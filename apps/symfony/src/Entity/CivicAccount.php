<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/** Compte de démonstration privé ; ni mot de passe ni lien de session dans les exports civiques. */
#[ORM\Entity]
#[ORM\Table(name: 'civic_accounts')]
class CivicAccount implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id, ORM\Column(length: 80)]
    public string $username;
    #[ORM\Column(length: 255)]
    public string $passwordHash;
    #[ORM\Column(length: 64)]
    public string $workspaceId;
    #[ORM\Column(length: 80)]
    public string $citizenId;
    #[ORM\Column(type: 'json')]
    public array $roles = ['ROLE_CITIZEN'];
    #[ORM\Column(type: 'boolean')]
    public bool $enabled = true;

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function eraseCredentials(): void
    {
    }
}
