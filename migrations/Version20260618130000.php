<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Répare les chaînes double-encodées (mojibake UTF-8) issues du legacy.
 *
 * Contexte : les colonnes sont en utf8mb4 et contiennent majoritairement du
 * UTF-8 valide ; quelques lignes historiques ont été écrites via une connexion
 * latin1 → double-encodage (« Mûrier » stocké « MÃ»rier »). On les réinterprète
 * (latin1 → binaire → utf8mb4), ce qui annule le double-encodage.
 *
 * Ciblage prudent : uniquement les lignes contenant « Ã » ou « Â » (signature
 * du double-encodage) — les chaînes correctes ne sont pas touchées. Idempotent
 * en pratique sur ce jeu de données (les noms corrects ne contiennent pas ces
 * séquences).
 *
 * NB : opération sur la base partagée. Faire une sauvegarde avant exécution.
 */
final class Version20260618130000 extends AbstractMigration
{
    /** table => colonnes texte à réparer */
    private const TARGETS = [
        'plant_vegetable' => ['name', 'type_origine', 'nom_latin', 'p_fleur', 'p_fructi'],
        'plant_type' => ['name'],
        'plant_group' => ['name'],
        'plant_lieu' => ['name'],
        'plant_porte_greffe' => ['name'],
        'plant_action' => ['title', 'comment', 'type_action'],
        'plant_vegetable_history' => ['key', 'oldValue', 'newValue'],
    ];

    public function getDescription(): string
    {
        return 'Répare les chaînes double-encodées (mojibake UTF-8) héritées du legacy.';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\AbstractMySQLPlatform,
            'Migration spécifique MySQL.',
        );

        foreach (self::TARGETS as $table => $columns) {
            foreach ($columns as $column) {
                $t = '`'.$table.'`';
                $c = '`'.$column.'`';
                $fix = sprintf('CONVERT(CAST(CONVERT(%s USING latin1) AS BINARY) USING utf8mb4)', $c);
                // Réinterprète latin1→utf8mb4 (annule le double-encodage), UNIQUEMENT :
                //  - lignes marquées « Ã »/« Â » (signature du double-encodage),
                //  - ET dont la réinterprétation est un UTF-8 valide (IS NOT NULL) :
                //    garde-fou crucial — une chaîne déjà correcte (ex. « panaché »)
                //    donnerait NULL et serait donc exclue, jamais écrasée.
                $this->addSql(sprintf(
                    "UPDATE %s SET %s = %s WHERE (%s LIKE '%%Ã%%' OR %s LIKE '%%Â%%') AND %s IS NOT NULL",
                    $t, $c, $fix, $c, $c, $fix,
                ));
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Réparation de données non réversible.');
    }
}
