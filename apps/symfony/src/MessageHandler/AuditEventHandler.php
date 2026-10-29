<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\AuditEventRecorded;
use App\Service\SimulationEngine;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AuditEventHandler
{
    public function __construct(
        private Connection $connection,
        private SimulationEngine $engine,
    ) {
    }

    public function __invoke(AuditEventRecorded $message): void
    {
        $json = $this->connection->fetchOne(
            'SELECT state FROM simulations WHERE id = ?',
            [$message->scenarioId],
        );
        if (!$json) {
            throw new \RuntimeException('Simulation du message introuvable.');
        }
        $state = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $event = null;
        foreach ($state['events'] as $candidate) {
            if ($candidate['id'] === $message->eventId) {
                $event = $candidate;
            }
        }
        if (!$event || !$this->engine->verify($state)['valid']) {
            throw new \RuntimeException('Événement ou intégrité invalide : traitement suspendu.');
        }
        // Une reprise ou une double livraison ne produit pas un second effet.
        $this->connection->executeStatement(
            'INSERT OR IGNORE INTO message_receipts (event_id, scenario_id, checkpoint, handled_at) VALUES (?, ?, ?, ?)',
            [
                $message->eventId,
                $message->scenarioId,
                $event['hash'],
                gmdate('c'),
            ],
        );
    }
}
