# Cancellation Confirmation Email

**Date:** 2026-05-31  
**Status:** Approved

## Contexte

Quand un booker annule sa réservation, le `CancelReservationCommandHandler` dispatch un event `ReservationCancelled`. Aucun email n'est envoyé au booker à ce stade. L'objectif est d'envoyer un email de confirmation d'annulation avec, si applicable, le montant remboursé.

## Pattern suivi

Identique au flux `SendBookingConfirmationEmail` existant dans le contexte `Notification` :

- Un `EventListener` (Infrastructure) écoute l'event et dispatch une commande async
- Un `CommandHandler` (Application) fetch les données nécessaires et délègue l'envoi
- Un port interface (Domain) découple l'envoi de l'infrastructure
- Une implémentation Symfony Mailer (Infrastructure) envoie l'email via Twig

## Flux

```
ReservationCancelled (event)
  → ReservationCancelledListener
    → SendCancellationConfirmationEmailCommand (async)
      → SendCancellationConfirmationEmailCommandHandler
          → BookerContactFetcherInterface (existant, réutilisé)
          → ReservationDetailsFetcherInterface (existant, réutilisé)
          → CancellationConfirmationEmailSenderInterface
            → SymfonyMailerCancellationConfirmationEmailSender
              → cancellation_confirmation.html.twig / .txt.twig
```

## Fichiers à créer

| Fichier | Type |
|---|---|
| `src/Notification/Infrastructure/EventListener/ReservationCancelledListener.php` | EventListener |
| `src/Notification/Application/UseCase/SendCancellationConfirmationEmail/SendCancellationConfirmationEmailCommand.php` | AsyncCommandInterface |
| `src/Notification/Application/UseCase/SendCancellationConfirmationEmail/SendCancellationConfirmationEmailCommandHandler.php` | AsyncCommandHandlerInterface |
| `src/Notification/Domain/Port/CancellationConfirmationEmailSenderInterface.php` | Port |
| `src/Notification/Infrastructure/Service/SymfonyMailerCancellationConfirmationEmailSender.php` | Service |
| `templates/emails/cancellation_confirmation.html.twig` | Template |
| `templates/emails/cancellation_confirmation.txt.twig` | Template |

## Fichiers à modifier

| Fichier | Changement |
|---|---|
| `config/services/notification.yaml` | Alias `CancellationConfirmationEmailSenderInterface` + `$mailerFrom` pour le nouveau sender |

## Commande

`SendCancellationConfirmationEmailCommand` porte trois champs :
- `reservationId` — pour fetcher les détails (checkIn, checkOut, totalPriceCents)
- `bookerId` — pour fetcher le contact (email, firstName, lastName)
- `refundAmountCents` — calculé au moment de l'annulation dans le handler, non re-dérivable depuis la réservation

## Email

- **Sujet :** "Votre réservation a été annulée"
- **Corps :** prénom du booker, dates du séjour (checkIn / checkOut), montant remboursé (affiché uniquement si `refundAmountCents > 0`)
- **Langue :** français (cohérent avec l'email de confirmation existant)
- **Format :** HTML + TXT (deux templates Twig, même structure que `booking_confirmation`)

## Comportements défensifs

Le handler logge un warning et retourne silencieusement si :
- le booker n'est pas trouvé (`BookerContact === null`)
- la réservation n'est pas trouvée (`ReservationDetails === null`)

(Identique au comportement du `SendBookingConfirmationEmailCommandHandler`)

## Tests

- **Unit :** `SendCancellationConfirmationEmailCommandHandlerTest` — cas happy path, booker introuvable, réservation introuvable ; refund nul vs non-nul
- **Functional :** test que l'event `ReservationCancelled` déclenche bien l'envoi d'email via le listener (avec transport in-memory en test)
