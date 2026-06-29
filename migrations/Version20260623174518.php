<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 4 (module Potager) : tables des bacs (`plant_bac`) et de leurs snapshots
 * par saison (`plant_bac_saison`).
 */
final class Version20260623174518 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Potager Phase 4 : bacs + snapshots par saison';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE plant_bac (id INT AUTO_INCREMENT NOT NULL, utilisateur_id INT NOT NULL, name VARCHAR(255) NOT NULL, largeur_defaut INT NOT NULL, longueur_defaut INT NOT NULL, lignes_defaut INT NOT NULL, colonnes_defaut INT NOT NULL, archived TINYINT(1) NOT NULL, INDEX IDX_F2E36745FB88E14F (utilisateur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE plant_bac_saison (id INT AUTO_INCREMENT NOT NULL, bac_id INT NOT NULL, saison_id INT NOT NULL, utilisateur_id INT NOT NULL, largeur INT NOT NULL, longueur INT NOT NULL, pos_x INT NOT NULL, pos_y INT NOT NULL, lignes INT NOT NULL, colonnes INT NOT NULL, INDEX IDX_793A341AE03F15C0 (bac_id), INDEX IDX_793A341AF965414C (saison_id), INDEX IDX_793A341AFB88E14F (utilisateur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE plant_bac ADD CONSTRAINT FK_F2E36745FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES plant_utilisateur (id)');
        $this->addSql('ALTER TABLE plant_bac_saison ADD CONSTRAINT FK_793A341AE03F15C0 FOREIGN KEY (bac_id) REFERENCES plant_bac (id)');
        $this->addSql('ALTER TABLE plant_bac_saison ADD CONSTRAINT FK_793A341AF965414C FOREIGN KEY (saison_id) REFERENCES plant_saison (id)');
        $this->addSql('ALTER TABLE plant_bac_saison ADD CONSTRAINT FK_793A341AFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES plant_utilisateur (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plant_bac_saison DROP FOREIGN KEY FK_793A341AE03F15C0');
        $this->addSql('ALTER TABLE plant_bac_saison DROP FOREIGN KEY FK_793A341AF965414C');
        $this->addSql('ALTER TABLE plant_bac_saison DROP FOREIGN KEY FK_793A341AFB88E14F');
        $this->addSql('ALTER TABLE plant_bac DROP FOREIGN KEY FK_F2E36745FB88E14F');
        $this->addSql('DROP TABLE plant_bac_saison');
        $this->addSql('DROP TABLE plant_bac');
    }
}
