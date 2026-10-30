<?php

declare(strict_types=1);

namespace App\Service;

/** Rebuildable display projection; never an input to a vote, signature, export or mutation. */
final class PageState
{
    public const VERSION = 1;

    public static function encode(array $state, int $revision, bool $includeVotes = true): string
    {
        $state['publicKey'] =
            $state['keyPair']['publicKey'] ?? ($state['publicKey'] ?? null);
        unset($state['keyPair'], $state['operations']);
        foreach ($state['citizens'] as &$citizen) {
            unset($citizen['mindHistory']);
            if (isset($citizen['mind'])) {
                $citizen['mind'] = ['round' => $citizen['mind']['round'] ?? 0];
            }
        }
        unset($citizen);
        if ($includeVotes) {
            foreach ($state['votes'] as &$vote) {
                unset($vote['deliberation']);
                foreach ($vote['history'] as &$entry) {
                    unset($entry['deliberation']);
                }
                unset($entry);
            }
            unset($vote);
        } else {
            unset($state['votes']);
        }

        return json_encode(
            [
                'version' => self::VERSION,
                'revision' => $revision,
                'includesVotes' => $includeVotes,
                'state' => $state,
            ],
            JSON_THROW_ON_ERROR,
        );
    }
}
