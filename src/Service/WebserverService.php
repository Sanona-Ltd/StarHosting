<?php

namespace App\Service;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class WebserverService
{
    private string $webserver;
    private string $webroot;

    public function __construct()
    {
        $this->webserver = $_ENV['PANEL_WEBSERVER'] ?? 'nginx';
        $this->webroot = '/var/www';
    }

    public function createVhost(string $domain, string $phpVersion): void
    {
        $webrootPath = $this->webroot . '/' . $domain . '/public_html';

        if (!is_dir($webrootPath)) {
            mkdir($webrootPath, 0755, true);
        }

        $config = $this->generateVhostConfig($domain, $phpVersion, $webrootPath);

        match ($this->webserver) {
            'nginx' => $this->writeNginxConfig($domain, $config),
            'apache' => $this->writeApacheConfig($domain, $config),
            default => throw new \RuntimeException('Unsupported webserver: ' . $this->webserver),
        };

        $this->reload();
    }

    public function deleteVhost(string $domain): void
    {
        match ($this->webserver) {
            'nginx' => $this->removeNginxConfig($domain),
            'apache' => $this->removeApacheConfig($domain),
            default => null,
        };

        $webrootPath = $this->webroot . '/' . $domain;
        if (is_dir($webrootPath)) {
            $this->runProcess(['rm', '-rf', $webrootPath]);
        }

        $this->reload();
    }

    private function generateVhostConfig(string $domain, string $phpVersion, string $webrootPath): string
    {
        if ($this->webserver === 'nginx') {
            return <<<CONF
server {
    listen 80;
    server_name {$domain} www.{$domain};
    root {$webrootPath};
    index index.php index.html;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php{$phpVersion}-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
    }
}
CONF;
        }

        return <<<CONF
<VirtualHost *:80>
    ServerName {$domain}
    ServerAlias www.{$domain}
    DocumentRoot {$webrootPath}

    <Directory {$webrootPath}>
        AllowOverride All
        Require all granted
    </Directory>

    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php{$phpVersion}-fpm.sock|fcgi://localhost/"
    </FilesMatch>
</VirtualHost>
CONF;
    }

    private function writeNginxConfig(string $domain, string $config): void
    {
        $path = '/etc/nginx/sites-available/' . $domain . '.conf';
        $link = '/etc/nginx/sites-enabled/' . $domain . '.conf';

        file_put_contents($path, $config);
        if (!file_exists($link)) {
            symlink($path, $link);
        }
    }

    private function removeNginxConfig(string $domain): void
    {
        $path = '/etc/nginx/sites-available/' . $domain . '.conf';
        $link = '/etc/nginx/sites-enabled/' . $domain . '.conf';

        if (file_exists($link)) {
            unlink($link);
        }
        if (file_exists($path)) {
            unlink($path);
        }
    }

    private function writeApacheConfig(string $domain, string $config): void
    {
        $path = '/etc/apache2/sites-available/' . $domain . '.conf';
        file_put_contents($path, $config);
        $this->runProcess(['a2ensite', $domain . '.conf']);
    }

    private function removeApacheConfig(string $domain): void
    {
        $path = '/etc/apache2/sites-available/' . $domain . '.conf';
        $this->runProcess(['a2dissite', $domain . '.conf']);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    private function reload(): void
    {
        match ($this->webserver) {
            'nginx' => $this->runProcess(['systemctl', 'reload', 'nginx']),
            'apache' => $this->runProcess(['systemctl', 'reload', 'apache2']),
            default => null,
        };
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
