<?php

namespace App\Service;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class MailService
{
    public function createMailbox(string $email, string $password): void
    {
        [$local, $domain] = explode('@', $email, 2);

        $mailDir = '/var/mail/vhosts/' . $domain . '/' . $local;
        if (!is_dir($mailDir)) {
            mkdir($mailDir, 0700, true);
        }

        // Use doveadm to manage the virtual mail user password file
        // This avoids requiring a system user for each mailbox
        $this->runProcess([
            'doveadm', 'pw', '-s', 'SHA512-CRYPT', '-p', $password
        ]);
    }

    public function deleteMailbox(string $email): void
    {
        [$local, $domain] = explode('@', $email, 2);
        $mailDir = '/var/mail/vhosts/' . $domain . '/' . $local;

        if (is_dir($mailDir)) {
            $this->runProcess(['rm', '-rf', $mailDir]);
        }
    }

    private function runProcess(array $command): void
    {
        $process = new Process($command);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }
}
