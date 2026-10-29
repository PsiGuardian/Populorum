<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'SQLite, trois communes de référence, simulations isolées et verrouillage optimiste';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE municipalities (code VARCHAR(5) NOT NULL, name VARCHAR(80) NOT NULL, region VARCHAR(80) NOT NULL, department VARCHAR(80) NOT NULL, vintage INTEGER NOT NULL, source_url VARCHAR(255) NOT NULL, PRIMARY KEY(code))',
        );
        $this->addSql(
            'CREATE TABLE simulations (id VARCHAR(64) NOT NULL, revision INTEGER NOT NULL DEFAULT 0, state CLOB NOT NULL, updated_at VARCHAR(30) NOT NULL, PRIMARY KEY(id))',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE simulations');
        $this->addSql('DROP TABLE municipalities');
    }
}
