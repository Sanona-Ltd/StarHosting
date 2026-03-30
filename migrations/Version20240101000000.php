<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240101000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE "user" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL --(DC2Type:json)
        , password VARCHAR(255) NOT NULL, first_name VARCHAR(100) DEFAULT NULL, last_name VARCHAR(100) DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)');
        $this->addSql('CREATE TABLE site (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, domain VARCHAR(255) NOT NULL, php_version VARCHAR(10) NOT NULL, webroot VARCHAR(255) NOT NULL, ssl_enabled BOOLEAN NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_694309E4A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_694309E4A7A91E0B ON site (domain)');
        $this->addSql('CREATE INDEX IDX_694309E4A76ED395 ON site (user_id)');
        $this->addSql('CREATE TABLE mail_domain (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, domain VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_F7C9B2DBA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F7C9B2DBA7A91E0B ON mail_domain (domain)');
        $this->addSql('CREATE INDEX IDX_F7C9B2DBA76ED395 ON mail_domain (user_id)');
        $this->addSql('CREATE TABLE mail_account (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, mail_domain_id INTEGER NOT NULL, email VARCHAR(255) NOT NULL, password_hash VARCHAR(255) NOT NULL, quota INTEGER NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_D4C97FD7C22A9CC8 FOREIGN KEY (mail_domain_id) REFERENCES mail_domain (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_D4C97FD7C22A9CC8 ON mail_account (mail_domain_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE mail_account');
        $this->addSql('DROP TABLE mail_domain');
        $this->addSql('DROP TABLE site');
        $this->addSql('DROP TABLE "user"');
    }
}
