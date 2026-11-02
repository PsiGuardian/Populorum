<?php

declare(strict_types=1);

namespace App\Service;

/** Les documents sont embarqués : aucun chemin vers le dépôt parent en production. */
final class CharterRepository
{
    /** @var array<string, string> */
    private ?array $documents = null;

    public function __construct(
        private string $projectDir,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire(service: 'cache.app')]
        private readonly ?\Symfony\Contracts\Cache\CacheInterface $publicCache = null,
    ) {
    }

    private function archiveKey(): string
    {
        $path = $this->projectDir . '/data/charters.json.gz';

        return hash('sha256', $path . ':' . filemtime($path) . ':' . filesize($path));
    }

    private function documents(): array
    {
        if ($this->documents !== null) {
            return $this->documents;
        }
        $file = $this->projectDir . '/data/charters.json.gz';
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

        return $this->documents = $documents;
    }

    /** @return list<string> */
    public function locales(): array
    {
        if ($this->publicCache === null) {
            return array_keys($this->documents());
        }

        return $this->publicCache->get('charter_locales_' . $this->archiveKey(), function ($item): array {
            $item->expiresAfter(3600);

            return array_keys($this->documents());
        });
    }

    public function resolveLocale(string $locale): string
    {
        return in_array($locale, $this->locales(), true) ? $locale : 'fr_FR';
    }

    /**
     * Public, versioned Markdown HTML is safe to reuse across visitors.
     * No user-generated text or private data enters this cache.
     */
    public function renderedHtml(string $locale, CharterRenderer $renderer): string
    {
        $locale = $this->resolveLocale($locale);
        if ($this->publicCache === null) {
            return $renderer->render($this->markdown($locale));
        }

        return $this->publicCache->get('charter_html_' . $this->archiveKey() . '_' . $locale, function ($item) use ($locale, $renderer): string {
            $item->expiresAfter(3600);

            return $renderer->render($this->markdown($locale));
        });
    }

    public function markdown(string $locale): string
    {
        return $this->documents()[$this->resolveLocale($locale)];
    }
}
