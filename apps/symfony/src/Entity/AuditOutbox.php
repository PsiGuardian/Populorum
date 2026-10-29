<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'audit_outbox')]
class AuditOutbox
{
    #[ORM\Id, ORM\Column(length: 80)]
    public string $eventId;
    #[ORM\Column(length: 64)]
    public string $scenarioId;
    #[ORM\Column(type: 'boolean')]
    public bool $dispatched = false;
}
