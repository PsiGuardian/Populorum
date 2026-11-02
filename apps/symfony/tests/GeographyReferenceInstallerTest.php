<?php

declare(strict_types=1);

use App\Service\GeographyReferenceInstaller;
use PHPUnit\Framework\TestCase;

final class GeographyReferenceInstallerTest extends TestCase
{
    public function testRestoresAndVerifiesWithoutPythonOrOverwritingExistingIndex(): void
    {
        $root = sys_get_temp_dir() . '/populorum-reference-' . bin2hex(random_bytes(8));
        $directory = $root . '/data/geography';
        self::assertTrue(mkdir($directory, 0700, true));
        $archive = $directory . '/communes-2026.sqlite.gz';
        $database = $directory . '/communes-2026.sqlite';
        try {
            self::assertTrue(copy(dirname(__DIR__) . '/data/geography/communes-2026.sqlite.gz', $archive));
            $installer = new GeographyReferenceInstaller($root);
            try {
                $installer->install(true);
                self::fail('The missing index should fail a check-only request.');
            } catch (RuntimeException $error) {
                self::assertStringContainsString('app:geography:install', $error->getMessage());
            }
            self::assertSame(['communes' => 34875, 'postals' => 35313, 'installed' => true], $installer->install());
            $digest = hash_file('sha256', $database);
            self::assertSame('40449a3c1bd346d83f4330b1a6dc6486ecdd6942ff34a01e19c38ce12d247b82', $digest);
            self::assertSame(['communes' => 34875, 'postals' => 35313, 'installed' => false], $installer->install(true));
            self::assertSame(['communes' => 34875, 'postals' => 35313, 'installed' => false], $installer->install());
            self::assertSame($digest, hash_file('sha256', $database));
        } finally {
            @unlink($archive);
            @unlink($database);
            @rmdir($directory);
            @rmdir($root . '/data');
            @rmdir($root);
        }
    }

    public function testRejectsInvalidArchiveWithoutInstallingData(): void
    {
        $root = sys_get_temp_dir() . '/populorum-reference-' . bin2hex(random_bytes(8));
        $directory = $root . '/data/geography';
        self::assertTrue(mkdir($directory, 0700, true));
        $archive = $directory . '/communes-2026.sqlite.gz';
        $database = $directory . '/communes-2026.sqlite';
        try {
            self::assertNotFalse(file_put_contents($archive, gzencode('incorrect sqlite data')));
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('SHA-256');
            try {
                (new GeographyReferenceInstaller($root))->install();
            } finally {
                self::assertFileDoesNotExist($database);
            }
        } finally {
            @unlink($archive);
            @rmdir($directory);
            @rmdir($root . '/data');
            @rmdir($root);
        }
    }
}
