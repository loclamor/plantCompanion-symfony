<?php

/**
 * Endpoint de migration Doctrine sans accès console (hébergement FTP-only).
 *
 * Boote le kernel Symfony en prod et joue `doctrine:migrations:migrate`.
 * Déployé en permanence et déclenché automatiquement par le workflow GitHub
 * Action (étape « Jouer les migrations ») après le dépôt FTP — voir
 * .github/workflows/deploy.yml. Peut aussi être appelé à la main au navigateur.
 *
 * SÉCURITÉ — protégé par un token secret lu dans l'environnement (jamais
 * committé) :
 *   - Serveur  : définir MIGRATE_TOKEN dans .env.local (non déployé, non versionné).
 *   - Action   : secret GitHub MIGRATE_TOKEN (passé en en-tête X-Migrate-Token).
 * Sans token serveur configuré → 404 (fail-closed). Token absent/faux → 404.
 *
 * Idempotent : `--allow-no-migration` => exit 0 même si rien à jouer (chaque
 * déploiement peut donc l'appeler sans erreur).
 *
 * Appel manuel : https://VOTRE-DOMAINE/_migrate.php?token=LE_TOKEN
 * Appel CI     : curl -H "X-Migrate-Token: LE_TOKEN" https://VOTRE-DOMAINE/_migrate.php
 *
 * PRÉCAUTION : sauvegarder la base avant toute migration touchant aux données.
 */

require_once dirname(__DIR__).'/vendor/autoload.php';

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Dotenv\Dotenv;

// Charge .env / .env.local (APP_ENV=prod, DATABASE_URL utf8mb4, MIGRATE_TOKEN).
(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

// --- Garde d'accès (avant tout boot kernel) -------------------------------
$expected = $_SERVER['MIGRATE_TOKEN'] ?? $_ENV['MIGRATE_TOKEN'] ?? getenv('MIGRATE_TOKEN') ?: null;
$provided = $_SERVER['HTTP_X_MIGRATE_TOKEN'] ?? $_GET['token'] ?? '';

if (!is_string($expected) || $expected === '' || !is_string($provided) || !hash_equals($expected, $provided)) {
    http_response_code(404);
    exit;
}

set_time_limit(300);
header('Content-Type: text/plain; charset=utf-8');

// --- Exécution des migrations ---------------------------------------------
$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'prod', false);
$application = new Application($kernel);
$application->setAutoExit(false);

$output = new BufferedOutput();
$exitCode = $application->run(new ArrayInput([
    'command' => 'doctrine:migrations:migrate',
    '--no-interaction' => true,
    '--allow-no-migration' => true,
]), $output);

// HTTP 500 si échec → `curl -f` côté CI fait échouer le job (BufferedOutput
// n'a encore rien émis, donc on peut fixer le code avant le premier echo).
if ($exitCode !== 0) {
    http_response_code(500);
}

echo $output->fetch();
echo "\n--- exit code: {$exitCode} ---\n";
echo $exitCode === 0
    ? "OK : migrations à jour.\n"
    : "ÉCHEC. Vérifiez .env.local (DATABASE_URL) et var/log/.\n";
