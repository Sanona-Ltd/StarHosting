<?php

namespace App\Controller;

use App\Entity\MailAccount;
use App\Entity\MailDomain;
use App\Repository\MailAccountRepository;
use App\Repository\MailDomainRepository;
use App\Service\MailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/mail')]
class MailController extends AbstractController
{
    public function __construct(
        private readonly MailDomainRepository $mailDomainRepository,
        private readonly MailAccountRepository $mailAccountRepository,
        private readonly EntityManagerInterface $em,
        private readonly MailService $mailService,
    ) {
    }

    #[Route('', name: 'app_mail_index')]
    public function index(): Response
    {
        $domains = $this->mailDomainRepository->findBy(['user' => $this->getUser()]);

        return $this->render('mail/index.html.twig', ['domains' => $domains]);
    }

    #[Route('/domain/add', name: 'app_mail_domain_add', methods: ['POST'])]
    public function addDomain(Request $request): Response
    {
        $domain = trim($request->request->get('domain', ''));

        if (!$domain) {
            $this->addFlash('error', 'Domain is required.');
            return $this->redirectToRoute('app_mail_index');
        }

        if ($this->mailDomainRepository->findOneBy(['domain' => $domain])) {
            $this->addFlash('error', 'Mail domain already exists.');
            return $this->redirectToRoute('app_mail_index');
        }

        $mailDomain = new MailDomain();
        $mailDomain->setDomain($domain)->setUser($this->getUser());
        $this->em->persist($mailDomain);
        $this->em->flush();

        $this->addFlash('success', 'Mail domain added.');
        return $this->redirectToRoute('app_mail_index');
    }

    #[Route('/account/create', name: 'app_mail_account_create', methods: ['GET', 'POST'])]
    public function createAccount(Request $request): Response
    {
        $domains = $this->mailDomainRepository->findBy(['user' => $this->getUser()]);

        if ($request->isMethod('POST')) {
            $email = trim($request->request->get('email', ''));
            $password = $request->request->get('password', '');
            $quota = (int) $request->request->get('quota', 1024);
            $domainId = (int) $request->request->get('domain_id', 0);

            $mailDomain = $this->mailDomainRepository->find($domainId);

            if (!$mailDomain || $mailDomain->getUser() !== $this->getUser()) {
                $this->addFlash('error', 'Invalid domain.');
                return $this->render('mail/create.html.twig', ['domains' => $domains]);
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
            } catch (\Exception $e) {
                $this->addFlash('warning', 'Account saved but mailbox could not be created: ' . $e->getMessage());
            }

            $this->addFlash('success', 'Mail account created.');
            return $this->redirectToRoute('app_mail_index');
        }

        return $this->render('mail/create.html.twig', ['domains' => $domains]);
    }

    #[Route('/account/{id}/delete', name: 'app_mail_account_delete', methods: ['POST'])]
    public function deleteAccount(int $id, Request $request): Response
    {
        $account = $this->mailAccountRepository->find($id);

        if (!$account || $account->getMailDomain()->getUser() !== $this->getUser()) {
            throw $this->createNotFoundException();
        }

        if ($this->isCsrfTokenValid('delete_mail_' . $id, $request->request->get('_token'))) {
            try {
                $this->mailService->deleteMailbox($account->getEmail());
            } catch (\Exception $e) {
                $this->addFlash('warning', 'Mailbox could not be removed: ' . $e->getMessage());
            }

            $this->em->remove($account);
            $this->em->flush();
            $this->addFlash('success', 'Mail account deleted.');
        }

        return $this->redirectToRoute('app_mail_index');
    }
}
