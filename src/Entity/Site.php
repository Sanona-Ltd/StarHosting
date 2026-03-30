<?php

namespace App\Entity;

use App\Repository\SiteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SiteRepository::class)]
class Site
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $domain = null;

    #[ORM\Column(length: 10)]
    private ?string $phpVersion = null;

    #[ORM\Column(length: 255)]
    private ?string $webroot = null;

    #[ORM\Column]
    private bool $sslEnabled = false;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'sites')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getDomain(): ?string { return $this->domain; }

    public function setDomain(string $domain): static
    {
        $this->domain = $domain;
        return $this;
    }

    public function getPhpVersion(): ?string { return $this->phpVersion; }

    public function setPhpVersion(string $phpVersion): static
    {
        $this->phpVersion = $phpVersion;
        return $this;
    }

    public function getWebroot(): ?string { return $this->webroot; }

    public function setWebroot(string $webroot): static
    {
        $this->webroot = $webroot;
        return $this;
    }

    public function isSslEnabled(): bool { return $this->sslEnabled; }

    public function setSslEnabled(bool $sslEnabled): static
    {
        $this->sslEnabled = $sslEnabled;
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
}
