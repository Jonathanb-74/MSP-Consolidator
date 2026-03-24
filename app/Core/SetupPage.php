<?php

namespace App\Core;

/**
 * Affiche une page HTML autonome (sans layout) en cas de problème de configuration.
 * Utilisée au démarrage avant que le routeur ne soit disponible.
 */
class SetupPage
{
    /**
     * @param 'config'|'connection'|'schema' $step   Étape échouée
     * @param string                          $detail Message technique (affiché en mode debug)
     */
    public static function show(string $step, string $detail = ''): never
    {
        $debug = defined('APP_ROOT') && file_exists(APP_ROOT . '/config/app.php')
            ? (bool)(require APP_ROOT . '/config/app.php')['debug']
            : false;

        $steps = [
            'config'     => ['Fichier de configuration',     'config/database.php correctement renseigné'],
            'connection' => ['Connexion à la base de données', 'Serveur MySQL / MariaDB accessible'],
            'schema'     => ['Schéma de la base de données',  'Tables créées via schema.sql'],
        ];

        $order = ['config', 'connection', 'schema'];
        $failedIndex = array_search($step, $order, true);

        // Construire les étapes avec statut
        $stepsHtml = '';
        foreach ($order as $i => $key) {
            if ($i < $failedIndex) {
                $icon  = '<span class="text-success me-2"><svg width="18" height="18" fill="currentColor" viewBox="0 0 16 16"><path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z"/></svg></span>';
                $cls   = 'text-success';
                $label = 'OK';
            } elseif ($i === $failedIndex) {
                $icon  = '<span class="text-danger me-2"><svg width="18" height="18" fill="currentColor" viewBox="0 0 16 16"><path d="M11.354 4.646a.5.5 0 0 0-.708 0L8 7.293 5.354 4.646a.5.5 0 0 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0 0-.708z"/></svg></span>';
                $cls   = 'text-danger fw-semibold';
                $label = 'Échec';
            } else {
                $icon  = '<span class="text-secondary me-2"><svg width="18" height="18" fill="currentColor" viewBox="0 0 16 16"><circle cx="8" cy="8" r="4.5"/></svg></span>';
                $cls   = 'text-secondary';
                $label = 'En attente';
            }

            $stepsHtml .= '<li class="list-group-item d-flex align-items-center ' . ($i === $failedIndex ? 'list-group-item-danger' : '') . '">'
                . $icon
                . '<div class="flex-grow-1">'
                . '<div class="' . $cls . ' small">' . htmlspecialchars($steps[$key][0]) . '</div>'
                . '<div class="text-body-secondary" style="font-size:.8em">' . htmlspecialchars($steps[$key][1]) . '</div>'
                . '</div>'
                . '<span class="badge ' . ($i < $failedIndex ? 'bg-success' : ($i === $failedIndex ? 'bg-danger' : 'bg-secondary')) . '">' . $label . '</span>'
                . '</li>';
        }

        // Instructions selon l'étape
        $instructions = match ($step) {
            'config' => self::instrConfig(),
            'connection' => self::instrConnection(),
            'schema' => self::instrSchema(),
            default => '',
        };

        $debugBlock = '';
        if ($debug && $detail !== '') {
            $debugBlock = '<div class="alert alert-secondary mt-3 mb-0 small font-monospace">'
                . '<strong>Détail technique :</strong><br>'
                . nl2br(htmlspecialchars($detail))
                . '</div>';
        }

        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        echo <<<HTML
<!DOCTYPE html>
<html lang="fr" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration requise — MSP Consolidator</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
<div class="min-vh-100 d-flex align-items-center justify-content-center p-3" style="background:var(--bs-body-bg)">
    <div style="max-width:620px;width:100%">

        <!-- Header -->
        <div class="text-center mb-4">
            <i class="bi bi-shield-check text-primary" style="font-size:2.5rem"></i>
            <h1 class="h4 fw-bold mt-2 mb-0">MSP Consolidator</h1>
            <p class="text-body-secondary small">Configuration initiale requise</p>
        </div>

        <!-- Étapes -->
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-list-check text-body-secondary"></i>
                <span class="fw-semibold">État de l'installation</span>
            </div>
            <ul class="list-group list-group-flush">
                {$stepsHtml}
            </ul>
        </div>

        <!-- Instructions -->
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-terminal text-warning"></i>
                <span class="fw-semibold">Marche à suivre</span>
            </div>
            <div class="card-body">
                {$instructions}
                {$debugBlock}
            </div>
        </div>

        <p class="text-center text-body-secondary small mt-3">
            Rechargez la page après avoir effectué les corrections.
            &nbsp;·&nbsp;
            <a href="/" class="text-body-secondary">Réessayer</a>
        </p>

    </div>
</div>
</body>
</html>
HTML;
        exit;
    }

