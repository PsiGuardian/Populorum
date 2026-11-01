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
