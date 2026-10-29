BEGIN TRANSACTION;
CREATE TABLE doctrine_migration_versions (version VARCHAR(191) NOT NULL, executed_at DATETIME DEFAULT NULL, execution_time INTEGER DEFAULT NULL, PRIMARY KEY (version));
INSERT INTO "doctrine_migration_versions" VALUES('DoctrineMigrations\Version20261029000100','2026-10-29 05:02:33',1);
CREATE TABLE municipalities (code VARCHAR(5) NOT NULL, name VARCHAR(80) NOT NULL, region VARCHAR(80) NOT NULL, department VARCHAR(80) NOT NULL, vintage INTEGER NOT NULL, source_url VARCHAR(255) NOT NULL, PRIMARY KEY(code));
INSERT INTO "municipalities" VALUES('75056','Paris','Île-de-France','Paris',2026,'https://www.insee.fr/fr/metadonnees/geographie/commune/75056-paris');
INSERT INTO "municipalities" VALUES('13055','Marseille','Provence-Alpes-Côte d’Azur','Bouches-du-Rhône',2026,'https://www.insee.fr/fr/metadonnees/geographie/commune/13055-marseille');
INSERT INTO "municipalities" VALUES('76351','Le Havre','Normandie','Seine-Maritime',2026,'https://www.insee.fr/fr/metadonnees/geographie/commune/76351-le-havre');
CREATE TABLE simulations (id VARCHAR(64) NOT NULL, revision INTEGER NOT NULL DEFAULT 0, state CLOB NOT NULL, updated_at VARCHAR(30) NOT NULL, PRIMARY KEY(id));
COMMIT;
