# Published Contracts — Lectures Cross-Contexte

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remplacer les 10 services de lecture cross-contexte qui utilisent `SyncQueryBus + Query interne` par des contrats publiés (`*FinderInterface + *View`) détenus par le producteur.

**Architecture:** Chaque producteur expose `Application\Contract\{Interface} + {View}`. L'implémentation vit en `Infrastructure\Contract\Doctrine*`. Les consommateurs injectent le contrat publié directement — plus de `SyncQueryBus` cross-contexte.

**Tech Stack:** PHP 8.4, Symfony 8.0, Doctrine DBAL, PHPUnit

---

## Structure des fichiers

### Nouveaux fichiers

```
src/Hotel/Application/Contract/HotelFinderInterface.php
src/Hotel/Application/Contract/HotelView.php
src/Hotel/Infrastructure/Contract/DoctrineHotelFinder.php

src/Room/Application/Contract/RoomFinderInterface.php
src/Room/Application/Contract/RoomView.php
src/Room/Infrastructure/Contract/DoctrineRoomFinder.php

src/Reservation/Application/Contract/ReservationFinderInterface.php
src/Reservation/Application/Contract/ReservationView.php
src/Reservation/Infrastructure/Contract/DoctrineReservationFinder.php

src/Availability/Application/Contract/AvailabilityCheckerInterface.php
src/Availability/Infrastructure/Contract/DoctrineAvailabilityChecker.php

src/Pricing/Application/Contract/PricingQuoteCalculatorInterface.php
src/Pricing/Application/Contract/PricingQuoteFinderInterface.php
src/Pricing/Application/Contract/PricingQuoteView.php
src/Pricing/Application/Contract/CancellationPolicyFinderInterface.php
src/Pricing/Application/Contract/CancellationPolicyView.php
src/Pricing/Infrastructure/Contract/DoctrinePricingQuoteFinder.php
src/Pricing/Infrastructure/Contract/DoctrineCancellationPolicyFinder.php
```

### Fichiers existants modifiés

```
src/Notification/Infrastructure/Service/BookerContactFetcher.php      (Task 1)
src/Room/Infrastructure/Persistence/Doctrine/HotelExistenceChecker.php (Task 2)
src/Availability/Infrastructure/Service/RoomExistenceChecker.php       (Task 3)
src/Pricing/Infrastructure/Service/RoomExistenceChecker.php            (Task 3)
src/Reservation/Infrastructure/Service/RoomExistenceChecker.php        (Task 3)
src/Reservation/Infrastructure/Service/RoomCapacityFetcher.php         (Task 3)
src/Notification/Infrastructure/Service/ReservationDetailsFetcher.php  (Task 4)
src/Reservation/Infrastructure/Service/AvailabilityChecker.php         (Task 5)
src/Reservation/Infrastructure/Service/PricingQuoteFetcher.php         (Task 6)
src/Reservation/Infrastructure/Service/PricingCancellationPolicyFetcher.php (Task 6)
src/Pricing/Application/UseCase/GetPricingQuote/GetPricingQuoteQueryHandler.php (Task 6 — implémente PricingQuoteCalculatorInterface)

config/services/hotel.yaml        (Task 2)
config/services/room.yaml         (Task 3)
config/services/reservation.yaml  (Task 4)
config/services/pricing.yaml      (Task 6)
```

---

## Task 1 — Booker : migrer BookerContactFetcher

