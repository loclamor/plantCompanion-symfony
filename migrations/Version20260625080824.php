<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260625080824 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 5 — table plant_culture (plants posés dans les bacs par saison)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE plant_culture (id INT AUTO_INCREMENT NOT NULL, saison_id INT NOT NULL, bac_saison_id INT NOT NULL, graine_type_id INT DEFAULT NULL, semis_id INT DEFAULT NULL, parent_culture_id INT DEFAULT NULL, utilisateur_id INT NOT NULL, name VARCHAR(255) NOT NULL, pos_x INT NOT NULL, pos_y INT NOT NULL, largeur_cases INT NOT NULL, hauteur_cases INT NOT NULL, date_plantation DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', date_recolte_theorique DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', date_fin DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', statut VARCHAR(20) NOT NULL, perenne TINYINT(1) NOT NULL, INDEX IDX_CC205692F965414C (saison_id), INDEX IDX_CC2056924C6971EE (bac_saison_id), INDEX IDX_CC20569277915F94 (graine_type_id), INDEX IDX_CC2056925EB130B0 (semis_id), INDEX IDX_CC205692F64BA0CD (parent_culture_id), INDEX IDX_CC205692FB88E14F (utilisateur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE plant_culture ADD CONSTRAINT FK_CC205692F965414C FOREIGN KEY (saison_id) REFERENCES plant_saison (id)');
        $this->addSql('ALTER TABLE plant_culture ADD CONSTRAINT FK_CC2056924C6971EE FOREIGN KEY (bac_saison_id) REFERENCES plant_bac_saison (id)');
        $this->addSql('ALTER TABLE plant_culture ADD CONSTRAINT FK_CC20569277915F94 FOREIGN KEY (graine_type_id) REFERENCES plant_graine_type (id)');
        $this->addSql('ALTER TABLE plant_culture ADD CONSTRAINT FK_CC2056925EB130B0 FOREIGN KEY (semis_id) REFERENCES plant_semis (id)');
        $this->addSql('ALTER TABLE plant_culture ADD CONSTRAINT FK_CC205692F64BA0CD FOREIGN KEY (parent_culture_id) REFERENCES plant_culture (id)');
        $this->addSql('ALTER TABLE plant_culture ADD CONSTRAINT FK_CC205692FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES plant_utilisateur (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plant_culture DROP FOREIGN KEY FK_CC205692F965414C');
        $this->addSql('ALTER TABLE plant_culture DROP FOREIGN KEY FK_CC2056924C6971EE');
        $this->addSql('ALTER TABLE plant_culture DROP FOREIGN KEY FK_CC20569277915F94');
        $this->addSql('ALTER TABLE plant_culture DROP FOREIGN KEY FK_CC2056925EB130B0');
        $this->addSql('ALTER TABLE plant_culture DROP FOREIGN KEY FK_CC205692F64BA0CD');
        $this->addSql('ALTER TABLE plant_culture DROP FOREIGN KEY FK_CC205692FB88E14F');
        $this->addSql('DROP TABLE plant_culture');
    }
}
