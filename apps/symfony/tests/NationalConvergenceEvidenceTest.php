<?php

declare(strict_types=1);

use App\Service\NationalConvergenceEvidence;
use PHPUnit\Framework\TestCase;

final class NationalConvergenceEvidenceTest extends TestCase
{
    public function testNonPublishedIsNeverRepresentedAsZero(): void
    {
        $root = sys_get_temp_dir() . '/populorum-ssmsi-' . bin2hex(random_bytes(8));
        mkdir($root . '/data/research', 0700, true);
        $path = $root . '/data/research/ssmsi-national-2025.sqlite';
        try {
            $db = new PDO('sqlite:' . $path);
            $db->exec("
                CREATE TABLE metadata (key TEXT PRIMARY KEY, value TEXT NOT NULL);
                CREATE TABLE facts (
                    code TEXT NOT NULL, year INTEGER NOT NULL,
                    indicator TEXT NOT NULL, status TEXT NOT NULL,
                    rate REAL, count INTEGER, count_unit TEXT NOT NULL,
                    population_vintage TEXT, housing_vintage TEXT,
                    PRIMARY KEY (code, year, indicator)
                );
                INSERT INTO metadata VALUES ('schema','populorum-ssmsi-national-1');
                INSERT INTO metadata VALUES ('communes_with_published_2025','1');
                INSERT INTO metadata VALUES ('indexed_rows','3');
                INSERT INTO facts VALUES ('66134',2024,'Indicateur','ndiff',NULL,NULL,'Victime',NULL,NULL);
                INSERT INTO facts VALUES ('66134',2025,'Indicateur','diff',0.0,0,'Victime','2023','2022');
                INSERT INTO facts VALUES ('75056',2025,'Indicateur','diff',3.5,25,'Victime','2023','2022');
            ");
            $db = null;
            $evidence = new NationalConvergenceEvidence($root);
            self::assertTrue($evidence->coverage()['available']);
            self::assertSame(1, $evidence->coverage()['publishedCommunes']);
            self::assertSame(['Indicateur'], $evidence->indicators());
            $rows = $evidence->publishedForPage(['66134', '75056', 'invalid'], 'Indicateur');
            self::assertSame(0.0, $rows['66134']['rate']);
            self::assertSame(0, $rows['66134']['count']);
            self::assertSame(3.5, $rows['75056']['rate']);
            $years = $evidence->city('66134', 'Indicateur');
            self::assertNull($years[2024]['rate']);
            self::assertNull($years[2024]['count']);
            self::assertSame('ndiff', $years[2024]['status']);
            self::assertSame(0.0, $years[2025]['rate']);
            self::assertSame([], $evidence->city('99999', 'Indicateur'));
        } finally {
            @unlink($path);
            @rmdir($root . '/data/research');
            @rmdir($root . '/data');
            @rmdir($root);
        }
    }
}
