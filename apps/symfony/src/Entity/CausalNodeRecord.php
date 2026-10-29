<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'causal_node_records')]
final class CausalNodeRecord extends ObjectRecord
{
}
