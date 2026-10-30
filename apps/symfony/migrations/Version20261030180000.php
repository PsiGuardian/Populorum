<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Lecture des pages sans décoder les bulletins inutilisés ; source complète conservée';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE simulations ADD COLUMN overview_state CLOB DEFAULT NULL');
        $this->addSql('CREATE TRIGGER simulations_invalidate_overview AFTER UPDATE OF state ON simulations WHEN NEW.state <> OLD.state AND NEW.overview_state IS OLD.overview_state BEGIN UPDATE simulations SET overview_state = NULL WHERE id = NEW.id; END');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER simulations_invalidate_overview');
        $this->addSql('ALTER TABLE simulations DROP COLUMN overview_state');
    }
}
