<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'vote_records')]
#[ORM\UniqueConstraint(
    name: 'unique_active_vote',
    columns: ['scenario_id', 'consultation_id', 'citizen_id'],
)]
final class VoteRecord extends ObjectRecord
{
    #[ORM\Column(length: 80)]
    public string $consultationId;
    #[ORM\Column(length: 80)]
    public string $citizenId;
}
