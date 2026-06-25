<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 5 — table plant_recolte (récoltes multiples par culture).
 */
final class Version20260625093607 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 5 — table plant_recolte (récoltes multiples par culture)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE plant_recolte (id INT AUTO_INCREMENT NOT NULL, culture_id INT NOT NULL, utilisateur_id INT NOT NULL, date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', quantite DOUBLE PRECISION DEFAULT NULL, unite VARCHAR(20) NOT NULL, notes VARCHAR(255) DEFAULT NULL, INDEX IDX_4EBABB45B108249D (culture_id), INDEX IDX_4EBABB45FB88E14F (utilisateur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE plant_recolte ADD CONSTRAINT FK_4EBABB45B108249D FOREIGN KEY (culture_id) REFERENCES plant_culture (id)');
        $this->addSql('ALTER TABLE plant_recolte ADD CONSTRAINT FK_4EBABB45FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES plant_utilisateur (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plant_recolte DROP FOREIGN KEY FK_4EBABB45B108249D');
        $this->addSql('ALTER TABLE plant_recolte DROP FOREIGN KEY FK_4EBABB45FB88E14F');
        $this->addSql('DROP TABLE plant_recolte');
    }
}
