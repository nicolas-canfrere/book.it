# Open Host Service et Published Language : communiquer entre contextes sans se coupler aux entrailles

Dans un projet en Domain-Driven Design, les bounded contexts délimitent des zones d'autonomie. Mais tôt ou tard, un contexte a besoin de données qui appartiennent à un autre. La question n'est pas *si* ils vont se parler — c'est *comment* ils vont le faire sans se retrouver enchevêtrés.

Deux patterns DDD répondent à cette question ensemble : **Open Host Service** et **Published Language**.

---

## Le problème qu'on cherche à éviter

Imaginez le contexte `Notification` qui doit envoyer un email de confirmation de réservation. Il lui faut le prénom, le nom et l'email du voyageur — des données qui appartiennent au contexte `Booker`.

La solution naïve : importer directement ce dont on a besoin.

```php
// ❌ Ce qu'on ne veut pas
use App\Booker\Application\Query\GetBookerQuery;
use App\Booker\Domain\Model\Booker; // L'agrégat du contexte voisin
```

Le problème est immédiat : `Notification` est maintenant couplé aux *entrailles* de `Booker`. Si `Booker` renomme son agrégat, réorganise ses use cases, ou change la structure interne de `Booker`, `Notification` explose. Les deux contextes évoluent en permanence ensemble — ce qui est exactement l'inverse de ce que les bounded contexts sont censés apporter.

---

## Open Host Service : exposer une porte d'entrée officielle

L'**Open Host Service** (OHS) est la décision du producteur d'exposer une *surface publique et stable* à destination des autres contextes. Plutôt que de laisser les consommateurs fouiller dans ses couches internes, il ouvre une porte d'entrée officielle.

Dans le code, cette surface vit dans un namespace dédié : `App\{Context}\Application\Contract\`.

```
src/
  Booker/
    Application/
      Contract/              ← L'Open Host Service de Booker
        BookerFinderInterface.php
        BookerView.php
    Domain/                  ← Les entrailles — personne n'y touche de l'extérieur
    Infrastructure/
      Contract/
        DoctrineBookerFinder.php   ← L'implémentation (côté producteur)
```

L'interface est la porte :

```php
// App\Booker\Application\Contract\BookerFinderInterface.php
interface BookerFinderInterface
{
    public function find(string $bookerId): ?BookerView;
}
```

C'est une promesse. `Booker` s'engage à honorer cette interface quelle que soit la façon dont son domaine interne évolue. Refactorer l'agrégat, changer l'ORM, migrer vers un event store — tout ça reste invisible pour les consommateurs.

Le producteur implémente l'interface dans sa couche Infrastructure, et c'est lui qui contrôle la traduction entre son domaine interne et ce qu'il expose :

```php
// App\Booker\Infrastructure\Contract\DoctrineBookerFinder.php
final readonly class DoctrineBookerFinder implements BookerFinderInterface
{
    public function __construct(private BookerRepositoryInterface $bookerRepository) {}

    public function find(string $bookerId): ?BookerView
    {
        $booker = $this->bookerRepository->get($bookerId);

        if (null === $booker) {
            return null;
        }

        // Traduction agrégat → DTO public. Les entrailles s'arrêtent ici.
        return new BookerView(
            id: $booker->id,
            firstName: $booker->firstName,
            lastName: $booker->lastName,
            email: $booker->email,
        );
    }
}
```

Le mapping agrégat → DTO est la responsabilité du producteur. C'est lui qui décide quoi exposer et dans quel format.

---

## Published Language : le vocabulaire partagé à la frontière

L'**Open Host Service** définit *comment* accéder aux données. Le **Published Language** (PL) définit *dans quel langage* ces données sont exprimées — c'est le vocabulaire partagé à la frontière entre contextes.

Concrètement, le Published Language prend la forme des DTOs stables exposés par l'OHS :

```php
// App\Booker\Application\Contract\BookerView.php
final readonly class BookerView
{
    public function __construct(
        public string $id,
        public string $firstName,
        public string $lastName,
        public string $email,
    ) {}
}
```

`BookerView` n'est pas un agrégat. Ce n'est pas un objet du domaine de `Booker`. C'est une **représentation stable et intentionnelle** de ce que `Booker` accepte de partager avec le monde extérieur. Ni plus, ni moins.

La distinction est cruciale : l'agrégat `Booker` pourrait avoir cinquante champs, des value objects complexes, un état interne riche. `BookerView` n'en expose que quatre — exactement ceux dont les consommateurs ont besoin. C'est un choix de design délibéré du producteur.

> Un `*View` est une API publique. Le modifier est un **breaking change** pour tous les consommateurs. On le traite avec le même soin qu'un endpoint HTTP.

---

## Côté consommateur : parler son propre langage

L'OHS et le PL résolvent le problème du côté producteur. Mais le consommateur a aussi une obligation symétrique : **ne pas laisser le langage du producteur envahir son propre domaine**.

`Notification` a besoin de contacter un voyageur. Dans son domaine à lui, ce concept s'appelle `BookerContact`, pas `BookerView`. Pour préserver cette autonomie, il définit son propre port dans son domaine :

```php
// App\Notification\Domain\Port\BookerContactFetcherInterface.php
interface BookerContactFetcherInterface
{
    public function fetch(string $bookerId): ?BookerContact;
}
```

Et `BookerContact` est son propre read model, exprimé dans *son* langage :

```php
// App\Notification\Domain\ReadModel\BookerContact.php
final readonly class BookerContact
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
    ) {}
}
```

La connexion entre les deux mondes se fait dans la couche Infrastructure du consommateur — et uniquement là :

```php
// App\Notification\Infrastructure\Service\BookerContactFetcher.php
final readonly class BookerContactFetcher implements BookerContactFetcherInterface
{
    public function __construct(private BookerFinderInterface $bookers) {}

    public function fetch(string $bookerId): ?BookerContact
    {
        $view = $this->bookers->find($bookerId);

        if (null === $view) {
            return null;
        }

        // Traduction PL du producteur → read model propre au consommateur
        return new BookerContact(
            firstName: $view->firstName,
            lastName: $view->lastName,
            email: $view->email,
        );
    }
}
```

Ce qui se passe ici est important : `BookerView` (le Published Language de `Booker`) ne traverse jamais la frontière de la couche Infrastructure de `Notification`. Le domaine de `Notification` ne connaît que `BookerContact` — son propre concept. La dépendance vers `Booker\Application\Contract` est localisée, explicite, et invisible depuis le reste du contexte.

---

## La mécanique vue de haut

La collaboration entre OHS et PL suit un schéma systématique :

```
Producteur (Booker)                     Consommateur (Notification)
─────────────────────                   ──────────────────────────
Application\Contract\
  BookerFinderInterface  ◄──────────────  Infrastructure\Service\
  BookerView (PL)                           BookerContactFetcher
                                               implements
