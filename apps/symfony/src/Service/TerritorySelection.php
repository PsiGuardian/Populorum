<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

/** One geographical selection across the UI; the three fictive pilots remain a separate dataset. */
final class TerritorySelection
{
    public const SESSION_KEY = 'populorum_commune_code';

    public const DEMO_VIEWS = [
        'home', 'territories', 'proposals', 'consultations', 'experiments',
        'convergence', 'memory', 'accounts', 'services', 'procurement',
        'governance', 'infrastructure', 'transparency',
    ];

    public function __construct(private readonly GeographyCatalog $geography)
    {
    }

    /** GET scope exists for backwards-compatible budget links only; everything else uses the session. */
    public function current(Request $request, string $view): ?array
    {
        $session = $request->getSession();
        if ($view === 'budget' && ($request->query->has('scope') || $request->query->has('city'))) {
            // Backward-compatible legacy budget links also update the shared scope.
            $requested = strtoupper(trim($request->query->getString(
                'scope', $request->query->getString('city', ''),
            )));
            if ($requested === 'FR') {
                $session->remove(self::SESSION_KEY);
            } elseif (GeographyCatalog::validCode($requested) && $this->geography->find($requested) !== null) {
                $session->set(self::SESSION_KEY, $requested);
            }
        }
        $code = $session->get(self::SESSION_KEY, '');
        if (!is_string($code) || !GeographyCatalog::validCode($code)) {
            return null;
        }
        $commune = $this->geography->find($code);
        if ($commune === null) {
            $session->remove(self::SESSION_KEY);
        }

        return $commune;
    }

    public function choose(Request $request, string $code): bool
    {
        $code = strtoupper(trim($code));
        $session = $request->getSession();
        if ($code === 'FR') {
            $session->remove(self::SESSION_KEY);

            return true;
        }
        if (!GeographyCatalog::validCode($code) || $this->geography->find($code) === null) {
            return false;
        }
        $session->set(self::SESSION_KEY, $code);
        $recent = $session->get('populorum_recent_communes', []);
        $recent = is_array($recent) ? $recent : [];
        $recent = array_values(array_unique(array_merge([$code], array_filter($recent, 'is_string'))));
        $session->set('populorum_recent_communes', array_slice($recent, 0, 5));

        return true;
    }

    public function recent(Request $request): array
    {
        $codes = $request->getSession()->get('populorum_recent_communes', []);
        $results = [];
        foreach (is_array($codes) ? array_slice($codes, 0, 5) : [] as $code) {
            $commune = is_string($code) ? $this->geography->find($code) : null;
            if ($commune !== null) {
                $results[] = $commune;
            }
        }

        return $results;
    }

    /** Resolve the demo's local ID from the INSEE code, never from list order. */
    public static function pilotId(?array $commune, array $territories): ?string
    {
        if ($commune === null) {
            return null;
        }
        foreach ($territories as $territory) {
            if (($territory['code'] ?? null) === $commune['code']) {
                return (string) $territory['id'];
            }
        }

        return null;
    }

    /** Read-only projection. Never persist a filtered scenario back into Doctrine. */
    public function filterDemo(array $state, string $pilotId): array
    {
        foreach (['territories', 'proposals', 'consultations', 'experiments', 'tenders',
            'workers', 'missions', 'operations', 'finance', 'accounts', 'events'] as $name) {
            if (!is_array($state[$name] ?? null)) {
                continue;
            }
            $state[$name] = array_values(array_filter($state[$name],
                static fn (array $row): bool => !array_key_exists('territoryId', $row)
                    ? ($name !== 'territories' || $row['id'] === $pilotId)
                    : $row['territoryId'] === $pilotId
            ));
        }
        $consultationIds = array_fill_keys(array_column($state['consultations'] ?? [], 'id'), true);
        if (isset($state['votes'])) {
            $state['votes'] = array_values(array_filter($state['votes'],
                static fn (array $vote): bool => isset($consultationIds[$vote['consultationId'] ?? ''])
            ));
        }

        return $state;
    }
}
