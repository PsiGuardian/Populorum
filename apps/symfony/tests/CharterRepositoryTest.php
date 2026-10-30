<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/tools/bootstrap.php';

use App\Service\CharterRepository;
use PHPUnit\Framework\TestCase;

final class CharterRepositoryTest extends TestCase
{
    public function testPackagedDocumentsMatchEveryCanonicalSource(): void
    {
        $repository = new CharterRepository(dirname(__DIR__));
        self::assertCount(28, $repository->locales());
        foreach ($repository->locales() as $locale) {
            $source =
                dirname(__DIR__, 3) .
                ($locale === 'fr_FR'
                    ? '/CHARTER.md'
                    : '/docs/translations/' . $locale . '/CHARTER.md');
            self::assertSame(
                file_get_contents($source),
                $repository->markdown($locale),
                'Exécuter php tools/build-charters.php après modification de ' .
                    $locale,
            );
        }
    }

    public function testDeploymentNeedsOnlyTheSymfonyApplicationData(): void
    {
        $directory =
            sys_get_temp_dir() .
            '/populorum-charters-' .
            bin2hex(random_bytes(8));
        mkdir($directory . '/data', 0700, true);
        $archive = $directory . '/data/charters.json.gz';
        copy(dirname(__DIR__) . '/data/charters.json.gz', $archive);
        try {
            $repository = new CharterRepository($directory);
            self::assertCount(28, $repository->locales());
            self::assertStringContainsString(
                'Article 11',
                $repository->markdown('fr_FR'),
            );
            self::assertSame(
                'fr_FR',
                $repository->resolveLocale('../../etc/passwd'),
            );
            self::assertSame('fr_FR', $repository->resolveLocale('unknown'));
            self::assertSame(
                $repository->markdown('fr_FR'),
                $repository->markdown('unknown'),
            );
        } finally {
            unlink($archive);
            rmdir($directory . '/data');
            rmdir($directory);
        }
    }
}
