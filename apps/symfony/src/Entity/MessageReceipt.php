<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'message_receipts')]
class MessageReceipt
{
    #[ORM\Id, ORM\Column(length: 80)]
    public string $eventId;
    #[ORM\Column(length: 64)]
    public string $scenarioId;
    #[ORM\Column(length: 64)]
    public string $checkpoint;
    #[ORM\Column(length: 30)]
    public string $handledAt;
}