`BookerFinderInterface` + `BookerView` existent déjà (PR #46). Seul le consommateur `Notification\BookerContactFetcher` reste à migrer.

**Files:**
- Modify: `src/Notification/Infrastructure/Service/BookerContactFetcher.php`
- Create: `tests/Notification/Infrastructure/Service/BookerContactFetcherTest.php`

- [ ] **Écrire le test qui échoue**

```php
<?php
// tests/Notification/Infrastructure/Service/BookerContactFetcherTest.php
declare(strict_types=1);

namespace App\Tests\Notification\Infrastructure\Service;

use App\Booker\Application\Contract\BookerFinderInterface;
use App\Booker\Application\Contract\BookerView;
use App\Notification\Domain\Port\BookerContactFetcherInterface;
use App\Notification\Infrastructure\Service\BookerContactFetcher;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class BookerContactFetcherTest extends TestCase
{
    private BookerFinderInterface&\PHPUnit\Framework\MockObject\Stub $bookerFinder;
    private BookerContactFetcherInterface $fetcher;

    protected function setUp(): void
    {
        $this->bookerFinder = $this->createStub(BookerFinderInterface::class);
        $this->fetcher = new BookerContactFetcher($this->bookerFinder);
    }

    public function test_fetch_returns_contact_when_booker_found(): void
    {
        $this->bookerFinder->method('find')->willReturn(
            new BookerView('booker-1', 'Alice', 'Dupont', 'alice@example.com')
        );

        $contact = $this->fetcher->fetch('booker-1');

        self::assertNotNull($contact);
        self::assertSame('Alice', $contact->firstName);
        self::assertSame('Dupont', $contact->lastName);
        self::assertSame('alice@example.com', $contact->email);
    }

    public function test_fetch_returns_null_when_booker_not_found(): void
    {
        $this->bookerFinder->method('find')->willReturn(null);

        self::assertNull($this->fetcher->fetch('unknown'));
    }
}
```

- [ ] **Lancer le test pour vérifier qu'il échoue**

```bash
make unit-test -- --filter BookerContactFetcherTest
```

Expected: FAIL — mauvaise signature du constructeur (attend encore `SyncQueryBusInterface`)

- [ ] **Mettre à jour BookerContactFetcher**

```php
<?php
// src/Notification/Infrastructure/Service/BookerContactFetcher.php
declare(strict_types=1);

namespace App\Notification\Infrastructure\Service;

use App\Booker\Application\Contract\BookerFinderInterface;
use App\Notification\Domain\Port\BookerContactFetcherInterface;
use App\Notification\Domain\ReadModel\BookerContact;

final readonly class BookerContactFetcher implements BookerContactFetcherInterface
{
    public function __construct(private BookerFinderInterface $bookers)
    {
    }

    public function fetch(string $bookerId): ?BookerContact
    {
        $view = $this->bookers->find($bookerId);

        if (null === $view) {
            return null;
        }

        return new BookerContact(
            firstName: $view->firstName,
            lastName: $view->lastName,
            email: $view->email,
        );
    }
}
```

- [ ] **Lancer le test pour vérifier qu'il passe**

```bash
make unit-test -- --filter BookerContactFetcherTest
```

Expected: PASS (2 tests)

- [ ] **Suite complète + lint**

```bash
make test && make lint
```

Expected: tous les tests passent, 0 erreur PHPStan, 0 violation deptrac

- [ ] **Commit**

```bash
git add src/Notification/Infrastructure/Service/BookerContactFetcher.php \
        tests/Notification/Infrastructure/Service/BookerContactFetcherTest.php
git commit -m "refactor(notification): inject BookerFinderInterface in BookerContactFetcher"
```

---

## Task 2 — Hotel : contrat publié + migrer HotelExistenceChecker

**Files:**
- Create: `src/Hotel/Application/Contract/HotelFinderInterface.php`
- Create: `src/Hotel/Application/Contract/HotelView.php`
- Create: `src/Hotel/Infrastructure/Contract/DoctrineHotelFinder.php`
- Modify: `src/Room/Infrastructure/Persistence/Doctrine/HotelExistenceChecker.php`
- Modify: `config/services/hotel.yaml`
- Create: `tests/Hotel/Infrastructure/Contract/DoctrineHotelFinderTest.php`
- Create: `tests/Room/Infrastructure/Persistence/Doctrine/HotelExistenceCheckerTest.php`

- [ ] **Écrire le test pour DoctrineHotelFinder**

```php
<?php
// tests/Hotel/Infrastructure/Contract/DoctrineHotelFinderTest.php
declare(strict_types=1);

namespace App\Tests\Hotel\Infrastructure\Contract;

use App\Hotel\Application\Contract\HotelFinderInterface;
use App\Hotel\Application\Contract\HotelView;
use App\Hotel\Domain\Model\Address;
use App\Hotel\Domain\Model\Hotel;
use App\Hotel\Domain\Port\HotelRepositoryInterface;
use App\Hotel\Infrastructure\Contract\DoctrineHotelFinder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DoctrineHotelFinderTest extends TestCase
{
    private HotelRepositoryInterface&\PHPUnit\Framework\MockObject\Stub $repository;
    private HotelFinderInterface $finder;

    protected function setUp(): void
    {
        $this->repository = $this->createStub(HotelRepositoryInterface::class);
        $this->finder = new DoctrineHotelFinder($this->repository);
    }

    public function test_find_returns_view_when_hotel_exists(): void
    {
        $hotel = new Hotel(
            id: 'hotel-1',
            name: 'Test Hotel',
            address: new Address('1 rue Test', '75001', 'Paris', 'FR'),
            createdAt: new \DateTimeImmutable(),
        );
        $this->repository->method('get')->willReturn($hotel);

        $view = $this->finder->find('hotel-1');

        self::assertInstanceOf(HotelView::class, $view);
        self::assertSame('hotel-1', $view->id);
    }

    public function test_find_returns_null_when_hotel_not_found(): void
    {
        $this->repository->method('get')->willReturn(null);

        self::assertNull($this->finder->find('unknown'));
    }
}
```

- [ ] **Lancer pour vérifier l'échec**

```bash
make unit-test -- --filter DoctrineHotelFinderTest
```

Expected: FAIL — `DoctrineHotelFinder` n'existe pas

- [ ] **Créer l'interface + le DTO**

```php
<?php
// src/Hotel/Application/Contract/HotelFinderInterface.php
declare(strict_types=1);

namespace App\Hotel\Application\Contract;

interface HotelFinderInterface
{
    public function find(string $hotelId): ?HotelView;
}
```

```php
<?php
// src/Hotel/Application/Contract/HotelView.php
declare(strict_types=1);

namespace App\Hotel\Application\Contract;

final readonly class HotelView
{
    public function __construct(public string $id)
    {
    }
}
```

- [ ] **Créer DoctrineHotelFinder**

```php
<?php
// src/Hotel/Infrastructure/Contract/DoctrineHotelFinder.php
declare(strict_types=1);

namespace App\Hotel\Infrastructure\Contract;

use App\Hotel\Application\Contract\HotelFinderInterface;
use App\Hotel\Application\Contract\HotelView;
use App\Hotel\Domain\Port\HotelRepositoryInterface;

final readonly class DoctrineHotelFinder implements HotelFinderInterface
{
    public function __construct(private HotelRepositoryInterface $hotelRepository)
    {
    }

    public function find(string $hotelId): ?HotelView
    {
        $hotel = $this->hotelRepository->get($hotelId);

        if (null === $hotel) {
            return null;
        }

        return new HotelView(id: $hotel->id);
    }
}
```

- [ ] **Lancer pour vérifier que le test passe**

```bash
make unit-test -- --filter DoctrineHotelFinderTest
```

Expected: PASS (2 tests)

- [ ] **Écrire le test pour HotelExistenceChecker**

```php
<?php
// tests/Room/Infrastructure/Persistence/Doctrine/HotelExistenceCheckerTest.php
declare(strict_types=1);

namespace App\Tests\Room\Infrastructure\Persistence\Doctrine;

use App\Hotel\Application\Contract\HotelFinderInterface;
use App\Hotel\Application\Contract\HotelView;
use App\Room\Domain\Port\HotelExistsInterface;
use App\Room\Infrastructure\Persistence\Doctrine\HotelExistenceChecker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class HotelExistenceCheckerTest extends TestCase
{
    private HotelFinderInterface&\PHPUnit\Framework\MockObject\Stub $hotelFinder;
    private HotelExistsInterface $checker;

    protected function setUp(): void
    {
        $this->hotelFinder = $this->createStub(HotelFinderInterface::class);
        $this->checker = new HotelExistenceChecker($this->hotelFinder);
    }

    public function test_returns_true_when_hotel_exists(): void
    {
        $this->hotelFinder->method('find')->willReturn(new HotelView('hotel-1'));

        self::assertTrue($this->checker->exists('hotel-1'));
    }

    public function test_returns_false_when_hotel_not_found(): void
    {
        $this->hotelFinder->method('find')->willReturn(null);

        self::assertFalse($this->checker->exists('unknown'));
    }
}
```

- [ ] **Lancer pour vérifier l'échec**

```bash
make unit-test -- --filter HotelExistenceCheckerTest
```

Expected: FAIL — mauvaise signature du constructeur

- [ ] **Mettre à jour HotelExistenceChecker**

```php
<?php
// src/Room/Infrastructure/Persistence/Doctrine/HotelExistenceChecker.php
declare(strict_types=1);

namespace App\Room\Infrastructure\Persistence\Doctrine;

use App\Hotel\Application\Contract\HotelFinderInterface;
use App\Room\Domain\Port\HotelExistsInterface;

final readonly class HotelExistenceChecker implements HotelExistsInterface
{
    public function __construct(private HotelFinderInterface $hotels)
    {
    }

    public function exists(string $hotelId): bool
    {
        return null !== $this->hotels->find($hotelId);
    }
}
```

- [ ] **Mettre à jour config/services/hotel.yaml**

Remplacer le bloc `App\Hotel\Application\:` par :

```yaml
    App\Hotel\Application\:
        resource: '../../src/Hotel/Application/'
        exclude:
            - '../../src/Hotel/Application/**/*Exception.php'
            - '../../src/Hotel/Application/**/*Command.php'
            - '../../src/Hotel/Application/**/*Query.php'
            - '../../src/Hotel/Application/Contract/*View.php'
```

- [ ] **Lancer pour vérifier que HotelExistenceCheckerTest passe**

```bash
make unit-test -- --filter HotelExistenceCheckerTest
```

Expected: PASS (2 tests)

- [ ] **Suite complète + lint**

```bash
make test && make lint
```

- [ ] **Commit**

```bash
git add src/Hotel/Application/Contract/ \
        src/Hotel/Infrastructure/Contract/ \
        src/Room/Infrastructure/Persistence/Doctrine/HotelExistenceChecker.php \
        config/services/hotel.yaml \
        tests/Hotel/Infrastructure/Contract/ \
        tests/Room/Infrastructure/Persistence/Doctrine/HotelExistenceCheckerTest.php
git commit -m "refactor(hotel): introduce HotelFinderInterface published contract"
```

---

## Task 3 — Room : contrat publié + 4 consommateurs

`RoomView` expose `id` + `capacity` (join SQL `room` × `room_type`). Couvre à la fois les checkers d'existence et le fetcher de capacité.

**Files:**
- Create: `src/Room/Application/Contract/RoomFinderInterface.php`
- Create: `src/Room/Application/Contract/RoomView.php`
- Create: `src/Room/Infrastructure/Contract/DoctrineRoomFinder.php`
- Modify: `src/Availability/Infrastructure/Service/RoomExistenceChecker.php`
- Modify: `src/Pricing/Infrastructure/Service/RoomExistenceChecker.php`
- Modify: `src/Reservation/Infrastructure/Service/RoomExistenceChecker.php`
- Modify: `src/Reservation/Infrastructure/Service/RoomCapacityFetcher.php`
- Modify: `config/services/room.yaml`
- Create: `tests/Room/Infrastructure/Contract/DoctrineRoomFinderTest.php`
- Create: `tests/Availability/Infrastructure/Service/RoomExistenceCheckerTest.php`
- Create: `tests/Pricing/Infrastructure/Service/RoomExistenceCheckerTest.php`
- Create: `tests/Reservation/Infrastructure/Service/RoomExistenceCheckerTest.php`
- Create: `tests/Reservation/Infrastructure/Service/RoomCapacityFetcherTest.php`

- [ ] **Écrire le test pour DoctrineRoomFinder**

`DoctrineRoomFinder` injecte `RoomRepositoryInterface` (existence) et `RoomCapacityFinderInterface` (capacité). Les deux sont des ports du domaine Room, autorisés en Infrastructure.

```php
<?php
// tests/Room/Infrastructure/Contract/DoctrineRoomFinderTest.php
declare(strict_types=1);

namespace App\Tests\Room\Infrastructure\Contract;

use App\Room\Application\Contract\RoomFinderInterface;
use App\Room\Application\Contract\RoomView;
use App\Room\Domain\Model\Room;
use App\Room\Domain\Port\RoomCapacityFinderInterface;
use App\Room\Domain\Port\RoomRepositoryInterface;
use App\Room\Domain\ValueObject\RoomFloor;
use App\Room\Domain\ValueObject\RoomNumber;
use App\Room\Infrastructure\Contract\DoctrineRoomFinder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DoctrineRoomFinderTest extends TestCase
{
    private RoomRepositoryInterface&\PHPUnit\Framework\MockObject\Stub $roomRepository;
    private RoomCapacityFinderInterface&\PHPUnit\Framework\MockObject\Stub $capacityFinder;
    private RoomFinderInterface $finder;

    protected function setUp(): void
    {
        $this->roomRepository = $this->createStub(RoomRepositoryInterface::class);
        $this->capacityFinder = $this->createStub(RoomCapacityFinderInterface::class);
        $this->finder = new DoctrineRoomFinder($this->roomRepository, $this->capacityFinder);
    }

    public function test_find_returns_view_with_capacity_when_room_exists(): void
    {
        $room = new Room(
            id: 'room-1',
            hotelId: 'hotel-1',
            number: new RoomNumber('101'),
            floor: new RoomFloor(1),
            roomTypeId: 'type-1',
            createdAt: new \DateTimeImmutable(),
        );
        $this->roomRepository->method('get')->willReturn($room);
        $this->capacityFinder->method('findCapacity')->willReturn(3);

        $view = $this->finder->find('room-1');

        self::assertInstanceOf(RoomView::class, $view);
        self::assertSame('room-1', $view->id);
        self::assertSame(3, $view->capacity);
    }

    public function test_find_returns_null_when_room_not_found(): void
    {
        $this->roomRepository->method('get')->willReturn(null);

        self::assertNull($this->finder->find('unknown'));
    }
}
```

- [ ] **Lancer pour vérifier l'échec**

```bash
make unit-test -- --filter DoctrineRoomFinderTest
```

Expected: FAIL — `DoctrineRoomFinder` n'existe pas

- [ ] **Créer l'interface + le DTO**

```php
<?php
// src/Room/Application/Contract/RoomFinderInterface.php
declare(strict_types=1);

namespace App\Room\Application\Contract;

interface RoomFinderInterface
{
    public function find(string $roomId): ?RoomView;
}
```

```php
<?php
// src/Room/Application/Contract/RoomView.php
declare(strict_types=1);

namespace App\Room\Application\Contract;

final readonly class RoomView
{
    public function __construct(
        public string $id,
        public int $capacity,
    ) {
    }
}
```

- [ ] **Créer DoctrineRoomFinder**

```php
<?php
// src/Room/Infrastructure/Contract/DoctrineRoomFinder.php
declare(strict_types=1);

namespace App\Room\Infrastructure\Contract;

use App\Room\Application\Contract\RoomFinderInterface;
use App\Room\Application\Contract\RoomView;
use App\Room\Domain\Port\RoomCapacityFinderInterface;
use App\Room\Domain\Port\RoomRepositoryInterface;

final readonly class DoctrineRoomFinder implements RoomFinderInterface
{
    public function __construct(
        private RoomRepositoryInterface $roomRepository,
        private RoomCapacityFinderInterface $capacityFinder,
    ) {
    }

    public function find(string $roomId): ?RoomView
    {
        $room = $this->roomRepository->get($roomId);

        if (null === $room) {
            return null;
        }

        return new RoomView(
            id: $room->id,
            capacity: $this->capacityFinder->findCapacity($roomId),
        );
    }
}
```

- [ ] **Lancer pour vérifier que DoctrineRoomFinderTest passe**

```bash
make unit-test -- --filter DoctrineRoomFinderTest
```

Expected: PASS (2 tests)

- [ ] **Écrire les tests des 4 consommateurs**

```php
<?php
// tests/Availability/Infrastructure/Service/RoomExistenceCheckerTest.php
declare(strict_types=1);

namespace App\Tests\Availability\Infrastructure\Service;

use App\Availability\Domain\Port\RoomExistsInterface;
use App\Availability\Infrastructure\Service\RoomExistenceChecker;
use App\Room\Application\Contract\RoomFinderInterface;
use App\Room\Application\Contract\RoomView;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RoomExistenceCheckerTest extends TestCase
{
    private RoomFinderInterface&\PHPUnit\Framework\MockObject\Stub $roomFinder;
    private RoomExistsInterface $checker;

    protected function setUp(): void
    {
        $this->roomFinder = $this->createStub(RoomFinderInterface::class);
        $this->checker = new RoomExistenceChecker($this->roomFinder);
    }

    public function test_returns_true_when_room_found(): void
    {
        $this->roomFinder->method('find')->willReturn(new RoomView('room-1', 2));

        self::assertTrue($this->checker->exists('room-1'));
    }

    public function test_returns_false_when_room_not_found(): void
    {
        $this->roomFinder->method('find')->willReturn(null);

        self::assertFalse($this->checker->exists('unknown'));
    }
}
```

```php
<?php
// tests/Pricing/Infrastructure/Service/RoomExistenceCheckerTest.php
declare(strict_types=1);

namespace App\Tests\Pricing\Infrastructure\Service;

use App\Pricing\Domain\Port\RoomExistsInterface;
use App\Pricing\Infrastructure\Service\RoomExistenceChecker;
use App\Room\Application\Contract\RoomFinderInterface;
use App\Room\Application\Contract\RoomView;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RoomExistenceCheckerTest extends TestCase
{
    private RoomFinderInterface&\PHPUnit\Framework\MockObject\Stub $roomFinder;
    private RoomExistsInterface $checker;

    protected function setUp(): void
    {
        $this->roomFinder = $this->createStub(RoomFinderInterface::class);
        $this->checker = new RoomExistenceChecker($this->roomFinder);
    }

    public function test_returns_true_when_room_found(): void
    {
        $this->roomFinder->method('find')->willReturn(new RoomView('room-1', 2));

        self::assertTrue($this->checker->exists('room-1'));
    }

    public function test_returns_false_when_room_not_found(): void
    {
        $this->roomFinder->method('find')->willReturn(null);

        self::assertFalse($this->checker->exists('unknown'));
    }
}
```

```php
<?php
// tests/Reservation/Infrastructure/Service/RoomExistenceCheckerTest.php
declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure\Service;

use App\Reservation\Domain\Port\RoomExistsInterface;
use App\Reservation\Infrastructure\Service\RoomExistenceChecker;
use App\Room\Application\Contract\RoomFinderInterface;
use App\Room\Application\Contract\RoomView;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RoomExistenceCheckerTest extends TestCase
{
    private RoomFinderInterface&\PHPUnit\Framework\MockObject\Stub $roomFinder;
    private RoomExistsInterface $checker;

    protected function setUp(): void
    {
        $this->roomFinder = $this->createStub(RoomFinderInterface::class);
        $this->checker = new RoomExistenceChecker($this->roomFinder);
    }

    public function test_returns_true_when_room_found(): void
    {
        $this->roomFinder->method('find')->willReturn(new RoomView('room-1', 2));

        self::assertTrue($this->checker->exists('room-1'));
    }

    public function test_returns_false_when_room_not_found(): void
    {
        $this->roomFinder->method('find')->willReturn(null);

        self::assertFalse($this->checker->exists('unknown'));
    }
}
```

```php
<?php
// tests/Reservation/Infrastructure/Service/RoomCapacityFetcherTest.php
declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure\Service;

use App\Reservation\Domain\Port\RoomCapacityFetcherInterface;
use App\Reservation\Infrastructure\Service\RoomCapacityFetcher;
use App\Room\Application\Contract\RoomFinderInterface;
use App\Room\Application\Contract\RoomView;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RoomCapacityFetcherTest extends TestCase
{
    private RoomFinderInterface&\PHPUnit\Framework\MockObject\Stub $roomFinder;
    private RoomCapacityFetcherInterface $fetcher;

    protected function setUp(): void
    {
        $this->roomFinder = $this->createStub(RoomFinderInterface::class);
        $this->fetcher = new RoomCapacityFetcher($this->roomFinder);
    }

    public function test_fetches_capacity_from_room_view(): void
    {
        $this->roomFinder->method('find')->willReturn(new RoomView('room-1', 4));

        self::assertSame(4, $this->fetcher->fetchCapacity('room-1'));
    }

    public function test_returns_zero_capacity_when_room_not_found(): void
    {
        $this->roomFinder->method('find')->willReturn(null);

        self::assertSame(0, $this->fetcher->fetchCapacity('unknown'));
    }
}
```

- [ ] **Lancer pour vérifier que les 4 tests échouent**

```bash
make unit-test -- --filter "RoomExistenceCheckerTest|RoomCapacityFetcherTest"
```

Expected: FAIL — mauvaises signatures de constructeur

- [ ] **Mettre à jour les 4 consommateurs**

```php
<?php
// src/Availability/Infrastructure/Service/RoomExistenceChecker.php
declare(strict_types=1);

namespace App\Availability\Infrastructure\Service;

use App\Availability\Domain\Port\RoomExistsInterface;
use App\Room\Application\Contract\RoomFinderInterface;

final readonly class RoomExistenceChecker implements RoomExistsInterface
{
    public function __construct(private RoomFinderInterface $rooms)
    {
    }

    public function exists(string $roomId): bool
    {
        return null !== $this->rooms->find($roomId);
    }
}
```

```php
<?php
// src/Pricing/Infrastructure/Service/RoomExistenceChecker.php
declare(strict_types=1);

namespace App\Pricing\Infrastructure\Service;

use App\Pricing\Domain\Port\RoomExistsInterface;
use App\Room\Application\Contract\RoomFinderInterface;

final readonly class RoomExistenceChecker implements RoomExistsInterface
{
    public function __construct(private RoomFinderInterface $rooms)
    {
    }

    public function exists(string $roomId): bool
    {
        return null !== $this->rooms->find($roomId);
    }
}
```

```php
<?php
// src/Reservation/Infrastructure/Service/RoomExistenceChecker.php
declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Reservation\Domain\Port\RoomExistsInterface;
use App\Room\Application\Contract\RoomFinderInterface;

final readonly class RoomExistenceChecker implements RoomExistsInterface
{
    public function __construct(private RoomFinderInterface $rooms)
    {
    }

    public function exists(string $roomId): bool
    {
        return null !== $this->rooms->find($roomId);
    }
}
```

```php
<?php
// src/Reservation/Infrastructure/Service/RoomCapacityFetcher.php
declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Reservation\Domain\Port\RoomCapacityFetcherInterface;
use App\Room\Application\Contract\RoomFinderInterface;

final readonly class RoomCapacityFetcher implements RoomCapacityFetcherInterface
{
    public function __construct(private RoomFinderInterface $rooms)
    {
    }

    public function fetchCapacity(string $roomId): int
    {
        $view = $this->rooms->find($roomId);

        if (null === $view) {
            return 0;
        }

        return $view->capacity;
    }
}
```

- [ ] **Mettre à jour config/services/room.yaml**

Remplacer le bloc `App\Room\Application\:` existant par :

```yaml
    App\Room\Application\:
        resource: '../../src/Room/Application/'
        exclude:
            - '../../src/Room/Application/**/*Exception.php'
            - '../../src/Room/Application/**/*Command.php'
            - '../../src/Room/Application/**/*Query.php'
            - '../../src/Room/Application/Contract/*View.php'
```

- [ ] **Lancer pour vérifier que les 4 tests consommateurs passent**

```bash
make unit-test -- --filter "RoomExistenceCheckerTest|RoomCapacityFetcherTest"
```

Expected: PASS (8 tests au total)

- [ ] **Suite complète + lint**

```bash
make test && make lint
```

- [ ] **Commit**

```bash
git add src/Room/Application/Contract/ \
        src/Room/Infrastructure/Contract/ \
        src/Availability/Infrastructure/Service/RoomExistenceChecker.php \
        src/Pricing/Infrastructure/Service/RoomExistenceChecker.php \
        src/Reservation/Infrastructure/Service/RoomExistenceChecker.php \
        src/Reservation/Infrastructure/Service/RoomCapacityFetcher.php \
        config/services/room.yaml \
        tests/Room/Infrastructure/Contract/ \
        tests/Availability/Infrastructure/Service/RoomExistenceCheckerTest.php \
        tests/Pricing/Infrastructure/Service/RoomExistenceCheckerTest.php \
        tests/Reservation/Infrastructure/Service/RoomExistenceCheckerTest.php \
        tests/Reservation/Infrastructure/Service/RoomCapacityFetcherTest.php
git commit -m "refactor(room): introduce RoomFinderInterface published contract"
```

---

## Task 4 — Reservation : contrat publié + migrer ReservationDetailsFetcher

**Files:**
- Create: `src/Reservation/Application/Contract/ReservationFinderInterface.php`
- Create: `src/Reservation/Application/Contract/ReservationView.php`
- Create: `src/Reservation/Infrastructure/Contract/DoctrineReservationFinder.php`
- Modify: `src/Notification/Infrastructure/Service/ReservationDetailsFetcher.php`
- Modify: `config/services/reservation.yaml`
- Create: `tests/Reservation/Infrastructure/Contract/DoctrineReservationFinderTest.php`
- Create: `tests/Notification/Infrastructure/Service/ReservationDetailsFetcherTest.php`

- [ ] **Écrire le test pour DoctrineReservationFinder**

```php
<?php
// tests/Reservation/Infrastructure/Contract/DoctrineReservationFinderTest.php
declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure\Contract;

use App\Reservation\Application\Contract\ReservationFinderInterface;
use App\Reservation\Application\Contract\ReservationView;
use App\Reservation\Domain\Model\Reservation;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;
use App\Reservation\Domain\ValueObject\CancellationTerms;
use App\Reservation\Domain\ValueObject\DatePeriod;
use App\Reservation\Domain\ValueObject\GuestCount;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use App\Reservation\Infrastructure\Contract\DoctrineReservationFinder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DoctrineReservationFinderTest extends TestCase
{
    private ReservationRepositoryInterface&\PHPUnit\Framework\MockObject\Stub $repository;
    private ReservationFinderInterface $finder;

    protected function setUp(): void
    {
        $this->repository = $this->createStub(ReservationRepositoryInterface::class);
        $this->finder = new DoctrineReservationFinder($this->repository);
    }

    public function test_find_returns_view_when_reservation_exists(): void
    {
        $checkIn = new \DateTimeImmutable('2026-07-01');
        $checkOut = new \DateTimeImmutable('2026-07-05');

        $reservation = new Reservation(
            id: 'res-1',
            roomId: 'room-1',
            bookerId: 'booker-1',
            period: new DatePeriod($checkIn, $checkOut),
            totalPrice: 40000,
            cancellationTerms: CancellationTerms::alwaysRefundable(),
            priceBreakdown: PriceBreakdown::fromArray([]),
            guestCount: new GuestCount(2),
            createdAt: new \DateTimeImmutable(),
        );
        $this->repository->method('get')->willReturn($reservation);

        $view = $this->finder->find('res-1');

        self::assertInstanceOf(ReservationView::class, $view);
        self::assertEquals($checkIn, $view->checkIn);
        self::assertEquals($checkOut, $view->checkOut);
        self::assertSame(40000, $view->totalPriceCents);
    }

    public function test_find_returns_null_when_reservation_not_found(): void
    {
        $this->repository->method('get')->willReturn(null);

        self::assertNull($this->finder->find('unknown'));
    }
}
```

- [ ] **Lancer pour vérifier l'échec**

```bash
make unit-test -- --filter DoctrineReservationFinderTest
```

Expected: FAIL — `DoctrineReservationFinder` n'existe pas

- [ ] **Créer l'interface + le DTO**

```php
<?php
// src/Reservation/Application/Contract/ReservationFinderInterface.php
declare(strict_types=1);

namespace App\Reservation\Application\Contract;

interface ReservationFinderInterface
{
    public function find(string $reservationId): ?ReservationView;
}
```

```php
<?php
// src/Reservation/Application/Contract/ReservationView.php
declare(strict_types=1);

namespace App\Reservation\Application\Contract;

final readonly class ReservationView
{
    public function __construct(
        public \DateTimeImmutable $checkIn,
        public \DateTimeImmutable $checkOut,
        public int $totalPriceCents,
    ) {
    }
}
```

- [ ] **Créer DoctrineReservationFinder**

```php
<?php
// src/Reservation/Infrastructure/Contract/DoctrineReservationFinder.php
declare(strict_types=1);

namespace App\Reservation\Infrastructure\Contract;

use App\Reservation\Application\Contract\ReservationFinderInterface;
use App\Reservation\Application\Contract\ReservationView;
use App\Reservation\Domain\Port\ReservationRepositoryInterface;

final readonly class DoctrineReservationFinder implements ReservationFinderInterface
{
    public function __construct(private ReservationRepositoryInterface $reservationRepository)
    {
    }

    public function find(string $reservationId): ?ReservationView
    {
        $reservation = $this->reservationRepository->get($reservationId);

        if (null === $reservation) {
            return null;
        }

        return new ReservationView(
            checkIn: $reservation->period->checkIn,
            checkOut: $reservation->period->checkOut,
            totalPriceCents: $reservation->totalPrice,
        );
    }
}
```

- [ ] **Lancer pour vérifier que DoctrineReservationFinderTest passe**

```bash
make unit-test -- --filter DoctrineReservationFinderTest
```

Expected: PASS (2 tests)

- [ ] **Écrire le test pour ReservationDetailsFetcher**

```php
<?php
// tests/Notification/Infrastructure/Service/ReservationDetailsFetcherTest.php
declare(strict_types=1);

namespace App\Tests\Notification\Infrastructure\Service;

use App\Notification\Domain\Port\ReservationDetailsFetcherInterface;
use App\Notification\Infrastructure\Service\ReservationDetailsFetcher;
use App\Reservation\Application\Contract\ReservationFinderInterface;
use App\Reservation\Application\Contract\ReservationView;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ReservationDetailsFetcherTest extends TestCase
{
    private ReservationFinderInterface&\PHPUnit\Framework\MockObject\Stub $reservationFinder;
    private ReservationDetailsFetcherInterface $fetcher;

    protected function setUp(): void
    {
        $this->reservationFinder = $this->createStub(ReservationFinderInterface::class);
        $this->fetcher = new ReservationDetailsFetcher($this->reservationFinder);
    }

    public function test_fetch_returns_details_when_reservation_found(): void
    {
        $checkIn = new \DateTimeImmutable('2026-07-01');
        $checkOut = new \DateTimeImmutable('2026-07-05');

        $this->reservationFinder->method('find')->willReturn(
            new ReservationView($checkIn, $checkOut, 40000)
        );

        $details = $this->fetcher->fetch('res-1');

        self::assertNotNull($details);
        self::assertEquals($checkIn, $details->checkIn);
        self::assertEquals($checkOut, $details->checkOut);
        self::assertSame(40000, $details->totalPriceCents);
    }

    public function test_fetch_returns_null_when_reservation_not_found(): void
    {
        $this->reservationFinder->method('find')->willReturn(null);

        self::assertNull($this->fetcher->fetch('unknown'));
    }
}
```

- [ ] **Lancer pour vérifier l'échec**

```bash
make unit-test -- --filter ReservationDetailsFetcherTest
```

Expected: FAIL — mauvaise signature du constructeur

- [ ] **Mettre à jour ReservationDetailsFetcher**

```php
<?php
// src/Notification/Infrastructure/Service/ReservationDetailsFetcher.php
declare(strict_types=1);

namespace App\Notification\Infrastructure\Service;

use App\Notification\Domain\Port\ReservationDetailsFetcherInterface;
use App\Notification\Domain\ReadModel\ReservationDetails;
use App\Reservation\Application\Contract\ReservationFinderInterface;

final readonly class ReservationDetailsFetcher implements ReservationDetailsFetcherInterface
{
    public function __construct(private ReservationFinderInterface $reservations)
    {
    }

    public function fetch(string $reservationId): ?ReservationDetails
    {
        $view = $this->reservations->find($reservationId);

        if (null === $view) {
            return null;
        }

        return new ReservationDetails(
            checkIn: $view->checkIn,
            checkOut: $view->checkOut,
            totalPriceCents: $view->totalPriceCents,
        );
    }
}
```

- [ ] **Mettre à jour config/services/reservation.yaml**

Remplacer le bloc `App\Reservation\Application\:` existant par :

```yaml
    App\Reservation\Application\:
        resource: '../../src/Reservation/Application/'
        exclude:
            - '../../src/Reservation/Application/**/*Command.php'
            - '../../src/Reservation/Application/**/*Query.php'
            - '../../src/Reservation/Application/Contract/*View.php'
```

- [ ] **Lancer pour vérifier que ReservationDetailsFetcherTest passe**

```bash
make unit-test -- --filter ReservationDetailsFetcherTest
```

Expected: PASS (2 tests)

- [ ] **Suite complète + lint**

```bash
make test && make lint
```

- [ ] **Commit**

```bash
git add src/Reservation/Application/Contract/ \
        src/Reservation/Infrastructure/Contract/ \
        src/Notification/Infrastructure/Service/ReservationDetailsFetcher.php \
        config/services/reservation.yaml \
        tests/Reservation/Infrastructure/Contract/ \
        tests/Notification/Infrastructure/Service/ReservationDetailsFetcherTest.php
git commit -m "refactor(reservation): introduce ReservationFinderInterface published contract"
```

---

## Task 5 — Availability : contrat publié + migrer AvailabilityChecker

Pas de `*View` ici — le contrat expose directement un booléen.

**Files:**
- Create: `src/Availability/Application/Contract/AvailabilityCheckerInterface.php`
- Create: `src/Availability/Infrastructure/Contract/DoctrineAvailabilityChecker.php`
- Modify: `src/Reservation/Infrastructure/Service/AvailabilityChecker.php`
- Create: `tests/Availability/Infrastructure/Contract/DoctrineAvailabilityCheckerTest.php`
- Create: `tests/Reservation/Infrastructure/Service/AvailabilityCheckerTest.php`

- [ ] **Écrire le test pour DoctrineAvailabilityChecker**

```php
<?php
// tests/Availability/Infrastructure/Contract/DoctrineAvailabilityCheckerTest.php
declare(strict_types=1);

namespace App\Tests\Availability\Infrastructure\Contract;

use App\Availability\Application\Contract\AvailabilityCheckerInterface;
use App\Availability\Domain\Port\AvailabilityHoldRepositoryInterface;
use App\Availability\Domain\Port\BlockedPeriodRepositoryInterface;
use App\Availability\Infrastructure\Contract\DoctrineAvailabilityChecker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DoctrineAvailabilityCheckerTest extends TestCase
{
    private BlockedPeriodRepositoryInterface&\PHPUnit\Framework\MockObject\Stub $blockedPeriods;
    private AvailabilityHoldRepositoryInterface&\PHPUnit\Framework\MockObject\Stub $holds;
    private AvailabilityCheckerInterface $checker;

    private \DateTimeImmutable $checkIn;
    private \DateTimeImmutable $checkOut;

    protected function setUp(): void
    {
        $this->blockedPeriods = $this->createStub(BlockedPeriodRepositoryInterface::class);
        $this->holds = $this->createStub(AvailabilityHoldRepositoryInterface::class);
        $this->checker = new DoctrineAvailabilityChecker($this->blockedPeriods, $this->holds);
        $this->checkIn = new \DateTimeImmutable('2026-07-01');
        $this->checkOut = new \DateTimeImmutable('2026-07-05');
    }

    public function test_returns_false_when_blocked_period_overlaps(): void
    {
        $this->blockedPeriods->method('hasOverlap')->willReturn(true);

        self::assertFalse($this->checker->isAvailable('room-1', $this->checkIn, $this->checkOut));
    }

    public function test_returns_false_when_active_hold_overlaps(): void
    {
        $this->blockedPeriods->method('hasOverlap')->willReturn(false);
        $this->holds->method('hasActiveOverlap')->willReturn(true);

        self::assertFalse($this->checker->isAvailable('room-1', $this->checkIn, $this->checkOut));
    }

    public function test_returns_true_when_no_overlap(): void
    {
        $this->blockedPeriods->method('hasOverlap')->willReturn(false);
        $this->holds->method('hasActiveOverlap')->willReturn(false);

        self::assertTrue($this->checker->isAvailable('room-1', $this->checkIn, $this->checkOut));
    }
}
```

- [ ] **Lancer pour vérifier l'échec**

```bash
make unit-test -- --filter DoctrineAvailabilityCheckerTest
```

Expected: FAIL — `DoctrineAvailabilityChecker` n'existe pas

- [ ] **Créer l'interface**

```php
<?php
// src/Availability/Application/Contract/AvailabilityCheckerInterface.php
declare(strict_types=1);

namespace App\Availability\Application\Contract;

interface AvailabilityCheckerInterface
{
    public function isAvailable(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): bool;
}
```

- [ ] **Créer DoctrineAvailabilityChecker**

```php
<?php
// src/Availability/Infrastructure/Contract/DoctrineAvailabilityChecker.php
declare(strict_types=1);

namespace App\Availability\Infrastructure\Contract;

use App\Availability\Application\Contract\AvailabilityCheckerInterface;
use App\Availability\Domain\Port\AvailabilityHoldRepositoryInterface;
use App\Availability\Domain\Port\BlockedPeriodRepositoryInterface;

final readonly class DoctrineAvailabilityChecker implements AvailabilityCheckerInterface
{
    public function __construct(
        private BlockedPeriodRepositoryInterface $blockedPeriods,
        private AvailabilityHoldRepositoryInterface $holds,
    ) {
    }

    public function isAvailable(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): bool
    {
        if ($this->blockedPeriods->hasOverlap($roomId, $checkIn, $checkOut)) {
            return false;
        }

        return !$this->holds->hasActiveOverlap($roomId, $checkIn, $checkOut);
    }
}
```

- [ ] **Lancer pour vérifier que DoctrineAvailabilityCheckerTest passe**

```bash
make unit-test -- --filter DoctrineAvailabilityCheckerTest
```

Expected: PASS (3 tests)

- [ ] **Écrire le test pour AvailabilityChecker (Reservation consumer)**

```php
<?php
// tests/Reservation/Infrastructure/Service/AvailabilityCheckerTest.php
declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure\Service;

use App\Availability\Application\Contract\AvailabilityCheckerInterface;
use App\Reservation\Domain\Port\RoomAvailabilityCheckerInterface;
use App\Reservation\Infrastructure\Service\AvailabilityChecker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class AvailabilityCheckerTest extends TestCase
{
    private AvailabilityCheckerInterface&\PHPUnit\Framework\MockObject\Stub $availabilityChecker;
    private RoomAvailabilityCheckerInterface $checker;

    protected function setUp(): void
    {
        $this->availabilityChecker = $this->createStub(AvailabilityCheckerInterface::class);
        $this->checker = new AvailabilityChecker($this->availabilityChecker);
    }

    public function test_returns_true_when_available(): void
    {
        $this->availabilityChecker->method('isAvailable')->willReturn(true);

        $checkIn = new \DateTimeImmutable('2026-07-01');
        $checkOut = new \DateTimeImmutable('2026-07-05');

        self::assertTrue($this->checker->isAvailable('room-1', $checkIn, $checkOut));
    }

    public function test_returns_false_when_not_available(): void
    {
        $this->availabilityChecker->method('isAvailable')->willReturn(false);

        $checkIn = new \DateTimeImmutable('2026-07-01');
        $checkOut = new \DateTimeImmutable('2026-07-05');

        self::assertFalse($this->checker->isAvailable('room-1', $checkIn, $checkOut));
    }
}
```

- [ ] **Lancer pour vérifier l'échec**

```bash
make unit-test -- --filter "App\Tests\Reservation\Infrastructure\Service\AvailabilityCheckerTest"
```

Expected: FAIL — mauvaise signature du constructeur

- [ ] **Mettre à jour AvailabilityChecker**

```php
<?php
// src/Reservation/Infrastructure/Service/AvailabilityChecker.php
declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Availability\Application\Contract\AvailabilityCheckerInterface;
use App\Reservation\Domain\Port\RoomAvailabilityCheckerInterface;

final readonly class AvailabilityChecker implements RoomAvailabilityCheckerInterface
{
    public function __construct(private AvailabilityCheckerInterface $availabilityChecker)
    {
    }

    public function isAvailable(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): bool
    {
        return $this->availabilityChecker->isAvailable($roomId, $checkIn, $checkOut);
    }
}
```

- [ ] **Lancer pour vérifier que AvailabilityCheckerTest passe**

```bash
make unit-test -- --filter "App\Tests\Reservation\Infrastructure\Service\AvailabilityCheckerTest"
```

Expected: PASS (2 tests)

- [ ] **Suite complète + lint**

```bash
make test && make lint
```

- [ ] **Commit**

```bash
git add src/Availability/Application/Contract/ \
        src/Availability/Infrastructure/Contract/ \
        src/Reservation/Infrastructure/Service/AvailabilityChecker.php \
        tests/Availability/Infrastructure/Contract/ \
        tests/Reservation/Infrastructure/Service/AvailabilityCheckerTest.php
git commit -m "refactor(availability): introduce AvailabilityCheckerInterface published contract"
```

---

## Task 6 — Pricing : deux contrats publiés + 2 consommateurs

Deux besoins distincts : le devis de prix (complex, délègue au handler existant) et la politique d'annulation (simple mapping d'agrégat).

