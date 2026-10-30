<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'proposal_revision_records')]
final class ProposalRevisionRecord extends ObjectRecord
{
}
