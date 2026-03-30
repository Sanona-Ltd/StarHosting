<?php

namespace App\Entity;

use App\Repository\MailDomainRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MailDomainRepository::class)]
class MailDomain
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $domain = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'mailDomains')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\OneToMany(targetEntity: MailAccount::class, mappedBy: 'mailDomain', orphanRemoval: true)]
    private Collection $mailAccounts;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->mailAccounts = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getDomain(): ?string { return $this->domain; }

    public function setDomain(string $domain): static
    {
        $this->domain = $domain;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUser(): ?User { return $this->user; }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getMailAccounts(): Collection { return $this->mailAccounts; }
}
