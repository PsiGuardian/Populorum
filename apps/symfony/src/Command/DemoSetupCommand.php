<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\DemoDatabase;
use App\Service\GeographyReferenceInstaller;
use OmegaGuardian\ProfilerShield\Security\Accounts;
use OmegaGuardian\ProfilerShield\Security\Settings;
use OmegaGuardian\ProfilerShield\Storage\FileStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:demo:prepare',
    description: 'Synchronise les communes INSEE 2026 et initialise les comptes de démonstration',
)]
final class DemoSetupCommand extends Command
{
    public const PUBLIC_USERNAME = 'demo';
    public const PUBLIC_PASSWORD = 'Populorum-Demo!2026';

    public function __construct(
        private DemoDatabase $database,
        private GeographyReferenceInstaller $geographyInstaller,
        private FileStore $store,
        private Accounts $accounts,
        private Settings $settings,
    ) {
        parent::__construct();
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        if (
            ($_SERVER['POPULORUM_DEMO'] ?? '') !== '1'
            || !in_array($_SERVER['APP_ENV'] ?? '', ['demo', 'test'], true)
        ) {
            throw new \RuntimeException('Préparation réservée à une instance de démonstration.');
        }
        // Idempotent: restore and verify the bundled national index before Doctrine synchronisation.
        $this->geographyInstaller->install();
        $communeCount = $this->database->importCities();
        $this->store->initialize();
        if (!$this->accounts->find(self::PUBLIC_USERNAME)) {
            $this->accounts->add(
                self::PUBLIC_USERNAME,
                self::PUBLIC_PASSWORD,
                'developer',
                false,
            );
        }
        if (!$this->accounts->find('operator')) {
            $password = bin2hex(random_bytes(24));
            $this->accounts->add('operator', $password, 'admin', false);
            $path = $this->store->root() . '/operator-credentials.txt';
            $credentialsFile = fopen($path, 'x');
            if (!$credentialsFile) {
                throw new \RuntimeException('Écriture des identifiants privés impossible.');
            }
            chmod($path, 0600);
            fwrite(
                $credentialsFile,
                "Identifiant privé : operator\nMot de passe privé : $password\n",
            );
            fclose($credentialsFile);
        }
        if (!is_file($this->store->root() . '/settings.json')) {
            $this->settings->set([
                'timezone' => 'Europe/Paris',
                'error_mode' => 'native',
                'mfa' => false,
                'schedule_enabled' => false,
                'jit_enabled' => false,
            ]);
        }
        $output->writeln(
            "Référentiel national INSEE 2026 : $communeCount communes synchronisées. Comptes de démonstration configurés ; identifiants et clés administrateur privés.",
        );

        return Command::SUCCESS;
    }
}