**Files:**
- Create: `src/Pricing/Application/Contract/PricingQuoteFinderInterface.php`
- Create: `src/Pricing/Application/Contract/PricingQuoteView.php`
- Create: `src/Pricing/Application/Contract/CancellationPolicyFinderInterface.php`
- Create: `src/Pricing/Application/Contract/CancellationPolicyView.php`
- Create: `src/Pricing/Infrastructure/Contract/DoctrinePricingQuoteFinder.php`
- Create: `src/Pricing/Infrastructure/Contract/DoctrineCancellationPolicyFinder.php`
- Modify: `src/Reservation/Infrastructure/Service/PricingQuoteFetcher.php`
- Modify: `src/Reservation/Infrastructure/Service/PricingCancellationPolicyFetcher.php`
- Modify: `config/services/pricing.yaml`
- Create: `tests/Pricing/Infrastructure/Contract/DoctrinePricingQuoteFinderTest.php`
- Create: `tests/Pricing/Infrastructure/Contract/DoctrineCancellationPolicyFinderTest.php`
- Create: `tests/Reservation/Infrastructure/Service/PricingQuoteFetcherTest.php`
- Create: `tests/Reservation/Infrastructure/Service/PricingCancellationPolicyFetcherTest.php`

### Sous-tâche 6a : CancellationPolicy (le plus simple d'abord)

