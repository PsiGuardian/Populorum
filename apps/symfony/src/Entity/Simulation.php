<?php
declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name:'simulations')]
class Simulation {
 #[ORM\Id, ORM\Column(type:'string',length:64)] public string $id;
 #[ORM\Column(type:'integer',options:['default'=>0])] public int $revision=0;
 #[ORM\Column(type:'json')] public array $state=[];
 #[ORM\Column(type:'string',length:30)] public string $updatedAt;
}
