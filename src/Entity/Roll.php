<?php

namespace App\Entity;

use App\Repository\RollRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Character;
use App\Entity\Session;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;

#[ORM\Entity(repositoryClass: RollRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Roll
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: Character::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $character;

    #[ORM\ManyToOne(targetEntity: Session::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $session;

    #[ORM\Column(type: 'string', length: 32)]
    private $diceType;

    #[ORM\Column(type: 'integer')]
    private $numDice;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $bonus;

    #[ORM\Column(type: 'integer')]
    private $totalValue;

    #[ORM\Column(type: 'integer')]
    private $actualRoll;

    #[ORM\Column(type: 'boolean')]
    private $isAdvantage;

    #[ORM\Column(type: 'boolean')]
    private $isDisadvantage;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $droppedValue;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $skill;

    // getters and setters ...
} 