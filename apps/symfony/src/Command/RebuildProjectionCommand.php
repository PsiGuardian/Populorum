<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\RelationalProjection;
use App\Service\SimulationEngine;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:projection:rebuild',
    description: 'Reconstruire les tables métier après contrôle des snapshots signés',
)]
final class RebuildProjectionCommand extends Command
{
    public function __construct(
        private Connection $connection,
        private RelationalProjection $projection,
        private SimulationEngine $engine,
    ) {
        parent::__construct();
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $count = 0;
        foreach (
            $this->connection->iterateAssociative(
                'SELECT id, state FROM simulations',
            ) as $row
        ) {
            $state = json_decode($row['state'], true, 512, JSON_THROW_ON_ERROR);
            if (!$this->engine->verify($state)['valid']) {
                throw new \RuntimeException('Snapshot divergent : reconstruction suspendue.');
            }
            $this->connection->transactional(
                fn () => $this->projection->write($row['id'], $state),
            );
            ++$count;
        }
        $output->writeln(
            $count .
                ' simulations contrôlées et projetées sans modifier leur historique.',
        );

        return Command::SUCCESS;
    }
}
