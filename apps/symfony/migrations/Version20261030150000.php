<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Projection de lecture légère et reconstructible ; snapshots et historiques conservés';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE simulations ADD COLUMN page_state CLOB DEFAULT NULL',
        );
        $this->addSql(
            'CREATE TRIGGER simulations_invalidate_page_view AFTER UPDATE OF state ON simulations WHEN NEW.state <> OLD.state AND NEW.page_state IS OLD.page_state BEGIN UPDATE simulations SET page_state = NULL WHERE id = NEW.id; END',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER simulations_invalidate_page_view');
        $this->addSql('ALTER TABLE simulations DROP COLUMN page_state');
    }
}
