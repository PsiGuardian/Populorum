<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\BackupService;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:backup',
    description: 'Créer ou restaurer une sauvegarde chiffrée dans un nouveau fichier',
)]
final class BackupCommand extends Command
{
    public function __construct(
        private BackupService $backup,
        private Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'operation',
            InputArgument::REQUIRED,
            'create ou restore',
        )
            ->addArgument(
                'file',
                InputArgument::REQUIRED,
                'Fichier de sauvegarde chiffré',
            )
            ->addOption(
                'key-file',
                null,
                InputOption::VALUE_REQUIRED,
                'Clé binaire privée ; créée pour une nouvelle sauvegarde si absente',
            )
            ->addOption(
                'destination',
                null,
                InputOption::VALUE_REQUIRED,
                'Nouveau fichier SQLite pour la restauration',
            );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $operation = $input->getArgument('operation');
        $keyPath = $input->getOption('key-file');
        if (!$keyPath || !in_array($operation, ['create', 'restore'], true)) {
            throw new \RuntimeException('Précisez create/restore et --key-file hors du dossier public.');
        }
        if ($operation === 'create' && !file_exists($keyPath)) {
            $file = fopen($keyPath, 'xb');
            chmod($keyPath, 0600);
            fwrite($file, random_bytes(32));
            fclose($file);
        }
        $key = file_get_contents($keyPath);
        $backupPath = $input->getArgument('file');
        if ($operation === 'create' && is_dir($backupPath)) {
            $backupPath =
                rtrim($backupPath, '/') .
                '/backup-' .
                gmdate('Ymd-His') .
                '-' .
                bin2hex(random_bytes(4)) .
                '.json';
        }
        $result =
            $operation === 'create'
                ? $this->backup->create($this->connection, $backupPath, $key)
                : $this->backup->restore(
                    $input->getArgument('file'),
                    $input->getOption('destination') ?:
                    throw new \RuntimeException('--destination est requis.'),
                    $key,
                );
        $output->writeln(
            json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );

        return Command::SUCCESS;
    }
}
