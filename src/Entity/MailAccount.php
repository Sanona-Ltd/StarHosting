<?php

namespace App\Entity;

use App\Repository\MailAccountRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MailAccountRepository::class)]
class MailAccount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $passwordHash = null;

    #[ORM\Column]
    private int $quota = 1024;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'mailAccounts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?MailDomain $mailDomain = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getEmail(): ?string { return $this->email; }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getPasswordHash(): ?string { return $this->passwordHash; }

    public function setPasswordHash(string $passwordHash): static
    {
        $this->passwordHash = $passwordHash;
        return $this;
    }

    public function getQuota(): int { return $this->quota; }

    public function setQuota(int $quota): static
    {
        $this->quota = $quota;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getMailDomain(): ?MailDomain { return $this->mailDomain; }

    public function setMailDomain(?MailDomain $mailDomain): static
    {
        $this->mailDomain = $mailDomain;
        return $this;
    }
}
