<?php

declare(strict_types=1);

use App\Service\GeographyCatalog;
use PHPUnit\Framework\TestCase;

final class GeographyCatalogTest extends TestCase
{
    public function testMissingIndexDoesNotInventFormerPilotMunicipalities(): void
    {
        $root = sys_get_temp_dir() . '/populorum-geo-' . bin2hex(random_bytes(6));
        mkdir($root, 0700, true);
        try {
            $catalog = new GeographyCatalog($root);
            self::assertFalse($catalog->coverage()['available']);
            self::assertSame(0, $catalog->coverage()['count']);
            self::assertNull($catalog->find('75056'));
            self::assertSame([], $catalog->search('Paris'));
            self::assertSame([], $catalog->search('66300'));
            self::assertSame([], $catalog->regions());
            self::assertSame([], $catalog->departments('11'));
            self::assertSame(['rows' => [], 'total' => 0], $catalog->cityPage('11', '75', 0));
        } finally {
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

            $exactLeHavre = $catalog->search('76600', 50);
            self::assertSame(['76351'], array_column($exactLeHavre, 'code'));
            self::assertSame('76600', $exactLeHavre[0]['postalCodes']);
            self::assertSame('76610', $catalog->search('76610', 50)[0]['postalCodes']);
            self::assertSame('Le Havre', $catalog->search('76610', 50)[0]['name']);
            self::assertSame(['76600'], array_column($catalog->search('INSEE:76600'), 'code'));
            foreach (['66', '663'] as $prefix) {
                $candidates = $catalog->search($prefix, 50);
                self::assertNotEmpty($candidates);
                foreach ($candidates as $candidate) {
                    $codes = array_map('trim', explode(',', $candidate['postalCodes']));
                    self::assertNotEmpty($codes);
                    foreach ($codes as $code) {
                        self::assertStringStartsWith($prefix, $code, $candidate['name']);
                    }
                }
            }
            $results = $catalog->search('66300', 50);
            self::assertCount(18, $results);
            foreach ($results as $city) {
                self::assertSame('66300', $city['postalCodes'], $city['name']);
            }
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
