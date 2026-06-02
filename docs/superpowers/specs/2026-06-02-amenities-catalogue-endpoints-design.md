# Design : Catalogue d'amenities — endpoints GET

**Date :** 2026-06-02
**Contextes :** Hotel, Room

## Contexte

Les contextes Hotel et Room disposent déjà de routes `PATCH` pour déclarer les amenities d'une entité spécifique. Les amenities sont exposées dans les réponses `GET /hotels/{id}` et `GET /hotels/{hotelId}/room-types/{roomTypeId}`.

Il manque des endpoints catalogue permettant à un front-office de lister **toutes les valeurs d'amenities possibles** (valeurs de l'enum PHP), indépendamment de toute entité persistée.

## Routes

| Méthode | Route | Tag OpenAPI | Statut |
|---------|-------|-------------|--------|
| `GET` | `/hotel-amenities` | Hotels | à créer |
| `GET` | `/room-type-amenities` | Room Types | à créer |

Pas de paramètres de chemin ni de query string. Pas d'authentification requise.

## Réponse

```json
{
  "amenities": ["concierge", "pool", "spa", "gym", "..."]
}
```

`200 OK`, `application/json`.

## Architecture (Option B — CQRS)

### Hotel context

```
src/Hotel/Application/UseCase/GetHotelAmenities/
  GetHotelAmenitiesQuery.php          # SyncQueryInterface<string[]>, aucun argument
  GetHotelAmenitiesQueryHandler.php   # retourne HotelAmenity::values()

src/Hotel/UI/Http/Controller/GetHotelAmenities/
  GetHotelAmenitiesController.php     # GET /hotel-amenities
```

### Room context

```
src/Room/Application/UseCase/GetRoomTypeAmenities/
  GetRoomTypeAmenitiesQuery.php
  GetRoomTypeAmenitiesQueryHandler.php  # retourne RoomAmenity::values()

src/Room/UI/Http/Controller/GetRoomTypeAmenities/
  GetRoomTypeAmenitiesController.php    # GET /room-type-amenities
```

### Détails d'implémentation

- Les handlers n'ont **aucune dépendance** : ils lisent l'enum directement via `::values()`, pas de repository
- Les queries n'ont **aucun argument**
- Pas de request DTO (aucun paramètre attendu)
- Les controllers passent par le `SyncQueryBusInterface` pour respecter le pattern existant

## Tests

- 1 test fonctionnel par endpoint (`#[Group('functional')]`)
- Vérification : `GET /hotel-amenities` → 200, structure `{"amenities": [...]}`, au moins une valeur connue présente
- Idem pour `GET /room-type-amenities`
- `make openapi` à relancer après implémentation

## Hors périmètre

- Filtrage ou pagination (données statiques, volume limité)
- Endpoint par entité (les amenities d'un hôtel spécifique sont déjà dans `GET /hotels/{id}`)
