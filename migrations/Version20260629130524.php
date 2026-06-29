<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Hiérarchie des types de graines : ajoute plant_graine_type.parent_id
 * (auto-référence ManyToOne, nullable). Le diff Doctrine a été réduit à ce
 * seul changement — les autres lignes générées étaient du drift legacy
 * d'index sans rapport.
 */
final class Version20260629130524 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Hiérarchie des types de graines : ajoute plant_graine_type.parent_id (auto-référence).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plant_graine_type ADD parent_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE plant_graine_type ADD CONSTRAINT FK_209F775B727ACA70 FOREIGN KEY (parent_id) REFERENCES plant_graine_type (id)');
        $this->addSql('CREATE INDEX IDX_209F775B727ACA70 ON plant_graine_type (parent_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plant_graine_type DROP FOREIGN KEY FK_209F775B727ACA70');
        $this->addSql('DROP INDEX IDX_209F775B727ACA70 ON plant_graine_type');
        $this->addSql('ALTER TABLE plant_graine_type DROP parent_id');
    }
}
