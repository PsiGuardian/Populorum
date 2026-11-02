<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\GeographyReferenceInstaller;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:geography:install',
    description: 'Installer ou vérifier le référentiel national INSEE 2026 et les codes postaux La Poste',
)]
final class GeographyInstallCommand extends Command
{
    public function __construct(private readonly GeographyReferenceInstaller $installer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('check', null, InputOption::VALUE_NONE, 'Vérifier le référentiel existant sans le modifier.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->installer->install((bool) $input->getOption('check'));
        $status = $result['installed'] ? 'installé' : 'vérifié (conservé sans modification)';
        $output->writeln(sprintf(
            'Référentiel INSEE 2026 %s : %d communes, %d correspondances postales.',
            $status,
            $result['communes'],
            $result['postals'],
        ));

        return Command::SUCCESS;
    }
}
