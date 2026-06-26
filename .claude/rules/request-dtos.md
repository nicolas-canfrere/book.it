---
paths:
  - "src/**/UI/Http/Controller/**/*Request.php"
---

# Request DTO Rules

## OpenAPI / Nelmio — type PHP = contrat OpenAPI

Nelmio dérive `required` et `nullable` **exclusivement depuis le système de types PHP**.
`#[OA\Property(required: true)]` et `#[Assert\NotBlank]` n'ont aucun effet sur ces champs.

| Type PHP | OpenAPI généré |
|----------|----------------|
| `string $prop` | `required: true`, non-nullable |
| `?string $prop` | `nullable: true` |
| `string $prop = 'default'` | `required: false` |
| `?string $prop = null` | `required: false`, `nullable: true` |

## Champs obligatoires

Utiliser un type non-nullable **sans valeur par défaut** :

```php
// correct
public string $name,
public int $guestCount,
public bool $isAccessible,

// interdit — génère required:false nullable:true dans openapi.yaml
public ?string $name = null,
```

Symfony (`#[MapQueryString]` et `#[MapRequestPayload]`) collecte les erreurs de désérialisation via `COLLECT_DENORMALIZATION_ERRORS` et retourne 422 pour les champs manquants — pas besoin de valeur par défaut.

## Champs optionnels

Réserver `?type = null` aux champs **vraiment optionnels** dans la logique métier.
Les placer **en dernier** dans le constructeur (PHP interdit les paramètres optionnels avant les requis) :

```php
public function __construct(
    public string $name,         // requis — en premier
    public int $guestCapacity,   // requis
    public ?int $surfaceM2 = null, // optionnel — en dernier
) {}
```

## Trade-off MapRequestPayload

Avec des types non-nullables sur un `#[MapRequestPayload]` DTO, `PartialDenormalizationException`
se déclenche **avant** le validator Symfony. Un champ présent-mais-invalide n'apparaît donc pas
dans le même 422 qu'un champ absent. C'est le comportement attendu — ne pas revenir à `?type = null`
pour contourner ce comportement.

## Après migration vers non-nullable

Supprimer les gardes défensives dans le contrôleur appelant :

```php
// avant (mort après migration)
name: $request->name ?? throw new \LogicException('name is required'),
firstName: $request->firstName ?? '',

// après
name: $request->name,
firstName: $request->firstName,
```

PHPStan signale ces restes en `nullCoalesce.property` — les corriger immédiatement.

## Composants de schéma non référencés

Nelmio génère des composants de schéma pour les DTOs `#[MapQueryString]` sans jamais les
référencer dans le spec. Les ajouter à `.redocly.lint-ignore.yaml` sous `no-unused-components`
pour éviter les faux positifs du linter OpenAPI.
