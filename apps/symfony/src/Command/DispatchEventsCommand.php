<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\AuditEventRecorded;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:events:dispatch',
    description: 'Transmet l’outbox transactionnelle à Messenger, sans charge privée',
)]
final class DispatchEventsCommand extends Command
{
    public function __construct(
        private Connection $connection,
        private MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM audit_outbox WHERE dispatched = 0 LIMIT 1000',
        );
        foreach ($rows as $row) {
            $this->bus->dispatch(
                new AuditEventRecorded($row['scenario_id'], $row['event_id']),
            );
            $this->connection->update(
                'audit_outbox',
                ['dispatched' => 1],
                ['event_id' => $row['event_id']],
            );
        }
        $output->writeln(
            count($rows) . ' événements transmis ; reprises idempotentes.',
        );

        return Command::SUCCESS;
    }
}
