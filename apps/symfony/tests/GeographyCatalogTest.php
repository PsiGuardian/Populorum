<?php

declare(strict_types=1);

use App\Service\GeographyCatalog;
use PHPUnit\Framework\TestCase;

final class GeographyCatalogTest extends TestCase
{
    public function testPilotFallbackWithoutNationalIndex(): void
    {
        $root = sys_get_temp_dir() . '/populorum-geo-' . bin2hex(random_bytes(6));
        mkdir($root . '/data', 0700, true);
        try {
            file_put_contents($root . '/data/cities.json', json_encode([
                ['id' => 't1', 'name' => 'Paris', 'code' => '75056', 'region' => 'Île-de-France', 'department' => 'Paris', 'sourceUrl' => 'https://www.insee.fr/'],
            ], JSON_THROW_ON_ERROR));
            $catalog = new GeographyCatalog($root);
            self::assertFalse($catalog->coverage()['available']);
            self::assertSame('Paris', $catalog->find('75056')['name']);
            self::assertSame('t1', $catalog->find('75056')['pilotId']);
            self::assertNull($catalog->find('99999'));
            self::assertSame('75056', $catalog->search('Par')[0]['code']);
            self::assertSame('75056', $catalog->cityPage('11', '75', 0)['rows'][0]['code']);
            self::assertSame(1, $catalog->coverage()['count']);
        } finally {
            @unlink($root . '/data/cities.json');
            @rmdir($root . '/data');
            @rmdir($root);
        }
    }

    public function testPostalLookupUsesPostalCodesAndReturnsAllMatches(): void
    {
        $source = dirname(__DIR__) . '/data/geography/communes-2026.sqlite.gz';
        $root = sys_get_temp_dir() . '/populorum-postal-' . bin2hex(random_bytes(8));
        mkdir($root . '/data/geography', 0700, true);
        $database = $root . '/data/geography/communes-2026.sqlite';
        try {
            $packed = file_get_contents($source);
            self::assertNotFalse($packed);
            $unpacked = gzdecode($packed);
            self::assertNotFalse($unpacked);
            self::assertNotFalse(file_put_contents($database, $unpacked));
            $catalog = new GeographyCatalog($root);
            self::assertTrue($catalog->coverage()['available']);
            self::assertTrue($catalog->coverage()['postals']);

            self::assertSame(['76351'], array_column($catalog->search('76600', 50), 'code'));
            self::assertSame('Le Havre', $catalog->search('76610', 50)[0]['name']);
            self::assertSame(['76600'], array_column($catalog->search('INSEE:76600'), 'code'));
            foreach (['66', '663'] as $prefix) {
                $candidates = $catalog->search($prefix, 50);
                self::assertNotEmpty($candidates);
                foreach ($candidates as $candidate) {
                    $codes = explode(', ', $candidate['postalCodes']);
                    self::assertNotEmpty(array_filter($codes, static fn (string $code): bool => str_starts_with($code, $prefix)), $candidate['name']);
                }
            }
            $results = $catalog->search('66300', 50);
            self::assertCount(18, $results);
            self::assertContains('Passa', array_column($results, 'name'));
            self::assertContains('Thuir', array_column($results, 'name'));
            self::assertContains('Trouillas', array_column($results, 'name'));
        } finally {
            @unlink($database);
            @rmdir($root . '/data/geography');
            @rmdir($root . '/data');
            @rmdir($root);
        }
    }

    public function testGeographicCodes(): void
    {
        self::assertTrue(GeographyCatalog::validCode('75056'));
        self::assertTrue(GeographyCatalog::validCode('2A004'));
        self::assertTrue(GeographyCatalog::validCode('2B123'));
        self::assertTrue(GeographyCatalog::validCode('97601'));
        self::assertFalse(GeographyCatalog::validCode('75'));
        self::assertFalse(GeographyCatalog::validCode('75056%'));
        self::assertFalse(GeographyCatalog::validCode('99999;drop'));
    }
}
