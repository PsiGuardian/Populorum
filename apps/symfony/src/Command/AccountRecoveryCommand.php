<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\CivicAccount;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:citizens:recover',
    description: 'Révoquer un compte fictif ou réinitialiser son secret depuis une entrée privée',
)]
final class AccountRecoveryCommand extends Command
{
    public function __construct(
        private Connection $connection,
        private UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('username', InputArgument::REQUIRED)
            ->addOption('revoke', null, InputOption::VALUE_NONE)
            ->addOption('password-stdin', null, InputOption::VALUE_NONE);
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $username = $input->getArgument('username');
        if (
            !$this->connection->fetchOne(
                'SELECT 1 FROM civic_accounts WHERE username = ?',
                [$username],
            )
        ) {
            throw new \RuntimeException('Compte introuvable.');
        }
        if ($input->getOption('revoke')) {
            $changes = ['enabled' => 0];
        } elseif ($input->getOption('password-stdin')) {
            $password = rtrim(stream_get_contents(STDIN), "\r\n");
            if (strlen($password) < 16 || strlen($password) > 128) {
                throw new \RuntimeException('Le secret doit contenir entre 16 et 128 caractères.');
            }
            $account = new CivicAccount();
            $changes = [
                'password_hash' => $this->hasher->hashPassword(
                    $account,
                    $password,
                ),
                'enabled' => 1,
            ];
        } else {
            throw new \RuntimeException('--revoke ou --password-stdin est requis ; aucun secret dans les arguments.');
        }
        $this->connection->update('civic_accounts', $changes, [
            'username' => $username,
        ]);
        $output->writeln(
            'Compte mis à jour ; les sessions sont revérifiées au prochain accès.',
        );

        return Command::SUCCESS;
    }
}