- [ ] **Écrire le test pour DoctrineCancellationPolicyFinder**

```php
<?php
// tests/Pricing/Infrastructure/Contract/DoctrineCancellationPolicyFinderTest.php
declare(strict_types=1);

namespace App\Tests\Pricing\Infrastructure\Contract;

use App\Pricing\Application\Contract\CancellationPolicyFinderInterface;
use App\Pricing\Application\Contract\CancellationPolicyView;
use App\Pricing\Domain\Model\CancellationPolicy;
use App\Pricing\Domain\Port\CancellationPolicyRepositoryInterface;
use App\Pricing\Infrastructure\Contract\DoctrineCancellationPolicyFinder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DoctrineCancellationPolicyFinderTest extends TestCase
{
    private CancellationPolicyRepositoryInterface&\PHPUnit\Framework\MockObject\Stub $repository;
    private CancellationPolicyFinderInterface $finder;

    protected function setUp(): void
    {
        $this->repository = $this->createStub(CancellationPolicyRepositoryInterface::class);
        $this->finder = new DoctrineCancellationPolicyFinder($this->repository);
    }

    public function test_find_returns_view_when_policy_exists(): void
    {
        $policy = new CancellationPolicy('room-1', 7, new \DateTimeImmutable());
        $this->repository->method('findByRoomId')->willReturn($policy);

        $view = $this->finder->find('room-1');

        self::assertInstanceOf(CancellationPolicyView::class, $view);
        self::assertSame(7, $view->daysThreshold);
    }

    public function test_find_returns_null_when_no_policy(): void
    {
        $this->repository->method('findByRoomId')->willReturn(null);

        self::assertNull($this->finder->find('room-1'));
    }
}
```

