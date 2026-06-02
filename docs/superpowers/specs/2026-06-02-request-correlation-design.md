# Request Correlation — Design Spec

**Date:** 2026-06-02  
**Status:** Approved  
**Standard:** `X-Request-Id` (non-W3C, industrie)

## Objectif

Permettre de corréler une requête HTTP entrante avec tous les logs qu'elle génère, y compris dans les consumers RabbitMQ async qui traitent les commandes qu'elle a dispatchées.

## Architecture

Six composants, tous dans `App\Shared\Infrastructure\`, aucune dépendance vers les bounded contexts.

| Composant | Namespace | Rôle |
|-----------|-----------|------|
| `RequestCorrelationContext` | `Correlation\` | Service singleton mutable — stocke le `request_id` courant |
| `RequestCorrelationListener` | `Http\` | Lit/génère le `X-Request-Id`, l'injecte dans le contexte, le renvoie en réponse |
| `CorrelationMonologProcessor` | `Log\` | Injecte `extra['request_id']` dans chaque entrée de log |
| `CorrelationStamp` | `Bus\` | `StampInterface` — porte le `request_id` sur l'enveloppe Messenger |
| `CorrelationMiddleware` | `Bus\Middleware\` | Attache le stamp à l'envoi, restaure le contexte à la réception |

## Flux HTTP

```
kernel.request (priority 10)
  → lit X-Request-Id entrant ou génère Uuid::v4()->toRfc4122()
  → RequestCorrelationContext::setId($id)

Traitement de la requête
  → Monolog : extra['request_id'] présent sur tous les logs
  → dispatch async : CorrelationMiddleware attache stamp + AMQP header

kernel.response
  → Response::headers->set('X-Request-Id', $context->getId())
```

## Flux Async (consumer RabbitMQ)

```
Dispatch (CorrelationMiddleware)
  → CorrelationStamp($id) sur l'enveloppe
  → AmqpStamp([], AMQP_NOPARAM, ['headers' => ['X-Request-Id' => $id]])

Receive (CorrelationMiddleware)
  → lit CorrelationStamp
  → RequestCorrelationContext::setId($stamp->getRequestId())
  → tous les logs du handler portent le request_id de la requête HTTP d'origine
```

## Génération de l'ID

- `X-Request-Id` présent en entrée → réutilisé tel quel (propagation gateway/load balancer)
- Absent → `Symfony\Component\Uid\Uuid::v4()->toRfc4122()`
- Pas de validation du format entrant (opaque ID)

## Cas limites

| Situation | Comportement |
|-----------|-------------|
| `getId()` appelé sans `setId()` (console, test) | Retourne un UUID généré à la construction — jamais `null` |
| `CorrelationStamp` absent d'un message reçu | Middleware génère un nouvel UUID, ne lève pas d'exception |
| `X-Request-Id` entrant malformé | Accepté tel quel, pas de rejet |

## Tests

| Type | Sujet |
|------|-------|
| Unit | `RequestCorrelationContext` : `getId()` sans `setId()` retourne un UUID valide |
| Unit | `CorrelationStamp` : porte l'id correctement |
| Unit | `CorrelationMonologProcessor` : injecte `extra['request_id']` |
| Unit | `CorrelationMiddleware` dispatch : `CorrelationStamp` + `AmqpStamp` présents |
| Unit | `CorrelationMiddleware` receive : contexte restauré depuis le stamp |
| Functional | Requête avec `X-Request-Id` entrant → même id en réponse |
| Functional | Requête sans header → `X-Request-Id` généré présent en réponse |

## Fichiers à créer

```
src/Shared/Infrastructure/Correlation/RequestCorrelationContext.php
src/Shared/Infrastructure/Http/RequestCorrelationListener.php
src/Shared/Infrastructure/Log/CorrelationMonologProcessor.php
src/Shared/Infrastructure/Bus/CorrelationStamp.php
src/Shared/Infrastructure/Bus/Middleware/CorrelationMiddleware.php
config/services/shared.yaml  (wiring du middleware + processor)
tests/Shared/Infrastructure/Correlation/RequestCorrelationContextTest.php
tests/Shared/Infrastructure/Bus/CorrelationStampTest.php
tests/Shared/Infrastructure/Log/CorrelationMonologProcessorTest.php
tests/Shared/Infrastructure/Bus/Middleware/CorrelationMiddlewareTest.php
tests/Shared/Infrastructure/Http/RequestCorrelationListenerTest.php  (functional)
```
