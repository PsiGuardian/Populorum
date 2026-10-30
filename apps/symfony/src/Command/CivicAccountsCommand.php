<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\CivicAccount;
use App\Service\DemoDatabase;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:citizens:accounts',
    description: 'Créer les accès privés des personnages d’un laboratoire partagé fictif',
)]
final class CivicAccountsCommand extends Command
{
    public function __construct(
        private DemoDatabase $database,
        private Connection $connection,
        private UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'workspace',
            null,
            InputOption::VALUE_REQUIRED,
            'Identifiant du laboratoire',
            'shared-demo',
        )->addOption(
            'output',
            null,
            InputOption::VALUE_REQUIRED,
            'Nouveau CSV privé contenant les accès ; ne pas publier',
        );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $id = $input->getOption('workspace');
        $path = $input->getOption('output');
        if (
            !preg_match('/^[a-zA-Z0-9_-]{1,40}$/D', $id)
            || !$path
            || file_exists($path)
        ) {
            throw new \RuntimeException('Identifiant sûr et nouveau fichier --output privé requis.');
        }
        $state = $this->database->workspace($id)['state'];
        $file = fopen($path, 'xb');
        chmod($path, 0600);
        fputcsv($file, ['username', 'password', 'citizenId'], escape: '');
        $created = 0;
        foreach ($state['citizens'] as $citizen) {
            $username = $id . '_' . $citizen['id'];
            if (
                $this->connection->fetchOne(
                    'SELECT 1 FROM civic_accounts WHERE username = ?',
                    [$username],
                )
            ) {
                continue;
            }
            $account = new CivicAccount();
            $password = bin2hex(random_bytes(16));
            $account->passwordHash = $this->hasher->hashPassword(
                $account,
                $password,
            );
            $this->connection->insert('civic_accounts', [
                'username' => $username,
                'password_hash' => $account->passwordHash,
                'workspace_id' => $id,
                'citizen_id' => $citizen['id'],
                'roles' => json_encode(['ROLE_CITIZEN']),
                'enabled' => 1,
            ]);
            fputcsv($file, [$username, $password, $citizen['id']], escape: '');
            ++$created;
        }
        fclose($file);
        $output->writeln(
            $created .
                ' accès fictifs créés ; remettre le CSV par un canal privé.',
        );

        return Command::SUCCESS;
    }
}