Infrastructure\Contract\              Domain\Port\
  DoctrineBookerFinder                    BookerContactFetcherInterface
    implements                        Domain\ReadModel\
    BookerFinderInterface                 BookerContact
```

1. Le producteur publie une interface + un DTO stable (`Contract/`)
2. Le consommateur définit son propre port dans son domaine
3. L'adaptateur Infrastructure du consommateur implémente ce port en déléguant à l'OHS du producteur
4. Le domaine du consommateur ne connaît ni `BookerFinderInterface` ni `BookerView` — il parle son propre langage

---

## Ce que ça change en pratique

**L'évolution des internaux est libre.** `Booker` peut changer d'ORM, renommer son agrégat, restructurer son domaine entièrement — tant que `BookerFinderInterface` et `BookerView` restent stables, aucun consommateur n't est impacté.

**Les dépendances deviennent visibles et délibérées.** Dans ce projet, deptrac vérifie que les imports cross-contextes ne pointent *que* vers des namespaces `Contract`. Ajouter une nouvelle dépendance entre contextes nécessite une modification explicite de `deptrac-contexts.yaml` — une friction volontaire qui rend chaque nouveau couplage visible et discutable en revue de code.

**Chaque contexte parle son propre langage.** `Notification` travaille avec `BookerContact`. `Reservation` travaille avec son propre read model du voyageur. Le fait que tous les deux consultent `BookerFinderInterface` est un détail d'implémentation Infrastructure — pas un concept de domaine partagé.

**La surface contractuelle est auditable.** Le `contextmap.yaml` généré par `make contextmap` capture l'ensemble des OHS exposés et des relations de consommation. En un coup d'œil, on voit qui dépend de qui — et on peut vérifier en CI que le code correspond à la carte.

---

## Pour aller plus loin

OHS et PL couvrent les **lectures** cross-contextes. Pour les **écritures**, le pattern complémentaire est la **chorégraphie par événements** : le producteur publie un événement de domaine (un fait passé, immuable), les consommateurs réagissent avec leurs propres handlers. Même philosophie — pas de couplage direct, pas d'import des internaux — mais pour les mutations plutôt que les requêtes.

Les deux ensemble forment l'architecture de communication décrite dans [ADR 0015](adr/0015-cross-context-communication-via-published-contracts.md) et visualisée dans la [context map](context-map.md) du projet.
