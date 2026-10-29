<?php
declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name:'municipalities')]
class Municipality {
 #[ORM\Id, ORM\Column(type:'string',length:5)] public string $code;
 #[ORM\Column(type:'string',length:80)] public string $name;
 #[ORM\Column(type:'string',length:80)] public string $region;
 #[ORM\Column(type:'string',length:80)] public string $department;
 #[ORM\Column(type:'integer')] public int $vintage=2026;
 #[ORM\Column(type:'string',length:255)] public string $sourceUrl;
 public function __construct(string $code,string $name,string $region,string $department,string $sourceUrl){$this->code=$code;$this->name=$name;$this->region=$region;$this->department=$department;$this->sourceUrl=$sourceUrl;}
}
