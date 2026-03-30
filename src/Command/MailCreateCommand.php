<?php

namespace App\Command;

use App\Entity\MailAccount;
use App\Repository\MailDomainRepository;
use App\Service\MailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:mail:create', description: 'Create a mail account')]
class MailCreateCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MailDomainRepository $mailDomainRepository,
        private readonly MailService $mailService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'The email address')
            ->addArgument('password', InputArgument::REQUIRED, 'The password');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');

        [, $domain] = explode('@', $email, 2);

        $mailDomain = $this->mailDomainRepository->findOneBy(['domain' => $domain]);
        if (!$mailDomain) {
            $io->error('Mail domain "' . $domain . '" not found.');
            return Command::FAILURE;
        }

        $account = new MailAccount();
        $account->setEmail($email)
            ->setPasswordHash(password_hash($password, PASSWORD_BCRYPT))
            ->setQuota(1024)
            ->setMailDomain($mailDomain);

        $this->em->persist($account);
        $this->em->flush();

        try {
            $this->mailService->createMailbox($email, $password);
            $io->success('Mail account created.');
        } catch (\Exception $e) {
            $io->warning('Account saved but mailbox creation failed: ' . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}
