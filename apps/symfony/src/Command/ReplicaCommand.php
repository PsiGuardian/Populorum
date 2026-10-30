<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\DemoDatabase;
use App\Service\ReplicaProtocol;
use App\Service\VoluntaryAgent;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:replica',
    description: 'Publier une copie publique fictive ou synchroniser un agent explicitement autorisé',
)]
final class ReplicaCommand extends Command
{
    public function __construct(
        private DemoDatabase $database,
        private ReplicaProtocol $protocol,
        private VoluntaryAgent $agent,
        private string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'operation',
            InputArgument::REQUIRED,
            'publish ou sync',
        )
            ->addOption(
                'workspace',
                null,
                InputOption::VALUE_REQUIRED,
                'Laboratoire à publier',
                'shared-demo',
            )
            ->addOption(
                'config',
                null,
                InputOption::VALUE_REQUIRED,
                'Fichier privé de consentement et de limites de l’agent',
            );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        if ($input->getArgument('operation') === 'publish') {
            $state = $this->database->workspace($input->getOption('workspace'))[
                'state'
            ];
            $envelope = $this->protocol->publish($state);
            $directory = $this->projectDir . '/var/replicas';
            if (!is_dir($directory)) {
                mkdir($directory, 0700, true);
            }
            $temporary = tempnam($directory, 'publish-');
            file_put_contents(
                $temporary,
                json_encode(
                    $envelope,
                    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                ),
            );
            chmod($temporary, 0600);
            rename($temporary, $directory . '/published.json');
            $output->writeln(
                'Copie publique de simulation publiée. Autorité à épingler : ' .
                    $envelope['authority'],
            );
        } elseif ($input->getArgument('operation') === 'sync') {
            $file =
                $input->getOption('config') ?:
                throw new \RuntimeException('--config est requis.');
            $output->writeln(
                json_encode(
                    $this->agent->synchronize($file),
                    JSON_THROW_ON_ERROR,
                ),
            );
        } else {
            throw new \RuntimeException('Choisissez publish ou sync.');
        }

        return Command::SUCCESS;
    }
}