- [ ] **Lancer pour vérifier l'échec**

```bash
make unit-test -- --filter DoctrineCancellationPolicyFinderTest
```

Expected: FAIL — `DoctrineCancellationPolicyFinder` n'existe pas

- [ ] **Créer les fichiers de contrat CancellationPolicy**

```php
<?php
// src/Pricing/Application/Contract/CancellationPolicyFinderInterface.php
declare(strict_types=1);

namespace App\Pricing\Application\Contract;

interface CancellationPolicyFinderInterface
{
    public function find(string $roomId): ?CancellationPolicyView;
}
```

```php
<?php
// src/Pricing/Application/Contract/CancellationPolicyView.php
declare(strict_types=1);

namespace App\Pricing\Application\Contract;

final readonly class CancellationPolicyView
{
    public function __construct(public int $daysThreshold)
    {
    }
}
```

- [ ] **Créer DoctrineCancellationPolicyFinder**

Pour lire `daysThreshold`, inspecter `CancellationPolicy` : si c'est une propriété publique, accéder directement. Si c'est via getter, adapter. (Le consommateur `PricingCancellationPolicyFetcher` utilisait `$policy->daysThreshold` — propriété publique.)

```php
<?php
// src/Pricing/Infrastructure/Contract/DoctrineCancellationPolicyFinder.php
declare(strict_types=1);

namespace App\Pricing\Infrastructure\Contract;

use App\Pricing\Application\Contract\CancellationPolicyFinderInterface;
use App\Pricing\Application\Contract\CancellationPolicyView;
use App\Pricing\Domain\Port\CancellationPolicyRepositoryInterface;

final readonly class DoctrineCancellationPolicyFinder implements CancellationPolicyFinderInterface
{
    public function __construct(private CancellationPolicyRepositoryInterface $cancellationPolicies)
    {
    }

    public function find(string $roomId): ?CancellationPolicyView
    {
        $policy = $this->cancellationPolicies->findByRoomId($roomId);

        if (null === $policy) {
            return null;
        }

        return new CancellationPolicyView(daysThreshold: $policy->daysThreshold);
    }
}
```

