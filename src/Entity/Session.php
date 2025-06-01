<?php

namespace App\Entity;

use App\Repository\SessionRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\ChatlogFile;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;

#[ORM\Entity(repositoryClass: SessionRepository::class)]
#[ORM\Table(name: 'session')]
#[ORM\UniqueConstraint(name: 'unique_session', columns: ['date', 'time', 'chatlog_file_id'])]
#[ORM\HasLifecycleCallbacks]
class Session
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'date')]
    private $date;

    #[ORM\Column(type: 'time')]
    private $time;

    #[ORM\Column(type: 'integer')]
    private $totalRolls;

    #[ORM\Column(type: 'float')]
    private $average;

    #[ORM\ManyToOne(targetEntity: ChatlogFile::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $chatlogFile;

    public function getId(): ?int
    {
        return $this->id;
    }
}
