<?php

declare(strict_types=1);

namespace App\Service;

/** Les documents sont embarqués : aucun chemin vers le dépôt parent en production. */
final class CharterRepository
{
    /** @var array<string, string> */
    private array $documents;

    public function __construct(string $projectDir)
    {
        $file = $projectDir . '/data/charters.json.gz';
        $compressed = file_get_contents($file);
        $json = $compressed === false ? false : gzdecode($compressed);
        if ($json === false) {
            throw new \RuntimeException('Archive des chartes absente ou illisible.');
        }
        $documents = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($documents) || !isset($documents['fr_FR'])) {
            throw new \RuntimeException('La charte française de référence est requise.');
        }
        foreach ($documents as $locale => $markdown) {
            if (
                !is_string($locale)
                || !is_string($markdown)
                || trim($markdown) === ''
            ) {
                throw new \RuntimeException('Document de charte invalide.');
            }
        }
        ksort($documents);
        $this->documents = $documents;
    }

    /** @return list<string> */
    public function locales(): array
    {
        return array_keys($this->documents);
    }

    public function resolveLocale(string $locale): string
    {
        return isset($this->documents[$locale]) ? $locale : 'fr_FR';
    }

    public function markdown(string $locale): string
    {
        return $this->documents[$this->resolveLocale($locale)];
    }
}
