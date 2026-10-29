<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Projections indexables et comptes privés : aucune suppression des simulations existantes. */
final class Version20261029111157 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Projections métier, unicité des voix, comptes privés et outbox transactionnelle';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE appeal_records (scenario_id VARCHAR(64) NOT NULL, object_id VARCHAR(160) NOT NULL, territory_id VARCHAR(80) DEFAULT NULL, parent_id VARCHAR(160) DEFAULT NULL, version INTEGER NOT NULL, data CLOB NOT NULL, PRIMARY KEY (scenario_id, object_id))');
        $this->addSql('CREATE TABLE audit_event_records (scenario_id VARCHAR(64) NOT NULL, object_id VARCHAR(160) NOT NULL, territory_id VARCHAR(80) DEFAULT NULL, parent_id VARCHAR(160) DEFAULT NULL, version INTEGER NOT NULL, data CLOB NOT NULL, PRIMARY KEY (scenario_id, object_id))');
        $this->addSql('CREATE TABLE audit_outbox (event_id VARCHAR(80) NOT NULL, scenario_id VARCHAR(64) NOT NULL, dispatched BOOLEAN NOT NULL, PRIMARY KEY (event_id))');
        $this->addSql('CREATE TABLE causal_edge_records (scenario_id VARCHAR(64) NOT NULL, object_id VARCHAR(160) NOT NULL, territory_id VARCHAR(80) DEFAULT NULL, parent_id VARCHAR(160) DEFAULT NULL, version INTEGER NOT NULL, data CLOB NOT NULL, PRIMARY KEY (scenario_id, object_id))');
        $this->addSql('CREATE TABLE causal_node_records (scenario_id VARCHAR(64) NOT NULL, object_id VARCHAR(160) NOT NULL, territory_id VARCHAR(80) DEFAULT NULL, parent_id VARCHAR(160) DEFAULT NULL, version INTEGER NOT NULL, data CLOB NOT NULL, PRIMARY KEY (scenario_id, object_id))');
        $this->addSql('CREATE TABLE citizen_records (scenario_id VARCHAR(64) NOT NULL, object_id VARCHAR(160) NOT NULL, territory_id VARCHAR(80) DEFAULT NULL, parent_id VARCHAR(160) DEFAULT NULL, version INTEGER NOT NULL, data CLOB NOT NULL, PRIMARY KEY (scenario_id, object_id))');
        $this->addSql('CREATE TABLE civic_accounts (username VARCHAR(80) NOT NULL, password_hash VARCHAR(255) NOT NULL, workspace_id VARCHAR(64) NOT NULL, citizen_id VARCHAR(80) NOT NULL, roles CLOB NOT NULL, enabled BOOLEAN NOT NULL, PRIMARY KEY (username))');
        $this->addSql('CREATE TABLE consultation_records (scenario_id VARCHAR(64) NOT NULL, object_id VARCHAR(160) NOT NULL, territory_id VARCHAR(80) DEFAULT NULL, parent_id VARCHAR(160) DEFAULT NULL, version INTEGER NOT NULL, data CLOB NOT NULL, PRIMARY KEY (scenario_id, object_id))');
        $this->addSql('CREATE TABLE experiment_records (scenario_id VARCHAR(64) NOT NULL, object_id VARCHAR(160) NOT NULL, territory_id VARCHAR(80) DEFAULT NULL, parent_id VARCHAR(160) DEFAULT NULL, version INTEGER NOT NULL, data CLOB NOT NULL, PRIMARY KEY (scenario_id, object_id))');
        $this->addSql('CREATE TABLE finance_records (scenario_id VARCHAR(64) NOT NULL, object_id VARCHAR(160) NOT NULL, territory_id VARCHAR(80) DEFAULT NULL, parent_id VARCHAR(160) DEFAULT NULL, version INTEGER NOT NULL, data CLOB NOT NULL, PRIMARY KEY (scenario_id, object_id))');
        $this->addSql('CREATE TABLE message_receipts (event_id VARCHAR(80) NOT NULL, scenario_id VARCHAR(64) NOT NULL, checkpoint VARCHAR(64) NOT NULL, handled_at VARCHAR(30) NOT NULL, PRIMARY KEY (event_id))');
        $this->addSql('CREATE TABLE moderation_records (scenario_id VARCHAR(64) NOT NULL, object_id VARCHAR(160) NOT NULL, territory_id VARCHAR(80) DEFAULT NULL, parent_id VARCHAR(160) DEFAULT NULL, version INTEGER NOT NULL, data CLOB NOT NULL, PRIMARY KEY (scenario_id, object_id))');
        $this->addSql('CREATE TABLE observation_records (scenario_id VARCHAR(64) NOT NULL, object_id VARCHAR(160) NOT NULL, territory_id VARCHAR(80) DEFAULT NULL, parent_id VARCHAR(160) DEFAULT NULL, version INTEGER NOT NULL, data CLOB NOT NULL, PRIMARY KEY (scenario_id, object_id))');
        $this->addSql('CREATE TABLE proposal_records (scenario_id VARCHAR(64) NOT NULL, object_id VARCHAR(160) NOT NULL, territory_id VARCHAR(80) DEFAULT NULL, parent_id VARCHAR(160) DEFAULT NULL, version INTEGER NOT NULL, data CLOB NOT NULL, PRIMARY KEY (scenario_id, object_id))');
        $this->addSql('CREATE TABLE proposal_revision_records (scenario_id VARCHAR(64) NOT NULL, object_id VARCHAR(160) NOT NULL, territory_id VARCHAR(80) DEFAULT NULL, parent_id VARCHAR(160) DEFAULT NULL, version INTEGER NOT NULL, data CLOB NOT NULL, PRIMARY KEY (scenario_id, object_id))');
        $this->addSql('CREATE TABLE rule_set_records (scenario_id VARCHAR(64) NOT NULL, object_id VARCHAR(160) NOT NULL, territory_id VARCHAR(80) DEFAULT NULL, parent_id VARCHAR(160) DEFAULT NULL, version INTEGER NOT NULL, data CLOB NOT NULL, PRIMARY KEY (scenario_id, object_id))');
        $this->addSql('CREATE TABLE vote_records (scenario_id VARCHAR(64) NOT NULL, object_id VARCHAR(160) NOT NULL, territory_id VARCHAR(80) DEFAULT NULL, parent_id VARCHAR(160) DEFAULT NULL, version INTEGER NOT NULL, data CLOB NOT NULL, consultation_id VARCHAR(80) NOT NULL, citizen_id VARCHAR(80) NOT NULL, PRIMARY KEY (scenario_id, object_id))');
        $this->addSql('CREATE UNIQUE INDEX unique_active_vote ON vote_records (scenario_id, consultation_id, citizen_id)');
    }

    public function down(Schema $schema): void
    {
        // Sauvegarder avant retour arrière : ces tables contiennent des comptes et historiques.
        $this->addSql('DROP TABLE appeal_records');
        $this->addSql('DROP TABLE audit_event_records');
        $this->addSql('DROP TABLE audit_outbox');
        $this->addSql('DROP TABLE causal_edge_records');
        $this->addSql('DROP TABLE causal_node_records');
        $this->addSql('DROP TABLE citizen_records');
        $this->addSql('DROP TABLE civic_accounts');
        $this->addSql('DROP TABLE consultation_records');
        $this->addSql('DROP TABLE experiment_records');
        $this->addSql('DROP TABLE finance_records');
        $this->addSql('DROP TABLE message_receipts');
        $this->addSql('DROP TABLE moderation_records');
        $this->addSql('DROP TABLE observation_records');
        $this->addSql('DROP TABLE proposal_records');
        $this->addSql('DROP TABLE proposal_revision_records');
        $this->addSql('DROP TABLE rule_set_records');
        $this->addSql('DROP TABLE vote_records');
    }
}
