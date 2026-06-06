<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260606200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add descriptive comments on all tables and columns';
    }

    public function up(Schema $schema): void
    {
        // hotel.hotel
        $this->addSql("COMMENT ON TABLE hotel.hotel IS 'Hôtel géré sur la plateforme. Agrégat racine du contexte Hotel, propriétaire des chambres et types de chambre.'");
        $this->addSql("COMMENT ON COLUMN hotel.hotel.id IS 'Identifiant unique de l''hôtel (UUID v4)'");
        $this->addSql("COMMENT ON COLUMN hotel.hotel.name IS 'Nom commercial de l''hôtel'");
        $this->addSql("COMMENT ON COLUMN hotel.hotel.street_address IS 'Adresse postale de l''hôtel (numéro et rue)'");
        $this->addSql("COMMENT ON COLUMN hotel.hotel.postal_code IS 'Code postal de l''hôtel'");
        $this->addSql("COMMENT ON COLUMN hotel.hotel.city IS 'Ville où se situe l''hôtel'");
        $this->addSql("COMMENT ON COLUMN hotel.hotel.country IS 'Code pays ISO 3166-1 alpha-2 (ex : FR, BE, DE)'");
        $this->addSql("COMMENT ON COLUMN hotel.hotel.search_key IS 'Clé de recherche normalisée combinant nom + adresse — unique, utilisée pour la déduplication et l''indexation full-text'");
        $this->addSql("COMMENT ON COLUMN hotel.hotel.stars IS 'Classement étoiles de l''hôtel (1–5), NULL si non classé'");
        $this->addSql("COMMENT ON COLUMN hotel.hotel.superior IS 'Indique un hôtel de catégorie supérieure au sein de sa classe d''étoiles'");
        $this->addSql("COMMENT ON COLUMN hotel.hotel.amenities IS 'Liste des équipements de l''hôtel (piscine, parking, spa…) — tableau de slugs normalisés'");
        $this->addSql("COMMENT ON COLUMN hotel.hotel.created_at IS 'Date d''enregistrement de l''hôtel sur la plateforme (DC2Type:datetime_immutable)'");

        // booker.booker
        $this->addSql("COMMENT ON TABLE booker.booker IS 'Client qui effectue des réservations. Agrégat racine du contexte Booker, identifié de façon unique par son email.'");
        $this->addSql("COMMENT ON COLUMN booker.booker.id IS 'Identifiant unique du booker (UUID v4)'");
        $this->addSql("COMMENT ON COLUMN booker.booker.first_name IS 'Prénom du booker'");
        $this->addSql("COMMENT ON COLUMN booker.booker.last_name IS 'Nom de famille du booker'");
        $this->addSql("COMMENT ON COLUMN booker.booker.email IS 'Adresse e-mail — unique sur la plateforme (index sur LOWER(email))'");
        $this->addSql("COMMENT ON COLUMN booker.booker.phone IS 'Numéro de téléphone au format international'");
        $this->addSql("COMMENT ON COLUMN booker.booker.date_of_birth IS 'Date de naissance — utilisée pour les vérifications d''âge minimum'");
        $this->addSql("COMMENT ON COLUMN booker.booker.registered_at IS 'Date d''inscription sur la plateforme (DC2Type:datetime_immutable)'");

        // room.room
        $this->addSql("COMMENT ON TABLE room.room IS 'Chambre physique d''un hôtel. Agrégat racine du contexte Room, rattachée à un hôtel et à un type de chambre.'");
        $this->addSql("COMMENT ON COLUMN room.room.id IS 'Identifiant unique de la chambre (UUID v4)'");
        $this->addSql("COMMENT ON COLUMN room.room.hotel_id IS 'Référence à l''hôtel propriétaire de la chambre'");
        $this->addSql("COMMENT ON COLUMN room.room.room_number IS 'Numéro ou code de la chambre au sein de l''hôtel — unique par hôtel'");
        $this->addSql("COMMENT ON COLUMN room.room.room_floor IS 'Étage où se situe la chambre (0 = rez-de-chaussée)'");
        $this->addSql("COMMENT ON COLUMN room.room.room_type_id IS 'Référence au type de chambre auquel appartient cette chambre physique'");
        $this->addSql("COMMENT ON COLUMN room.room.created_at IS 'Date d''ajout de la chambre dans le système (DC2Type:datetime_immutable)'");

        // room.room_type
        $this->addSql("COMMENT ON TABLE room.room_type IS 'Catégorie de chambre définie par l''hôtel (ex : Chambre double, Suite). Regroupe les chambres partageant les mêmes caractéristiques. Agrégat racine du contexte Room.'");
        $this->addSql("COMMENT ON COLUMN room.room_type.id IS 'Identifiant unique du type de chambre (UUID v4)'");
        $this->addSql("COMMENT ON COLUMN room.room_type.hotel_id IS 'Référence à l''hôtel auquel appartient ce type de chambre'");
        $this->addSql("COMMENT ON COLUMN room.room_type.name IS 'Nom du type de chambre au sein de l''hôtel — unique par hôtel'");
        $this->addSql("COMMENT ON COLUMN room.room_type.living_space_count IS 'Nombre de pièces de vie (salon, chambre séparée…)'");
        $this->addSql("COMMENT ON COLUMN room.room_type.surface_m2 IS 'Surface totale en m², NULL si non renseignée'");
        $this->addSql("COMMENT ON COLUMN room.room_type.guest_capacity IS 'Nombre maximum de personnes pouvant séjourner dans ce type de chambre'");
        $this->addSql("COMMENT ON COLUMN room.room_type.is_accessible IS 'Indique si la chambre est accessible aux personnes à mobilité réduite (norme PMR)'");
        $this->addSql("COMMENT ON COLUMN room.room_type.bed_composition IS 'Composition des lits (JSONB array, ex : [{\"type\":\"double\",\"count\":1}])'");
        $this->addSql("COMMENT ON COLUMN room.room_type.amenities IS 'Équipements spécifiques à ce type de chambre (climatisation, balcon…) — tableau de slugs normalisés'");
        $this->addSql("COMMENT ON COLUMN room.room_type.created_at IS 'Date de création du type de chambre (DC2Type:datetime_immutable)'");

        // reservation.reservation
        $this->addSql("COMMENT ON TABLE reservation.reservation IS 'Réservation confirmée d''une chambre par un booker. Agrégat racine du contexte Reservation.'");
        $this->addSql("COMMENT ON COLUMN reservation.reservation.id IS 'Identifiant unique de la réservation (UUID v4)'");
        $this->addSql("COMMENT ON COLUMN reservation.reservation.room_id IS 'Référence à la chambre réservée (Room context)'");
        $this->addSql("COMMENT ON COLUMN reservation.reservation.booker_id IS 'Référence au booker ayant effectué la réservation (Booker context)'");
        $this->addSql("COMMENT ON COLUMN reservation.reservation.check_in IS 'Date d''arrivée (incluse dans la période de séjour)'");
        $this->addSql("COMMENT ON COLUMN reservation.reservation.check_out IS 'Date de départ (exclue de la période de séjour)'");
        $this->addSql("COMMENT ON COLUMN reservation.reservation.total_price IS 'Prix total de la réservation en centimes d''euro'");
        $this->addSql("COMMENT ON COLUMN reservation.reservation.price_breakdown IS 'Détail du calcul du prix par nuit (JSONB array) — conservé pour traçabilité même si les tarifs changent ultérieurement'");
        $this->addSql("COMMENT ON COLUMN reservation.reservation.status IS 'Statut courant : pending | confirmed | cancelled'");
        $this->addSql("COMMENT ON COLUMN reservation.reservation.guest_count IS 'Nombre de voyageurs déclarés au moment de la réservation'");
        $this->addSql("COMMENT ON COLUMN reservation.reservation.cancellation_terms_days_threshold IS 'Seuil en jours de la politique d''annulation capturée au moment de la réservation — NULL si aucune politique définie'");
        $this->addSql("COMMENT ON COLUMN reservation.reservation.actual_departure_date IS 'Date de départ réelle du client — peut différer du check_out prévu en cas de départ anticipé ou prolongé'");
        $this->addSql("COMMENT ON COLUMN reservation.reservation.cancelled_at IS 'Date à laquelle la réservation a été annulée, NULL si non annulée'");
        $this->addSql("COMMENT ON COLUMN reservation.reservation.cancelled_by IS 'Qui a initié l''annulation : booker | operator | system'");
        $this->addSql("COMMENT ON COLUMN reservation.reservation.created_at IS 'Date de création de la réservation (DC2Type:datetime_immutable)'");

        // reservation.guest
        $this->addSql("COMMENT ON TABLE reservation.guest IS 'Voyageur déclaré pour une réservation. Entité enfant de reservation.reservation — supprimé en cascade avec la réservation.'");
        $this->addSql("COMMENT ON COLUMN reservation.guest.id IS 'Identifiant unique du voyageur (UUID v4)'");
        $this->addSql("COMMENT ON COLUMN reservation.guest.reservation_id IS 'Référence à la réservation à laquelle ce voyageur est rattaché'");
        $this->addSql("COMMENT ON COLUMN reservation.guest.first_name IS 'Prénom du voyageur'");
        $this->addSql("COMMENT ON COLUMN reservation.guest.last_name IS 'Nom de famille du voyageur'");
        $this->addSql("COMMENT ON COLUMN reservation.guest.date_of_birth IS 'Date de naissance du voyageur'");

        // availability.hold
        $this->addSql("COMMENT ON TABLE availability.hold IS 'Verrou temporaire de disponibilité posé pendant le processus de réservation. Empêche la double réservation pendant que le paiement est en cours. Expire automatiquement si la réservation n''aboutit pas.'");
        $this->addSql("COMMENT ON COLUMN availability.hold.id IS 'Identifiant unique du hold (UUID v4)'");
        $this->addSql("COMMENT ON COLUMN availability.hold.room_id IS 'Référence à la chambre dont la disponibilité est verrouillée'");
        $this->addSql("COMMENT ON COLUMN availability.hold.reservation_id IS 'Référence à la réservation en cours de création — unique : une réservation ne peut avoir qu''un seul hold actif'");
        $this->addSql("COMMENT ON COLUMN availability.hold.check_in IS 'Début de la période verrouillée (inclus)'");
        $this->addSql("COMMENT ON COLUMN availability.hold.check_out IS 'Fin de la période verrouillée (exclu)'");
        $this->addSql("COMMENT ON COLUMN availability.hold.expires_at IS 'Date d''expiration — passée cette date, le hold est caduc et la période redevient disponible (DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN availability.hold.created_at IS 'Date de création du hold (DC2Type:datetime_immutable)'");

        // availability.blocked_period
        $this->addSql("COMMENT ON TABLE availability.blocked_period IS 'Période de blocage manuel d''une chambre (maintenance, fermeture saisonnière, etc.). Rend la chambre indisponible à la réservation.'");
        $this->addSql("COMMENT ON COLUMN availability.blocked_period.id IS 'Identifiant unique de la période bloquée (UUID v4)'");
        $this->addSql("COMMENT ON COLUMN availability.blocked_period.room_id IS 'Référence à la chambre bloquée'");
        $this->addSql("COMMENT ON COLUMN availability.blocked_period.check_in IS 'Début du blocage (inclus)'");
        $this->addSql("COMMENT ON COLUMN availability.blocked_period.check_out IS 'Fin du blocage (exclu)'");
        $this->addSql("COMMENT ON COLUMN availability.blocked_period.created_at IS 'Date de création du blocage (DC2Type:datetime_immutable)'");

        // pricing.base_rate
        $this->addSql("COMMENT ON TABLE pricing.base_rate IS 'Tarif de base d''une chambre, appliqué lorsqu''aucune période tarifaire spécifique ne couvre les dates de séjour. Une seule ligne par chambre.'");
        $this->addSql("COMMENT ON COLUMN pricing.base_rate.room_id IS 'Référence à la chambre — clé primaire'");
        $this->addSql("COMMENT ON COLUMN pricing.base_rate.amount_cents IS 'Tarif de base par nuit en centimes d''euro'");
        $this->addSql("COMMENT ON COLUMN pricing.base_rate.updated_at IS 'Date de dernière mise à jour du tarif (DC2Type:datetime_immutable)'");

        // pricing.rate_period
        $this->addSql("COMMENT ON TABLE pricing.rate_period IS 'Tarif spécifique applicable à une chambre sur une période donnée (haute saison, événement…). Prend le dessus sur le tarif de base si la période chevauche les dates de séjour.'");
        $this->addSql("COMMENT ON COLUMN pricing.rate_period.id IS 'Identifiant unique de la période tarifaire (UUID v4)'");
        $this->addSql("COMMENT ON COLUMN pricing.rate_period.room_id IS 'Référence à la chambre concernée'");
        $this->addSql("COMMENT ON COLUMN pricing.rate_period.check_in IS 'Début de la période tarifaire (inclus)'");
        $this->addSql("COMMENT ON COLUMN pricing.rate_period.check_out IS 'Fin de la période tarifaire (exclu)'");
        $this->addSql("COMMENT ON COLUMN pricing.rate_period.amount_cents IS 'Tarif par nuit sur cette période, en centimes d''euro'");
        $this->addSql("COMMENT ON COLUMN pricing.rate_period.created_at IS 'Date de création (DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN pricing.rate_period.updated_at IS 'Date de dernière modification (DC2Type:datetime_immutable)'");

        // pricing.promotion
        $this->addSql("COMMENT ON TABLE pricing.promotion IS 'Promotion en pourcentage applicable à une chambre sur une période. La remise est appliquée au tarif calculé lors du calcul du prix de réservation.'");
        $this->addSql("COMMENT ON COLUMN pricing.promotion.id IS 'Identifiant unique de la promotion (UUID v4)'");
        $this->addSql("COMMENT ON COLUMN pricing.promotion.room_id IS 'Référence à la chambre bénéficiant de la promotion'");
        $this->addSql("COMMENT ON COLUMN pricing.promotion.check_in IS 'Début de la période promotionnelle (inclus)'");
        $this->addSql("COMMENT ON COLUMN pricing.promotion.check_out IS 'Fin de la période promotionnelle (exclu)'");
        $this->addSql("COMMENT ON COLUMN pricing.promotion.discount_percent IS 'Pourcentage de remise (0–100) appliqué au tarif calculé'");
        $this->addSql("COMMENT ON COLUMN pricing.promotion.created_at IS 'Date de création de la promotion (DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN pricing.promotion.updated_at IS 'Date de dernière modification (DC2Type:datetime_immutable)'");

        // pricing.cancellation_policy
        $this->addSql("COMMENT ON TABLE pricing.cancellation_policy IS 'Politique d''annulation d''une chambre. Définit le seuil en jours avant check_in à partir duquel l''annulation devient payante. Une seule ligne par chambre.'");
        $this->addSql("COMMENT ON COLUMN pricing.cancellation_policy.room_id IS 'Référence à la chambre — clé primaire'");
        $this->addSql("COMMENT ON COLUMN pricing.cancellation_policy.days_threshold IS 'Nombre de jours avant check_in en deçà duquel l''annulation est payante'");
        $this->addSql("COMMENT ON COLUMN pricing.cancellation_policy.updated_at IS 'Date de dernière mise à jour (DC2Type:datetime_immutable)'");

        // search.hotel_room_types
        $this->addSql("COMMENT ON TABLE search.hotel_room_types IS 'Read model de recherche — projection dénormalisée combinant Hotel et RoomType. Alimentée par des projectors sur les événements domaine. Sert les résultats de recherche de séjour.'");
        $this->addSql("COMMENT ON COLUMN search.hotel_room_types.room_type_id IS 'Identifiant du type de chambre — clé primaire de la projection'");
        $this->addSql("COMMENT ON COLUMN search.hotel_room_types.hotel_id IS 'Identifiant de l''hôtel propriétaire'");
        $this->addSql("COMMENT ON COLUMN search.hotel_room_types.hotel_name IS 'Nom de l''hôtel (dénormalisé)'");
        $this->addSql("COMMENT ON COLUMN search.hotel_room_types.city IS 'Ville de l''hôtel (dénormalisée depuis hotel.hotel)'");
        $this->addSql("COMMENT ON COLUMN search.hotel_room_types.country IS 'Pays de l''hôtel (dénormalisé depuis hotel.hotel)'");
        $this->addSql("COMMENT ON COLUMN search.hotel_room_types.star_rating IS 'Classement étoiles de l''hôtel (1–5), NULL si non classé'");
        $this->addSql("COMMENT ON COLUMN search.hotel_room_types.hotel_amenities IS 'Équipements de l''hôtel (JSONB array de slugs) — dénormalisés depuis hotel.hotel.amenities'");
        $this->addSql("COMMENT ON COLUMN search.hotel_room_types.room_type_name IS 'Nom du type de chambre (dénormalisé depuis room.room_type)'");
        $this->addSql("COMMENT ON COLUMN search.hotel_room_types.guest_capacity IS 'Capacité maximale du type de chambre (dénormalisée)'");
        $this->addSql("COMMENT ON COLUMN search.hotel_room_types.bed_composition IS 'Composition des lits (JSONB array) — dénormalisée depuis room.room_type'");
        $this->addSql("COMMENT ON COLUMN search.hotel_room_types.room_amenities IS 'Équipements du type de chambre (JSONB array de slugs) — dénormalisés depuis room.room_type.amenities'");
        $this->addSql("COMMENT ON COLUMN search.hotel_room_types.base_price_cents IS 'Tarif de base par nuit en centimes — dénormalisé depuis pricing.base_rate (NULL si non encore défini)'");

        // search.room_index
        $this->addSql("COMMENT ON TABLE search.room_index IS 'Index des chambres physiques pour le moteur de recherche. Lie une chambre à son type et son hôtel pour comptabiliser les disponibilités par type lors d''une recherche.'");
        $this->addSql("COMMENT ON COLUMN search.room_index.room_id IS 'Identifiant de la chambre physique — clé primaire'");
        $this->addSql("COMMENT ON COLUMN search.room_index.room_type_id IS 'Référence au type de chambre dans search.hotel_room_types'");
        $this->addSql("COMMENT ON COLUMN search.room_index.hotel_id IS 'Identifiant de l''hôtel (dénormalisé pour filtrage rapide)'");

        // search.unavailable_periods
        $this->addSql("COMMENT ON TABLE search.unavailable_periods IS 'Périodes d''indisponibilité des chambres dans le moteur de recherche. Alimentée par les réservations confirmées et les holds actifs. Utilise DATERANGE PostgreSQL avec index GiST pour des requêtes de chevauchement efficaces.'");
        $this->addSql("COMMENT ON COLUMN search.unavailable_periods.id IS 'Identifiant unique (UUID v4)'");
        $this->addSql("COMMENT ON COLUMN search.unavailable_periods.room_id IS 'Référence à la chambre indisponible'");
        $this->addSql("COMMENT ON COLUMN search.unavailable_periods.room_type_id IS 'Référence au type de chambre (pour agréger l''indisponibilité par type)'");
        $this->addSql("COMMENT ON COLUMN search.unavailable_periods.hotel_id IS 'Identifiant de l''hôtel (dénormalisé)'");
        $this->addSql("COMMENT ON COLUMN search.unavailable_periods.period IS 'Plage de dates d''indisponibilité (DATERANGE PostgreSQL, borne haute exclue) — indexée par GiST pour les requêtes de chevauchement'");
        $this->addSql("COMMENT ON COLUMN search.unavailable_periods.source_id IS 'Identifiant de la source ayant créé cette indisponibilité (reservation_id ou hold_id) — permet la suppression ciblée lors d''une annulation ou d''une expiration de hold'");

        // security.identity_mapping
        $this->addSql("COMMENT ON TABLE security.identity_mapping IS 'Correspondance entre les identités internes de la plateforme et les identités externes (OAuth, SSO). Permet de résoudre quel agrégat correspond à un token externe sans exposer les détails des contextes métier.'");
        $this->addSql("COMMENT ON COLUMN security.identity_mapping.internal_id IS 'UUID interne de l''entité sur la plateforme (ex : booker.id ou operator.id)'");
        $this->addSql("COMMENT ON COLUMN security.identity_mapping.context IS 'Contexte métier discriminant — indique dans quel bounded context chercher l''entité (ex : booker, operator). Fait partie de la clé primaire composite avec internal_id.'");
        $this->addSql("COMMENT ON COLUMN security.identity_mapping.external_id IS 'UUID fourni par le système d''authentification externe (provider OAuth, Keycloak, etc.)'");

        // operator.operator
        $this->addSql("COMMENT ON TABLE operator.operator IS 'Opérateur de la plateforme — personne habilitée à gérer les hôtels, chambres et tarifs. Distinct du booker (client). Agrégat racine du contexte Operator.'");
        $this->addSql("COMMENT ON COLUMN operator.operator.id IS 'Identifiant unique de l''opérateur (UUID v4)'");
        $this->addSql("COMMENT ON COLUMN operator.operator.first_name IS 'Prénom de l''opérateur'");
        $this->addSql("COMMENT ON COLUMN operator.operator.last_name IS 'Nom de famille de l''opérateur'");
        $this->addSql("COMMENT ON COLUMN operator.operator.email IS 'Adresse e-mail — unique sur la plateforme'");
        $this->addSql("COMMENT ON COLUMN operator.operator.phone IS 'Numéro de téléphone au format international'");
        $this->addSql("COMMENT ON COLUMN operator.operator.registered_at IS 'Date d''inscription sur la plateforme'");

        // translation.translation
        $this->addSql("COMMENT ON TABLE translation.translation IS 'Traductions des contenus éditoriaux (noms de types de chambre, descriptions…). Un sujet ne peut avoir qu''une seule traduction par locale.'");
        $this->addSql("COMMENT ON COLUMN translation.translation.id IS 'Identifiant unique de la traduction (UUID v4)'");
        $this->addSql("COMMENT ON COLUMN translation.translation.subject_type IS 'Type de l''entité traduite — discriminant (ex : room_type, hotel) reliant la traduction à son agrégat source'");
        $this->addSql("COMMENT ON COLUMN translation.translation.subject_id IS 'UUID de l''entité traduite dans son contexte d''origine'");
        $this->addSql("COMMENT ON COLUMN translation.translation.locale IS 'Code de locale BCP-47 (ex : fr, en, de, es)'");
        $this->addSql("COMMENT ON COLUMN translation.translation.text IS 'Texte traduit pour ce sujet dans cette locale'");
        $this->addSql("COMMENT ON COLUMN translation.translation.created_at IS 'Date de création de la traduction'");
        $this->addSql("COMMENT ON COLUMN translation.translation.updated_at IS 'Date de dernière mise à jour'");
    }

    public function down(Schema $schema): void
    {
        foreach ([
            'hotel.hotel',
            'booker.booker',
            'room.room',
            'room.room_type',
            'reservation.reservation',
            'reservation.guest',
            'availability.hold',
            'availability.blocked_period',
            'pricing.base_rate',
            'pricing.rate_period',
            'pricing.promotion',
            'pricing.cancellation_policy',
            'search.hotel_room_types',
            'search.room_index',
            'search.unavailable_periods',
            'security.identity_mapping',
            'operator.operator',
            'translation.translation',
        ] as $table) {
            $this->addSql("COMMENT ON TABLE $table IS NULL");
        }
    }
}
