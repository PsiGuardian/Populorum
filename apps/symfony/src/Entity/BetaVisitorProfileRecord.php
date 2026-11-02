<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Unprivileged beta profile record. Never a Symfony security user. */
#[ORM\Entity]
#[ORM\Table(name: 'beta_visitor_profiles')]
#[ORM\Index(name: 'beta_profiles_expiry', columns: ['expires_at'])]
final class BetaVisitorProfileRecord
{
    #[ORM\Id]
    #[ORM\Column(name: 'ip_fingerprint', length: 64)]
    public string $ipFingerprint;

    #[ORM\Column(name: 'first_name', length: 80)]
    public string $firstName;

    #[ORM\Column(name: 'last_name', length: 80)]
    public string $lastName;

    #[ORM\Column(type: 'integer')]
    public int $age;

    #[ORM\Column(name: 'commune_code', length: 5)]
    public string $communeCode;

    #[ORM\Column(name: 'created_at', length: 20)]
    public string $createdAt;

    #[ORM\Column(name: 'expires_at', type: 'integer')]
    public int $expiresAt;
}
