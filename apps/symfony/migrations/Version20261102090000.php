<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Profils de test facultatifs, reconnaissance IP pseudonymisée sans cookie ni droits';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE beta_visitor_profiles (
                ip_fingerprint VARCHAR(64) NOT NULL PRIMARY KEY,
                first_name VARCHAR(80) NOT NULL,
                last_name VARCHAR(80) NOT NULL,
                age INTEGER NOT NULL,
                commune_code VARCHAR(5) NOT NULL,
                created_at VARCHAR(20) NOT NULL,
                expires_at INTEGER NOT NULL
            )',
        );
        $this->addSql('CREATE INDEX beta_profiles_expiry ON beta_visitor_profiles (expires_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE beta_visitor_profiles');
    }
}
