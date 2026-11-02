<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Restore the bundled 2026 INSEE / La Poste SQLite index without Python.
 *
 * Never overwrite an existing index or touch the Doctrine application database.
 */
final class GeographyReferenceInstaller
{
    private const EXPECTED_SHA256 = '40449a3c1bd346d83f4330b1a6dc6486ecdd6942ff34a01e19c38ce12d247b82';

    public function __construct(private readonly string $projectDir)
    {
    }

    /** @return array{communes: int, postals: int, installed: bool} */
    public function install(bool $check = false): array
    {
        $target = $this->projectDir . '/data/geography/communes-2026.sqlite';
        if (is_file($target)) {
            return $this->inspect($target) + ['installed' => false];
        }
        if (file_exists($target) || is_link($target)) {
            throw new \RuntimeException('Chemin du référentiel INSEE occupé par un fichier non valide.');
        }
        if ($check) {
            throw new \RuntimeException('Référentiel INSEE absent : php bin/console app:geography:install --env=demo');
        }

        $archive = $target . '.gz';
        if (!is_file($archive)) {
            throw new \RuntimeException(
                'Archive INSEE manquante : data/geography/communes-2026.sqlite.gz. '
                . 'Réinstallez les sources de Populorum ou récupérez la base SQLite sur GitHub Releases.',
            );
        }

        $temporary = tempnam(dirname($target), '.communes-2026-');
        if ($temporary === false) {
            throw new \RuntimeException('Impossible de créer un fichier temporaire pour le référentiel.');
        }

        try {
            $input = @gzopen($archive, 'rb');
            $output = @fopen($temporary, 'wb');
            if ($input === false || $output === false) {
                if (is_resource($input)) {
                    gzclose($input);
                }
                if (is_resource($output)) {
                    fclose($output);
                }
                throw new \RuntimeException('Impossible de lire l’archive INSEE ou d’écrire le fichier temporaire.');
            }
            try {
                while (!gzeof($input)) {
                    $chunk = gzread($input, 1024 * 1024);
                    if ($chunk === false) {
                        throw new \RuntimeException('Archive INSEE illisible.');
                    }
                    $length = strlen($chunk);
                    $written = 0;
                    while ($written < $length) {
                        $n = fwrite($output, substr($chunk, $written));
                        if ($n === false || $n === 0) {
                            throw new \RuntimeException('Écriture du référentiel INSEE interrompue.');
                        }
                        $written += $n;
                    }
                }
                if (!fflush($output)) {
                    throw new \RuntimeException('Impossible de synchroniser le référentiel INSEE.');
                }
            } finally {
                gzclose($input);
                fclose($output);
            }

            if (!hash_equals(self::EXPECTED_SHA256, (string) hash_file('sha256', $temporary))) {
                throw new \RuntimeException('Empreinte SHA-256 du référentiel INSEE incorrecte.');
            }
            $counts = $this->inspect($temporary);
            chmod($temporary, 0644);
            if (!@link($temporary, $target)) {
                throw new \RuntimeException('Installation impossible : un référentiel existe déjà ou le dossier est protégé.');
            }

            return $counts + ['installed' => true];
        } finally {
            @unlink($temporary);
        }
    }

    /** @return array{communes: int, postals: int} */
    private function inspect(string $path): array
    {
        try {
            $db = new \PDO('sqlite:file:' . $path . '?mode=ro', null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
            $db->exec('PRAGMA query_only=ON');
            if ($db->query('PRAGMA quick_check')->fetchColumn() !== 'ok') {
                throw new \RuntimeException('Référentiel INSEE : intégrité SQLite incorrecte.');
            }
            $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(\PDO::FETCH_COLUMN);
            foreach (['metadata', 'communes', 'postal_codes'] as $table) {
                if (!in_array($table, $tables, true)) {
                    throw new \RuntimeException('Référentiel INSEE incomplet : table ' . $table . ' absente.');
                }
            }
            $vintage = $db->query("SELECT value FROM metadata WHERE key='vintage'")->fetchColumn();
            $communes = (int) $db->query('SELECT COUNT(*) FROM communes')->fetchColumn();
            $postals = (int) $db->query('SELECT COUNT(*) FROM postal_codes')->fetchColumn();
            if ($vintage !== '2026' || $communes < 34000 || $postals < 30000) {
                throw new \RuntimeException('Référentiel national incompatible ou incomplet (INSEE/La Poste 2026).');
            }

            return ['communes' => $communes, 'postals' => $postals];
        } catch (\PDOException $error) {
            throw new \RuntimeException('Impossible de contrôler le référentiel INSEE : ' . $error->getMessage(), 0, $error);
        }
    }
}