- [ ] **Lancer pour vérifier que DoctrineCancellationPolicyFinderTest passe**

```bash
make unit-test -- --filter DoctrineCancellationPolicyFinderTest
```

Expected: PASS (2 tests)

- [ ] **Écrire le test pour PricingCancellationPolicyFetcher**

```php
<?php
// tests/Reservation/Infrastructure/Service/PricingCancellationPolicyFetcherTest.php
declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure\Service;

use App\Pricing\Application\Contract\CancellationPolicyFinderInterface;
use App\Pricing\Application\Contract\CancellationPolicyView;
use App\Reservation\Domain\Port\CancellationPolicyFetcherInterface;
use App\Reservation\Infrastructure\Service\PricingCancellationPolicyFetcher;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class PricingCancellationPolicyFetcherTest extends TestCase
{
    private CancellationPolicyFinderInterface&\PHPUnit\Framework\MockObject\Stub $policyFinder;
    private CancellationPolicyFetcherInterface $fetcher;

    protected function setUp(): void
    {
        $this->policyFinder = $this->createStub(CancellationPolicyFinderInterface::class);
        $this->fetcher = new PricingCancellationPolicyFetcher($this->policyFinder);
    }

    public function test_returns_terms_with_threshold_when_policy_exists(): void
    {
        $this->policyFinder->method('find')->willReturn(new CancellationPolicyView(7));

        $terms = $this->fetcher->fetch('room-1');

        self::assertSame(7, $terms->daysThreshold);
    }

    public function test_returns_always_refundable_when_no_policy(): void
    {
        $this->policyFinder->method('find')->willReturn(null);

        $terms = $this->fetcher->fetch('room-1');

        self::assertNull($terms->daysThreshold);
    }
}
```

