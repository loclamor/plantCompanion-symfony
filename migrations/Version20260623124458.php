<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Crée les tables plant_semis et plant_rempotage (module Potager, Phase 3).
 */
final class Version20260623124458 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée les tables plant_semis et plant_rempotage (module Potager, Phase 3).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE plant_rempotage (id INT AUTO_INCREMENT NOT NULL, semis_id INT NOT NULL, utilisateur_id INT NOT NULL, date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', notes VARCHAR(255) DEFAULT NULL, INDEX IDX_6516FCA95EB130B0 (semis_id), INDEX IDX_6516FCA9FB88E14F (utilisateur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE plant_semis (id INT AUTO_INCREMENT NOT NULL, saison_id INT NOT NULL, graine_type_id INT NOT NULL, graine_lot_id INT DEFAULT NULL, utilisateur_id INT NOT NULL, methode VARCHAR(20) NOT NULL, date_semis DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', date_levee DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', date_plantation DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', date_plantation_theorique DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', date_recolte_theorique DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', statut VARCHAR(20) NOT NULL, echec TINYINT(1) NOT NULL, notes LONGTEXT DEFAULT NULL, INDEX IDX_BA4E5BA0F965414C (saison_id), INDEX IDX_BA4E5BA077915F94 (graine_type_id), INDEX IDX_BA4E5BA0C8138A4E (graine_lot_id), INDEX IDX_BA4E5BA0FB88E14F (utilisateur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE plant_rempotage ADD CONSTRAINT FK_6516FCA95EB130B0 FOREIGN KEY (semis_id) REFERENCES plant_semis (id)');
        $this->addSql('ALTER TABLE plant_rempotage ADD CONSTRAINT FK_6516FCA9FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES plant_utilisateur (id)');
        $this->addSql('ALTER TABLE plant_semis ADD CONSTRAINT FK_BA4E5BA0F965414C FOREIGN KEY (saison_id) REFERENCES plant_saison (id)');
        $this->addSql('ALTER TABLE plant_semis ADD CONSTRAINT FK_BA4E5BA077915F94 FOREIGN KEY (graine_type_id) REFERENCES plant_graine_type (id)');
        $this->addSql('ALTER TABLE plant_semis ADD CONSTRAINT FK_BA4E5BA0C8138A4E FOREIGN KEY (graine_lot_id) REFERENCES plant_graine_lot (id)');
        $this->addSql('ALTER TABLE plant_semis ADD CONSTRAINT FK_BA4E5BA0FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES plant_utilisateur (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plant_rempotage DROP FOREIGN KEY FK_6516FCA95EB130B0');
        $this->addSql('ALTER TABLE plant_rempotage DROP FOREIGN KEY FK_6516FCA9FB88E14F');
        $this->addSql('ALTER TABLE plant_semis DROP FOREIGN KEY FK_BA4E5BA0F965414C');
        $this->addSql('ALTER TABLE plant_semis DROP FOREIGN KEY FK_BA4E5BA077915F94');
        $this->addSql('ALTER TABLE plant_semis DROP FOREIGN KEY FK_BA4E5BA0C8138A4E');
        $this->addSql('ALTER TABLE plant_semis DROP FOREIGN KEY FK_BA4E5BA0FB88E14F');
        $this->addSql('DROP TABLE plant_rempotage');
        $this->addSql('DROP TABLE plant_semis');
    }
}
