<?php

namespace App\Controller;

use App\Entity\Site;
use App\Repository\SiteRepository;
use App\Service\WebserverService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/sites')]
class SiteController extends AbstractController
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
        private readonly EntityManagerInterface $em,
        private readonly WebserverService $webserverService,
    ) {
    }

    #[Route('', name: 'app_sites_index')]
    public function index(): Response
    {
        $sites = $this->siteRepository->findBy(['user' => $this->getUser()]);

        return $this->render('site/index.html.twig', ['sites' => $sites]);
    }

    #[Route('/create', name: 'app_sites_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $domain = trim($request->request->get('domain', ''));
            $phpVersion = $request->request->get('php_version', '8.3');

            if (!$domain) {
                $this->addFlash('error', 'Domain is required.');
                return $this->render('site/create.html.twig');
            }

            if ($this->siteRepository->findOneBy(['domain' => $domain])) {
                $this->addFlash('error', 'Domain already exists.');
                return $this->render('site/create.html.twig');
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
            } catch (\Exception $e) {
                $this->addFlash('warning', 'Site created but vhost config could not be generated: ' . $e->getMessage());
            }

            $this->addFlash('success', 'Site created successfully.');
            return $this->redirectToRoute('app_sites_index');
        }

        return $this->render('site/create.html.twig');
    }

    #[Route('/{id}/delete', name: 'app_sites_delete', methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $site = $this->siteRepository->find($id);

        if (!$site || $site->getUser() !== $this->getUser()) {
            throw $this->createNotFoundException();
        }

        if ($this->isCsrfTokenValid('delete_site_' . $id, $request->request->get('_token'))) {
            try {
                $this->webserverService->deleteVhost($site->getDomain());
            } catch (\Exception $e) {
                $this->addFlash('warning', 'Vhost could not be removed: ' . $e->getMessage());
            }

            $this->em->remove($site);
            $this->em->flush();
            $this->addFlash('success', 'Site deleted.');
        }

        return $this->redirectToRoute('app_sites_index');
    }
}