- [ ] **Lancer pour vérifier l'échec**

```bash
make unit-test -- --filter PricingCancellationPolicyFetcherTest
```

Expected: FAIL — mauvaise signature du constructeur

- [ ] **Mettre à jour PricingCancellationPolicyFetcher**

```php
<?php
// src/Reservation/Infrastructure/Service/PricingCancellationPolicyFetcher.php
declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Pricing\Application\Contract\CancellationPolicyFinderInterface;
use App\Reservation\Domain\Port\CancellationPolicyFetcherInterface;
use App\Reservation\Domain\ValueObject\CancellationTerms;

final readonly class PricingCancellationPolicyFetcher implements CancellationPolicyFetcherInterface
{
    public function __construct(private CancellationPolicyFinderInterface $cancellationPolicies)
    {
    }

    public function fetch(string $roomId): CancellationTerms
    {
        $view = $this->cancellationPolicies->find($roomId);

        if (null === $view) {
            return CancellationTerms::alwaysRefundable();
        }

        return CancellationTerms::withThreshold($view->daysThreshold);
    }
}
```

- [ ] **Lancer pour vérifier que PricingCancellationPolicyFetcherTest passe**

```bash
make unit-test -- --filter PricingCancellationPolicyFetcherTest
```

Expected: PASS (2 tests)

### Sous-tâche 6b : PricingQuote

`GetPricingQuoteQueryHandler` est `final readonly` — PHPUnit ne peut pas le stubber. On introduit `PricingQuoteCalculatorInterface` dans `Application\Contract\` que le handler implémente. `DoctrinePricingQuoteFinder` injecte cette interface : testable, sans duplication de la logique de calcul.

**Fichiers supplémentaires :**
- Create: `src/Pricing/Application/Contract/PricingQuoteCalculatorInterface.php`
- Modify: `src/Pricing/Application/UseCase/GetPricingQuote/GetPricingQuoteQueryHandler.php`

- [ ] **Créer PricingQuoteCalculatorInterface**

```php
<?php
// src/Pricing/Application/Contract/PricingQuoteCalculatorInterface.php
declare(strict_types=1);

namespace App\Pricing\Application\Contract;

interface PricingQuoteCalculatorInterface
{
    /**
     * @return array{roomId: string, checkIn: string, checkOut: string, totalAmountCents: int, nights: list<array{date: string, rateAmountCents: int, discountPercent: int|null, effectiveAmountCents: int}>}
     * @throws \DomainException if room does not exist or has no base rate
     */
    public function calculate(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): array;
}
```

- [ ] **Faire implémenter l'interface par GetPricingQuoteQueryHandler**

Ajouter `PricingQuoteCalculatorInterface` à la déclaration de classe et ajouter la méthode `calculate()` :

```php
// src/Pricing/Application/UseCase/GetPricingQuote/GetPricingQuoteQueryHandler.php

// Ligne de déclaration — ajouter l'interface :
final readonly class GetPricingQuoteQueryHandler implements SyncQueryHandlerInterface, PricingQuoteCalculatorInterface

// Ajouter après la méthode __invoke() :
    public function calculate(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): array
    {
        return ($this)(new GetPricingQuoteQuery($roomId, $checkIn, $checkOut));
    }
```

Ne pas oublier l'import : `use App\Pricing\Application\Contract\PricingQuoteCalculatorInterface;`

- [ ] **Écrire le test pour DoctrinePricingQuoteFinder**

```php
<?php
// tests/Pricing/Infrastructure/Contract/DoctrinePricingQuoteFinderTest.php
declare(strict_types=1);

namespace App\Tests\Pricing\Infrastructure\Contract;

