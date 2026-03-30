<?php

namespace App\Controller;

use App\Entity\MailAccount;
use App\Entity\MailDomain;
use App\Entity\Site;
use App\Repository\MailAccountRepository;
use App\Repository\MailDomainRepository;
use App\Repository\SiteRepository;
use App\Service\MailService;
use App\Service\WebserverService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1')]
class ApiController extends AbstractController
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
        private readonly MailAccountRepository $mailAccountRepository,
        private readonly MailDomainRepository $mailDomainRepository,
        private readonly EntityManagerInterface $em,
        private readonly WebserverService $webserverService,
        private readonly MailService $mailService,
    ) {
    }

    #[Route('/sites', name: 'api_sites_list', methods: ['GET'])]
    public function listSites(): JsonResponse
    {
        $sites = $this->siteRepository->findAll();
        $data = array_map(fn(Site $s) => [
            'id' => $s->getId(),
            'domain' => $s->getDomain(),
            'php_version' => $s->getPhpVersion(),
            'ssl_enabled' => $s->isSslEnabled(),
            'created_at' => $s->getCreatedAt()?->format(\DATE_ATOM),
        ], $sites);

        return $this->json($data);
    }

    #[Route('/sites', name: 'api_sites_create', methods: ['POST'])]
    public function createSite(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $domain = trim($payload['domain'] ?? '');
        $phpVersion = $payload['php_version'] ?? '8.3';

        if (!$domain) {
            return $this->json(['error' => 'domain is required'], 400);
        }

        if ($this->siteRepository->findOneBy(['domain' => $domain])) {
            return $this->json(['error' => 'domain already exists'], 409);
        }

        $site = new Site();
        $site->setDomain($domain)
            ->setPhpVersion($phpVersion)
            ->setWebroot('/var/www/' . $domain . '/public_html')
            ->setUser($this->getUser());

        $this->em->persist($site);
        $this->em->flush();

        try {
            $this->webserverService->createVhost($domain, $phpVersion);
        } catch (\Exception) {
        }

        return $this->json(['id' => $site->getId(), 'domain' => $site->getDomain()], 201);
    }

    #[Route('/mail/accounts', name: 'api_mail_accounts_list', methods: ['GET'])]
    public function listMailAccounts(): JsonResponse
    {
        $accounts = $this->mailAccountRepository->findAll();
        $data = array_map(fn(MailAccount $a) => [
            'id' => $a->getId(),
            'email' => $a->getEmail(),
            'quota' => $a->getQuota(),
            'created_at' => $a->getCreatedAt()?->format(\DATE_ATOM),
        ], $accounts);

        return $this->json($data);
    }

    #[Route('/mail/accounts', name: 'api_mail_accounts_create', methods: ['POST'])]
    public function createMailAccount(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $email = trim($payload['email'] ?? '');
        $password = $payload['password'] ?? '';
        $quota = (int) ($payload['quota'] ?? 1024);
        $domainId = (int) ($payload['domain_id'] ?? 0);

        if (!$email || !$password) {
            return $this->json(['error' => 'email and password are required'], 400);
        }

        $mailDomain = $this->mailDomainRepository->find($domainId);
        if (!$mailDomain) {
            return $this->json(['error' => 'mail domain not found'], 404);
        }

        $account = new MailAccount();
        $account->setEmail($email)
            ->setPasswordHash(password_hash($password, PASSWORD_BCRYPT))
            ->setQuota($quota)
            ->setMailDomain($mailDomain);

        $this->em->persist($account);
        $this->em->flush();

        try {
            $this->mailService->createMailbox($email, $password);
        } catch (\Exception) {
        }

        return $this->json(['id' => $account->getId(), 'email' => $account->getEmail()], 201);
    }
}
