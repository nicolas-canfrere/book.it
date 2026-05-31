<?php

declare(strict_types=1);

namespace App\Tests\Shared\UI\Console;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;

#[Group('functional')]
final class GenerateEventsCatalogCommandTest extends KernelTestCase
{
    public function testGeneratesDomainEventsYaml(): void
    {
        $kernel = self::bootKernel();
        $outputFile = $kernel->getProjectDir() . '/domainevents-test.yaml';

        $application = new Application($kernel);
        $command = $application->find('app:events:catalog');
        $tester = new CommandTester($command);

        $tester->execute(['--output' => 'domainevents-test.yaml']);

        try {
            self::assertSame(0, $tester->getStatusCode());
            self::assertFileExists($outputFile);

            $catalog = Yaml::parseFile($outputFile);
            self::assertIsArray($catalog);
            self::assertArrayHasKey('generated_at', $catalog);

            $events = $catalog['events'];
            self::assertIsArray($events);

            foreach ([
                'ReservationCreated',
                'ReservationConfirmed',
                'ReservationExpired',
                'ReservationCheckedOut',
                'ReservationPaymentCancelled',
            ] as $eventName) {
                self::assertArrayHasKey($eventName, $events, "Event $eventName missing from catalog");
                $eventData = $events[$eventName];
                self::assertIsArray($eventData);
                self::assertNotEmpty($eventData['listeners'], "Event $eventName has no listeners");
                $listeners = $eventData['listeners'];
                self::assertIsArray($listeners);
                foreach ($listeners as $listener) {
                    self::assertIsArray($listener);
                    self::assertArrayHasKey('context', $listener);
                    self::assertArrayHasKey('class', $listener);
                }
            }
        } finally {
            if (file_exists($outputFile)) {
                unlink($outputFile);
            }
        }
    }
}