use App\Pricing\Application\Contract\PricingQuoteCalculatorInterface;
use App\Pricing\Application\Contract\PricingQuoteFinderInterface;
use App\Pricing\Application\Contract\PricingQuoteView;
use App\Pricing\Infrastructure\Contract\DoctrinePricingQuoteFinder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DoctrinePricingQuoteFinderTest extends TestCase
{
    private PricingQuoteCalculatorInterface&\PHPUnit\Framework\MockObject\Stub $calculator;
    private PricingQuoteFinderInterface $finder;

    protected function setUp(): void
    {
        $this->calculator = $this->createStub(PricingQuoteCalculatorInterface::class);
        $this->finder = new DoctrinePricingQuoteFinder($this->calculator);
    }

    public function test_fetch_returns_view_from_calculator_result(): void
    {
        $checkIn = new \DateTimeImmutable('2026-07-01');
        $checkOut = new \DateTimeImmutable('2026-07-03');

        $nights = [
            ['date' => '2026-07-01', 'rateAmountCents' => 10000, 'discountPercent' => null, 'effectiveAmountCents' => 10000],
            ['date' => '2026-07-02', 'rateAmountCents' => 10000, 'discountPercent' => null, 'effectiveAmountCents' => 10000],
        ];

        $this->calculator->method('calculate')->willReturn([
            'roomId' => 'room-1',
            'checkIn' => '2026-07-01',
            'checkOut' => '2026-07-03',
            'totalAmountCents' => 20000,
            'nights' => $nights,
        ]);

        $view = $this->finder->fetch('room-1', $checkIn, $checkOut);

        self::assertInstanceOf(PricingQuoteView::class, $view);
        self::assertSame(20000, $view->totalAmountCents);
        self::assertSame($nights, $view->nights);
    }

    public function test_fetch_propagates_domain_exception(): void
    {
        $this->calculator->method('calculate')->willThrowException(new \DomainException('no base rate'));

        $this->expectException(\DomainException::class);

        $this->finder->fetch('room-1', new \DateTimeImmutable('2026-07-01'), new \DateTimeImmutable('2026-07-03'));
    }
}
```

- [ ] **Lancer pour vérifier l'échec**

```bash
make unit-test -- --filter DoctrinePricingQuoteFinderTest
```

Expected: FAIL — `DoctrinePricingQuoteFinder` n'existe pas

- [ ] **Créer les fichiers de contrat PricingQuote**

```php
<?php
// src/Pricing/Application/Contract/PricingQuoteFinderInterface.php
declare(strict_types=1);

namespace App\Pricing\Application\Contract;

interface PricingQuoteFinderInterface
{
    /**
     * @throws \DomainException if the room has no base rate or does not exist
     */
    public function fetch(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): PricingQuoteView;
}
```

```php
<?php
// src/Pricing/Application/Contract/PricingQuoteView.php
declare(strict_types=1);

namespace App\Pricing\Application\Contract;

final readonly class PricingQuoteView
{
    /**
     * @param list<array{date: string, rateAmountCents: int, discountPercent: int|null, effectiveAmountCents: int}> $nights
     */
    public function __construct(
        public int $totalAmountCents,
        public array $nights,
    ) {
    }
}
```

- [ ] **Créer DoctrinePricingQuoteFinder**

```php
<?php
// src/Pricing/Infrastructure/Contract/DoctrinePricingQuoteFinder.php
declare(strict_types=1);

namespace App\Pricing\Infrastructure\Contract;

use App\Pricing\Application\Contract\PricingQuoteCalculatorInterface;
use App\Pricing\Application\Contract\PricingQuoteFinderInterface;
use App\Pricing\Application\Contract\PricingQuoteView;

final readonly class DoctrinePricingQuoteFinder implements PricingQuoteFinderInterface
{
    public function __construct(private PricingQuoteCalculatorInterface $calculator)
    {
    }

    public function fetch(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): PricingQuoteView
    {
        $result = $this->calculator->calculate($roomId, $checkIn, $checkOut);

        return new PricingQuoteView(
            totalAmountCents: $result['totalAmountCents'],
            nights: $result['nights'],
        );
    }
}
```

- [ ] **Lancer pour vérifier que DoctrinePricingQuoteFinderTest passe**

```bash
make unit-test -- --filter DoctrinePricingQuoteFinderTest
```

Expected: PASS (2 tests)

- [ ] **Écrire le test pour PricingQuoteFetcher**

```php
<?php
// tests/Reservation/Infrastructure/Service/PricingQuoteFetcherTest.php
declare(strict_types=1);

namespace App\Tests\Reservation\Infrastructure\Service;

use App\Pricing\Application\Contract\PricingQuoteFinderInterface;
use App\Pricing\Application\Contract\PricingQuoteView;
use App\Reservation\Domain\Exception\RoomNotBookableException;
use App\Reservation\Domain\Port\PricingQuoteFetcherInterface;
use App\Reservation\Infrastructure\Service\PricingQuoteFetcher;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class PricingQuoteFetcherTest extends TestCase
{
    private PricingQuoteFinderInterface&\PHPUnit\Framework\MockObject\Stub $pricingFinder;
    private PricingQuoteFetcherInterface $fetcher;

    protected function setUp(): void
    {
        $this->pricingFinder = $this->createStub(PricingQuoteFinderInterface::class);
        $this->fetcher = new PricingQuoteFetcher($this->pricingFinder);
    }

    public function test_fetch_returns_snapshot_from_view(): void
    {
        $nights = [
            ['date' => '2026-07-01', 'rateAmountCents' => 10000, 'discountPercent' => null, 'effectiveAmountCents' => 10000],
        ];
        $this->pricingFinder->method('fetch')->willReturn(new PricingQuoteView(10000, $nights));

        $checkIn = new \DateTimeImmutable('2026-07-01');
        $checkOut = new \DateTimeImmutable('2026-07-02');

        $snapshot = $this->fetcher->fetch('room-1', $checkIn, $checkOut);

        self::assertSame(10000, $snapshot->totalAmountCents); // PricingQuoteSnapshot::$totalAmountCents (public readonly int)
    }

    public function test_fetch_throws_room_not_bookable_on_domain_exception(): void
    {
        $this->pricingFinder->method('fetch')->willThrowException(new \DomainException('no base rate'));

        $this->expectException(RoomNotBookableException::class);

        $this->fetcher->fetch('room-1', new \DateTimeImmutable('2026-07-01'), new \DateTimeImmutable('2026-07-02'));
    }
}
```

- [ ] **Lancer pour vérifier l'échec**

```bash
make unit-test -- --filter PricingQuoteFetcherTest
```

Expected: FAIL — mauvaise signature du constructeur

- [ ] **Mettre à jour PricingQuoteFetcher**

```php
<?php
// src/Reservation/Infrastructure/Service/PricingQuoteFetcher.php
declare(strict_types=1);

namespace App\Reservation\Infrastructure\Service;

use App\Pricing\Application\Contract\PricingQuoteFinderInterface;
use App\Reservation\Domain\Exception\RoomNotBookableException;
use App\Reservation\Domain\Port\PricingQuoteFetcherInterface;
use App\Reservation\Domain\ValueObject\PriceBreakdown;
use App\Reservation\Domain\ValueObject\PricingQuoteSnapshot;

final readonly class PricingQuoteFetcher implements PricingQuoteFetcherInterface
{
    public function __construct(private PricingQuoteFinderInterface $pricingFinder)
    {
    }

    public function fetch(string $roomId, \DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut): PricingQuoteSnapshot
    {
        try {
            $view = $this->pricingFinder->fetch($roomId, $checkIn, $checkOut);

            return new PricingQuoteSnapshot(
                $view->totalAmountCents,
                PriceBreakdown::fromArray($view->nights),
            );
        } catch (\DomainException) {
            throw new RoomNotBookableException($roomId);
        }
    }
}
```

- [ ] **Mettre à jour config/services/pricing.yaml**

Remplacer le bloc `App\Pricing\Application\:` existant par :

```yaml
    App\Pricing\Application\:
        resource: '../../src/Pricing/Application/'
        exclude:
            - '../../src/Pricing/Application/**/*Command.php'
            - '../../src/Pricing/Application/**/*Query.php'
            - '../../src/Pricing/Application/Contract/*View.php'
```

- [ ] **Lancer pour vérifier que les 2 tests consommateurs passent**

```bash
make unit-test -- --filter "PricingQuoteFetcherTest|PricingCancellationPolicyFetcherTest"
```

Expected: PASS (4 tests)

- [ ] **Suite complète + lint**

```bash
make test && make lint
```

Expected: tous les tests passent, 0 erreur PHPStan, 0 violation deptrac

- [ ] **Commit final**

```bash
git add src/Pricing/Application/Contract/ \
        src/Pricing/Infrastructure/Contract/ \
        src/Reservation/Infrastructure/Service/PricingQuoteFetcher.php \
        src/Reservation/Infrastructure/Service/PricingCancellationPolicyFetcher.php \
        config/services/pricing.yaml \
        tests/Pricing/Infrastructure/Contract/ \
        tests/Reservation/Infrastructure/Service/PricingQuoteFetcherTest.php \
        tests/Reservation/Infrastructure/Service/PricingCancellationPolicyFetcherTest.php
git commit -m "refactor(pricing): introduce PricingQuoteFinderInterface and CancellationPolicyFinderInterface published contracts"
```
