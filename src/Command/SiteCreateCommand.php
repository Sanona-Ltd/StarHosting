<?php

namespace App\Command;

use App\Entity\Site;
use App\Repository\SiteRepository;
use App\Repository\UserRepository;
use App\Service\WebserverService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:site:create', description: 'Create a new site')]
class SiteCreateCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SiteRepository $siteRepository,
        private readonly UserRepository $userRepository,
        private readonly WebserverService $webserverService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('domain', InputArgument::REQUIRED, 'The domain name')
            ->addArgument('phpVersion', InputArgument::OPTIONAL, 'PHP version (8.1, 8.2, 8.3)', '8.3');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $domain = $input->getArgument('domain');
        $phpVersion = $input->getArgument('phpVersion');

        if ($this->siteRepository->findOneBy(['domain' => $domain])) {
            $io->error('Domain already exists.');
            return Command::FAILURE;
        }

        $user = $this->userRepository->findOneBy([], ['id' => 'ASC']);
        if (!$user) {
            $io->error('No user found. Run app:setup first.');
            return Command::FAILURE;
        }

        $site = new Site();
        $site->setDomain($domain)
            ->setPhpVersion($phpVersion)
            ->setWebroot('/var/www/' . $domain . '/public_html')
            ->setUser($user);

        $this->em->persist($site);
        $this->em->flush();

        try {
            $this->webserverService->createVhost($domain, $phpVersion);
            $io->success('Site created and vhost configured.');
        } catch (\Exception $e) {
            $io->warning('Site created but vhost config failed: ' . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}
