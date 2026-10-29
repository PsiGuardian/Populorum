<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\CivicAccount;
use Doctrine\DBAL\Connection;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class CivicAccountProvider implements UserProviderInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM civic_accounts WHERE username = ? AND enabled = 1',
            [$identifier],
        );
        if (!$row) {
            throw new UserNotFoundException('Compte introuvable.');
        }
        $account = new CivicAccount();
        $account->username = $row['username'];
        $account->passwordHash = $row['password_hash'];
        $account->workspaceId = $row['workspace_id'];
        $account->citizenId = $row['citizen_id'];
        $account->roles = json_decode(
            $row['roles'],
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        return $account;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof CivicAccount) {
            throw new UnsupportedUserException();
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === CivicAccount::class;
    }
}
