<?php

declare(strict_types=1);

namespace App\Service;

/** Optional request-local counters: durations only, never payloads, identities or URLs. */
final class PerformanceTrace
{
    private static bool $enabled = false;
    private static array $durations = [];
    private static int $start = 0;

    public static function begin(): void
    {
        self::$enabled = ($_SERVER['POPULORUM_TIMING'] ?? '') === '1';
        self::$durations = [];
        self::$start = hrtime(true);
    }

    public static function measure(string $name, callable $operation): mixed
    {
        if (!self::$enabled) {
            return $operation();
        }
        $start = hrtime(true);
        try {
            return $operation();
        } finally {
            self::$durations[$name] ??= ['ms' => 0.0, 'calls' => 0];
            self::$durations[$name]['ms'] += (hrtime(true) - $start) / 1e6;
            ++self::$durations[$name]['calls'];
        }
    }

    public static function report(): array
    {
        return self::$enabled
            ? self::$durations + [
                'request' => [
                    'ms' => (hrtime(true) - self::$start) / 1e6,
                    'calls' => 1,
                ],
            ]
            : [];
    }

    public static function record(string $name, float $milliseconds): void
    {
        if (self::$enabled) {
            self::$durations[$name] = ['ms' => $milliseconds, 'calls' => 1];
        }
    }
}
