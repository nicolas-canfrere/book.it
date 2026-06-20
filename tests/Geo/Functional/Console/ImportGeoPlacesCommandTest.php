<?php

declare(strict_types=1);

namespace App\Tests\Geo\Functional\Console;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

#[Group('functional')]
final class ImportGeoPlacesCommandTest extends KernelTestCase
{
    private KernelInterface $appKernel;
    private Connection $geoConnection;

    protected function setUp(): void
    {
        $this->appKernel = self::bootKernel();
        $this->geoConnection = static::getContainer()->get('doctrine.dbal.geo_connection');
        $this->geoConnection->executeStatement('TRUNCATE geo_place');
    }

    #[Test]
    public function itImportsPlacesFromADumpFile(): void
    {
        $fixturePath = sys_get_temp_dir() . '/geo_places_fixture.txt';
        file_put_contents(
            $fixturePath,
            "2988507\tParis\tParis\tParis,Pariz\t48.85341\t2.3488\tP\tPPLC\tFR\t\t11\t75\t751\t75056\t2138551\t\t42\tEurope/Paris\t2024-01-01\n"
            . "4717560\tParis\tParis\t\t33.66094\t-95.55551\tP\tPPL\tUS\t\tTX\t\t\t\t25171\t\t136\tAmerica/Chicago\t2024-01-01\n",
        );

        $application = new Application($this->appKernel);
        $command = $application->find('geo:import-places');
        $tester = new CommandTester($command);
        $tester->execute(['file' => $fixturePath]);

        $tester->assertCommandIsSuccessful();
        unlink($fixturePath);

        $rows = $this->geoConnection->fetchAllAssociative('SELECT geoname_id, name, country_code, admin1_code FROM geo_place ORDER BY geoname_id');
        self::assertCount(2, $rows);
        self::assertSame('FR', $rows[0]['country_code']);
        self::assertSame('11', $rows[0]['admin1_code']);
        self::assertSame('US', $rows[1]['country_code']);
        self::assertSame('TX', $rows[1]['admin1_code']);
    }

    #[Test]
    public function itFailsWhenFileDoesNotExist(): void
    {
        $application = new Application($this->appKernel);
        $command = $application->find('geo:import-places');
        $tester = new CommandTester($command);
        $tester->execute(['file' => '/nonexistent/path.txt']);

        self::assertSame(1, $tester->getStatusCode());
    }
}
