<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260623094850 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée la table plant_saison (module Potager, Phase 2).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE plant_saison (id INT AUTO_INCREMENT NOT NULL, utilisateur_id INT NOT NULL, name VARCHAR(255) NOT NULL, annee INT NOT NULL, date_debut DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', date_fin DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', statut VARCHAR(20) NOT NULL, INDEX IDX_2340040DFB88E14F (utilisateur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE plant_saison ADD CONSTRAINT FK_2340040DFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES plant_utilisateur (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plant_saison DROP FOREIGN KEY FK_2340040DFB88E14F');
        $this->addSql('DROP TABLE plant_saison');
    }
}
