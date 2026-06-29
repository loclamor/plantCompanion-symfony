<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 1 — Grainothèque : tables plant_graine_type (type générique), plant_graine
 * (graine concrète) et plant_graine_lot (lots).
 */
final class Version20260623064924 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grainothèque : tables plant_graine_type, plant_graine et plant_graine_lot.';
    }

    public function up(Schema $schema): void
    {
        // Phase 1 — Grainothèque uniquement. Le diff auto a aussi capté du drift de
        // schéma legacy (renommages d'index sur des tables non liées) : volontairement
        // écarté pour ne pas toucher l'existant.
        $this->addSql('CREATE TABLE plant_graine_type (id INT AUTO_INCREMENT NOT NULL, utilisateur_id INT NOT NULL, name VARCHAR(255) NOT NULL, code VARCHAR(20) NOT NULL, INDEX IDX_209F775BFB88E14F (utilisateur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE plant_graine (id INT AUTO_INCREMENT NOT NULL, graine_type_id INT NOT NULL, utilisateur_id INT NOT NULL, code VARCHAR(50) NOT NULL, name VARCHAR(255) NOT NULL, methode_semis_conseillee VARCHAR(20) DEFAULT NULL, mois_semis SMALLINT DEFAULT NULL, mois_plantation_theorique SMALLINT DEFAULT NULL, mois_recolte_theorique SMALLINT DEFAULT NULL, notes LONGTEXT DEFAULT NULL, INDEX IDX_C68B4F5277915F94 (graine_type_id), INDEX IDX_C68B4F52FB88E14F (utilisateur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE plant_graine_lot (id INT AUTO_INCREMENT NOT NULL, graine_id INT NOT NULL, utilisateur_id INT NOT NULL, source VARCHAR(20) NOT NULL, date_acquisition DATE NOT NULL, quantite_initiale INT NOT NULL, quantite_restante INT NOT NULL, fournisseur VARCHAR(255) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, INDEX IDX_F6581B3081BEA5D2 (graine_id), INDEX IDX_F6581B30FB88E14F (utilisateur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE plant_graine_type ADD CONSTRAINT FK_209F775BFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES plant_utilisateur (id)');
        $this->addSql('ALTER TABLE plant_graine ADD CONSTRAINT FK_C68B4F5277915F94 FOREIGN KEY (graine_type_id) REFERENCES plant_graine_type (id)');
        $this->addSql('ALTER TABLE plant_graine ADD CONSTRAINT FK_C68B4F52FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES plant_utilisateur (id)');
        $this->addSql('ALTER TABLE plant_graine_lot ADD CONSTRAINT FK_F6581B3081BEA5D2 FOREIGN KEY (graine_id) REFERENCES plant_graine (id)');
        $this->addSql('ALTER TABLE plant_graine_lot ADD CONSTRAINT FK_F6581B30FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES plant_utilisateur (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plant_graine_lot DROP FOREIGN KEY FK_F6581B3081BEA5D2');
        $this->addSql('ALTER TABLE plant_graine_lot DROP FOREIGN KEY FK_F6581B30FB88E14F');
        $this->addSql('ALTER TABLE plant_graine DROP FOREIGN KEY FK_C68B4F5277915F94');
        $this->addSql('ALTER TABLE plant_graine DROP FOREIGN KEY FK_C68B4F52FB88E14F');
        $this->addSql('ALTER TABLE plant_graine_type DROP FOREIGN KEY FK_209F775BFB88E14F');
        $this->addSql('DROP TABLE plant_graine_lot');
        $this->addSql('DROP TABLE plant_graine');
        $this->addSql('DROP TABLE plant_graine_type');
    }
}
