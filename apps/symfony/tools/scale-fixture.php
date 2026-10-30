<?php

declare(strict_types=1);

/** Isolated synthetic load data only. Never imported into an existing laboratory. */
function performanceScaleFixture(array $state, int $factor): array
{
    if ($factor < 1 || $factor > 5) {
        throw new InvalidArgumentException('Scale must be between 1 and 5.');
    }
    if ($factor === 1) {
        return $state;
    }
    $collections = ['citizens', 'proposals', 'consultations', 'votes', 'experiments', 'causalAccounts', 'workers'];
    $ids = [];
    $collect = static function (array $data) use (&$collect, &$ids): void {
        foreach ($data as $key => $value) {
            if (($key === 'id' || $key === 'familyId') && is_string($value)) {
                $ids[$value] = true;
            } elseif (is_array($value)) {
                $collect($value);
            }
        }
    };
    foreach ($collections as $collection) {
        $collect($state[$collection] ?? []);
    }
    $original = $state;
    for ($copy = 2; $copy <= $factor; ++$copy) {
        $rewrite = static function (mixed $value) use (&$rewrite, $ids, $copy): mixed {
            if (is_string($value)) {
                return isset($ids[$value]) ? $value . '_load' . $copy : $value;
            }
            if (!is_array($value)) {
                return $value;
            }
            $result = [];
            foreach ($value as $key => $item) {
                $newKey = is_string($key) && isset($ids[$key]) ? $key . '_load' . $copy : $key;
                $result[$newKey] = $rewrite($item);
            }

            return $result;
        };
        foreach ($collections as $collection) {
            foreach ($original[$collection] ?? [] as $item) {
                $item = $rewrite($item);
                if ($collection === 'citizens') {
                    $item['name'] .= ' · charge ' . $copy;
                }
                $state[$collection][] = $item;
            }
        }
    }
    // A newly generated synthetic fixture receives its own audit seal, never a forged old history.
    $state['events'] = [];
    $state['operations'] = [];
    $state['keyPair'] = null;
    $state['preferences']['dataset'] .= '-load-' . $factor;

    return (new App\Service\SimulationEngine())->initializeFixture($state);
}
