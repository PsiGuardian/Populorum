<?php

declare(strict_types=1);

namespace App\Message;

/** Le message ne transporte aucune clé ni charge privée de simulation. */
final readonly class AuditEventRecorded
{
    public function __construct(
        public string $scenarioId,
        public string $eventId,
    ) {
    }
}
