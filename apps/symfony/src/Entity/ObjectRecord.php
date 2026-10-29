<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Projection transactionnelle consultable, scoped par simulation. Le journal signé reste la référence d’intégrité. */
#[ORM\MappedSuperclass]
abstract class ObjectRecord
{
    #[ORM\Id, ORM\Column(length: 64)]
    public string $scenarioId;
    #[ORM\Id, ORM\Column(length: 160)]
    public string $objectId;
    #[ORM\Column(length: 80, nullable: true)]
    public ?string $territoryId = null;
    #[ORM\Column(length: 160, nullable: true)]
    public ?string $parentId = null;
    #[ORM\Column(type: 'integer')]
    public int $version = 1;
    #[ORM\Column(type: 'json')]
    public array $data = [];
}
