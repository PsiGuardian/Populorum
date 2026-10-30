<?php

declare(strict_types=1);

namespace App\Service;

/** Public fictional accounts with append-only contributions, independent of voting or guilt. */
final class CausalAccounts
{
    private function text(
        array $payload,
        string $key,
        int $maximum = 3000,
        bool $required = true,
    ): string {
        $value = $payload[$key] ?? '';
        if (
            !is_string($value)
            || !mb_check_encoding($value, 'UTF-8')
            || mb_strlen($value) > $maximum
            || ($required && mb_strlen(trim($value)) < 2)
        ) {
            throw new \DomainException('Champ invalide : ' . $key, 400);
        }

        return trim($value);
    }

    private function entry(array $payload, int $version): array
    {
        if (($payload['fictionalConsent'] ?? false) !== true) {
            throw new \DomainException('Confirmez le caractère fictif du récit et sa visibilité publique.', 400);
        }
        $year = $this->text($payload, 'year', 4, false);
        $date = $this->text($payload, 'eventDate', 10, false);
        $time = $this->text($payload, 'eventTime', 5, false);
        if (
            $year !== ''
            && (!preg_match('/^\d{4}$/D', $year)
                || (int) $year < 1
                || (int) $year > (int) (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')))->format('Y'))
        ) {
            throw new \DomainException('Année invalide ou future.', 400);
        }
        if ($date !== '') {
            $parsed = \DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $date,
                new \DateTimeZone('Europe/Paris'),
            );
            if (
                !$parsed
                || $parsed->format('Y-m-d') !== $date
                || $date > (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')))->format('Y-m-d')
                || ($year !== '' && substr($date, 0, 4) !== $year)
            ) {
                throw new \DomainException('Date impossible, future ou incompatible avec l’année.', 400);
            }
        }
        if (
            $time !== ''
            && ($date === ''
                || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $time))
        ) {
            throw new \DomainException('Une heure valide HH:MM nécessite une date.', 400);
        }
        $nature = $payload['nature'] ?? 'hypothesis';
        if (
            !in_array(
                $nature,
                ['hypothesis', 'self_report', 'documented'],
                true,
            )
        ) {
            throw new \DomainException('Nature de l’élément inconnue.', 400);
        }
        $source = $this->text($payload, 'source', 1000, false);
        if ($nature === 'documented' && $source === '') {
            throw new \DomainException('Un élément documenté nécessite une source explicite.', 400);
        }
        $progress = $payload['disclosure'] ?? 'partial';
        if (
            !in_array($progress, ['partial', 'additional', 'correction'], true)
        ) {
            throw new \DomainException('Type de complément inconnu.', 400);
        }

        return [
            'version' => $version,
            'recordedAt' => gmdate("Y-m-d\TH:i:s\Z"),
            'year' => $year !== ''
                    ? $year
                    : ($date !== ''
                        ? substr($date, 0, 4)
                        : null),
            'eventDate' => $date !== '' ? $date : null,
            'eventTime' => $time !== '' ? $time : null,
            'timeZone' => 'Europe/Paris',
            'timePrecision' => $time !== ''
                    ? 'minute_declared'
                    : ($date !== ''
                        ? 'day'
                        : ($year !== ''
                            ? 'year'
                            : 'unknown')),
            'nature' => $nature,
            'disclosure' => $progress,
            'cause' => $this->text($payload, 'cause'),
            'consequence' => $this->text($payload, 'consequence'),
            'uncertainty' => $this->text($payload, 'uncertainty'),
            'unknownVariables' => $this->text(
                $payload,
                'unknownVariables',
                3000,
                false,
            ),
            'source' => $source,
            'reason' => $this->text($payload, 'reason'),
        ];
    }

    public function apply(
        array &$state,
        string $action,
        array $payload,
        array $actor,
    ): string {
        $state['causalAccounts'] ??= [];
        foreach ($actor['publicationRestrictions'] ?? [] as $restriction) {
            if (
                !$restriction['revoked']
                && strtotime($restriction['until']) > time()
            ) {
                throw new \DomainException('Publication temporairement restreinte ; les recours restent accessibles.', 403);
            }
        }
        if ($action === 'account.create') {
            if (count($state['causalAccounts']) >= 100) {
                throw new \DomainException('Limite de récits atteinte dans ce laboratoire.', 429);
            }
            $proposalId = $this->text($payload, 'proposalId', 80, false);
            if (
                $proposalId !== ''
                && !in_array(
                    $proposalId,
                    array_column($state['proposals'], 'id'),
                    true,
                )
            ) {
                throw new \DomainException('Chemin lié introuvable.', 400);
            }
            $kind = $payload['kind'] ?? 'experience';
            if (!in_array($kind, ['experience', 'concern'], true)) {
                throw new \DomainException('Type de récit inconnu.', 400);
            }
            $id = 'account_' . bin2hex(random_bytes(10));
            $state['causalAccounts'][] = [
                'id' => $id,
                'authorId' => $actor['id'],
                'territoryId' => $actor['territoryId'],
                'proposalId' => $proposalId,
                'title' => $this->text($payload, 'title', 160),
                'category' => $this->text($payload, 'category', 160),
                'kind' => $kind,
                'fictional' => true,
                'entries' => [$this->entry($payload, 1)],
            ];

            return $id;
        }
        foreach ($state['causalAccounts'] as &$account) {
            if ($account['id'] !== ($payload['accountId'] ?? '')) {
                continue;
            }
            if ($account['authorId'] !== $actor['id']) {
                throw new \DomainException('Seul l’auteur peut compléter son récit.', 403);
            }
            if (count($account['entries']) >= 50) {
                throw new \DomainException('Limite de compléments atteinte pour ce récit.', 429);
            }
            $account['entries'][] = $this->entry(
                $payload,
                count($account['entries']) + 1,
            );

            return $account['id'];
        }
        throw new \DomainException('Récit introuvable.', 404);
    }
}
