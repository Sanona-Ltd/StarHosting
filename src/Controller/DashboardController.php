<?php

namespace App\Controller;

use App\Repository\MailAccountRepository;
use App\Repository\SiteRepository;
use App\Service\SystemStatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    public function __construct(
        private readonly SystemStatsService $systemStats,
        private readonly SiteRepository $siteRepository,
        private readonly MailAccountRepository $mailAccountRepository,
    ) {
    }

    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(): Response
    {
        $user = $this->getUser();
        $stats = $this->systemStats->getStats();
        $sitesCount = $this->siteRepository->count(['user' => $user]);
        $mailCount = $this->mailAccountRepository->countByUser($user);

        return $this->render('dashboard/index.html.twig', [
            'stats' => $stats,
            'sites_count' => $sitesCount,
            'mail_count' => $mailCount,
            'webserver' => $_ENV['PANEL_WEBSERVER'] ?? 'nginx',
            'database' => $_ENV['PANEL_DATABASE'] ?? 'mariadb',
        ]);
    }
}