    // ── Instructions par étape ────────────────────────────────────────────────

    private static function instrConfig(): string
    {
        return <<<HTML
<p class="mb-2">Le fichier <code>config/database.php</code> est absent ou invalide.</p>
<ol class="mb-0">
    <li class="mb-2">
        Créez le fichier <code>config/database.php</code> en vous basant sur le modèle suivant&nbsp;:
        <pre class="bg-body-secondary rounded p-2 mt-1 small mb-0">&lt;?php
return [
    'host'     =&gt; '127.0.0.1',
    'port'     =&gt; '3306',
    'dbname'   =&gt; 'msp_consolidator',
    'user'     =&gt; 'votre_utilisateur',
    'password' =&gt; 'votre_mot_de_passe',
    'charset'  =&gt; 'utf8mb4',
    'options'  =&gt; [
        PDO::ATTR_ERRMODE            =&gt; PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE =&gt; PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   =&gt; false,
    ],
];</pre>
    </li>
    <li>Adaptez <code>host</code>, <code>dbname</code>, <code>user</code> et <code>password</code> à votre environnement.</li>
</ol>
HTML;
    }

    private static function instrConnection(): string
    {
        return <<<HTML
<p class="mb-2">La connexion à la base de données a échoué. Vérifiez les points suivants&nbsp;:</p>
<ul class="mb-0">
    <li class="mb-1">Le serveur MySQL / MariaDB est démarré et accessible.</li>
    <li class="mb-1">Les identifiants dans <code>config/database.php</code> sont corrects (<code>host</code>, <code>port</code>, <code>user</code>, <code>password</code>).</li>
    <li class="mb-1">La base de données <code>dbname</code> existe. Créez-la si besoin&nbsp;:
        <pre class="bg-body-secondary rounded p-2 mt-1 small mb-0">CREATE DATABASE msp_consolidator CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'msp_consolidator'@'localhost' IDENTIFIED BY 'mot_de_passe';
GRANT ALL PRIVILEGES ON msp_consolidator.* TO 'msp_consolidator'@'localhost';
FLUSH PRIVILEGES;</pre>
    </li>
    <li>L'utilisateur dispose des droits suffisants sur la base.</li>
</ul>
HTML;
    }

    private static function instrSchema(): string
    {
        return <<<HTML
<p class="mb-2">La connexion fonctionne mais les tables sont absentes. Importez le schéma&nbsp;:</p>
<ol class="mb-0">
    <li class="mb-2">
        Via la ligne de commande&nbsp;:
        <pre class="bg-body-secondary rounded p-2 mt-1 small mb-0">mysql -u votre_utilisateur -p msp_consolidator &lt; schema.sql</pre>
    </li>
    <li class="mb-2">
        Ou via phpMyAdmin / TablePlus / DBeaver&nbsp;: importez le fichier <code>schema.sql</code> situé à la racine du projet.
    </li>
    <li>
        Si la base existait déjà, appliquez les migrations manquantes depuis&nbsp;:
        <pre class="bg-body-secondary rounded p-2 mt-1 small mb-0">database/migrations/</pre>
    </li>
</ol>
HTML;
    }
}
