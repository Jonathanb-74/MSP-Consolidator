<?php
// Vue Documentation — rendu en HTML depuis les fichiers docs/*.md (contenu intégré)
?>

<div class="row g-0" style="min-height:calc(100vh - 120px)">

    <!-- Nav latérale sticky -->
    <div class="col-auto" style="width:220px">
        <div class="sticky-top pt-1" style="top:70px">
            <nav id="docsNav" class="d-flex flex-column gap-1 pe-3">
                <p class="text-uppercase text-body-secondary small fw-semibold mb-1 px-2">Documentation</p>
                <a href="#objectif"       class="doc-nav-link">Objectif</a>
                <a href="#securite"       class="doc-nav-link text-warning fw-semibold">⚠ Sécurité</a>
                <a href="#installation"   class="doc-nav-link">Installation</a>
                <a href="#providers"      class="doc-nav-link">Fournisseurs</a>
                <div class="ps-3 d-flex flex-column gap-1 mt-1">
                    <a href="#prov-eset"       class="doc-nav-link small">ESET</a>
                    <a href="#prov-becloud"    class="doc-nav-link small">Be-Cloud / M365</a>
                    <a href="#prov-ninjaone"   class="doc-nav-link small">NinjaOne</a>
                    <a href="#prov-infomaniak" class="doc-nav-link small">Infomaniak</a>
                </div>
                <a href="#mapping"        class="doc-nav-link">Mapping</a>
                <a href="#normalisation"  class="doc-nav-link">Normalisation</a>
                <a href="#sync"           class="doc-nav-link">Synchronisation</a>
            </nav>
        </div>
    </div>

    <!-- Contenu -->
    <div class="col ps-4" style="max-width:820px">

        <!-- ── Objectif ── -->
        <section id="objectif" class="doc-section">
            <h2 class="doc-h2">Objectif de l'application</h2>

            <p>
                <strong>MSP Consolidator</strong> est une application web PHP permettant aux prestataires informatiques (MSP)
                de centraliser et visualiser l'ensemble de leurs licences, abonnements et équipements gérés chez différents
                fournisseurs depuis une interface unique.
            </p>

            <p>Les MSP gèrent des dizaines de clients répartis sur plusieurs plateformes fournisseurs. Sans outil centralisé,
            suivre les consommations de licences, les renouvellements et les sur/sous-utilisations implique de jongler entre
            plusieurs consoles d'administration.</p>

            <p>MSP Consolidator résout ce problème en :</p>

            <ul>
                <li><strong>Synchronisant</strong> automatiquement les données de chaque fournisseur (ESET, Be-Cloud, NinjaOne, Infomaniak) dans une base de données locale</li>
                <li><strong>Mappant</strong> les clients fournisseurs aux clients internes via un système de correspondance par nom ou identifiant</li>
                <li><strong>Affichant</strong> un récapitulatif unifié (page <em>Récap Licences</em>) : toutes les licences de tous les clients sur une seule page</li>
                <li><strong>Alertant</strong> visuellement sur les sur-utilisations, expirations imminentes et licences non assignées</li>
                <li><strong>Générant</strong> des rapports PDF par client</li>
            </ul>

            <div class="doc-callout doc-callout-info">
                <strong>Ce que l'application n'est pas :</strong> elle ne facture pas, ne provisionne pas de licences chez les fournisseurs
                et ne sert pas de portail client.
            </div>
        </section>

        <!-- ── Sécurité ── -->
        <section id="securite" class="doc-section">
            <h2 class="doc-h2 text-warning">⚠ Sécurité — Points critiques</h2>

            <div class="doc-callout doc-callout-danger">
                <strong>MSP Consolidator ne dispose d'aucun système de connexion, d'authentification ou de contrôle d'accès.</strong><br>
                Toute personne pouvant accéder à l'URL de l'application a un accès complet et immédiat à toutes les données
                et à tous les identifiants API des fournisseurs.
            </div>

            <h3 class="doc-h3">Règle absolue : ne jamais exposer l'application sur Internet</h3>
            <p>L'application <strong>ne doit jamais</strong> être accessible depuis une adresse IP publique sans protection réseau préalable.</p>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div class="card border-success h-100">
                        <div class="card-header text-success fw-semibold small">✅ Déploiements acceptables</div>
                        <ul class="list-group list-group-flush small">
                            <li class="list-group-item">Réseau local (LAN) — postes du bureau uniquement</li>
                            <li class="list-group-item">Derrière un VPN d'entreprise</li>
                            <li class="list-group-item">Serveur interne protégé par firewall</li>
                            <li class="list-group-item">Accès via tunnel SSH</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-danger h-100">
                        <div class="card-header text-danger fw-semibold small">❌ Déploiements interdits</div>
                        <ul class="list-group list-group-flush small">
                            <li class="list-group-item">Serveur public sans authentification</li>
                            <li class="list-group-item">URL partagée par e-mail</li>
                            <li class="list-group-item">Hébergement mutualisé public</li>
                        </ul>
                    </div>
                </div>
            </div>

            <h3 class="doc-h3">Données sensibles stockées</h3>
            <p>Le fichier <code>config/providers.php</code> contient en clair les identifiants API de tous les fournisseurs.
            <strong>Ne le commitez jamais dans un dépôt Git public.</strong></p>

            <p>Ajoutez-le à votre <code>.gitignore</code> si votre dépôt est public :</p>
            <pre class="doc-code">/config/providers.php</pre>

            <h3 class="doc-h3">Protection minimale si exposition inévitable</h3>
            <p>Si vous devez exposer l'application sur un réseau semi-ouvert, ajoutez une authentification HTTP Basic via <code>.htaccess</code> :</p>
            <pre class="doc-code">AuthType Basic
AuthName "MSP Consolidator"
AuthUserFile /chemin/absolu/.htpasswd
Require valid-user</pre>

            <div class="doc-callout doc-callout-warning">
                L'authentification HTTP Basic requiert HTTPS pour ne pas transmettre les mots de passe en clair.
            </div>
        </section>

        <!-- ── Installation ── -->
        <section id="installation" class="doc-section">
            <h2 class="doc-h2">Installation</h2>

            <div class="doc-callout doc-callout-info">
                Le fichier <a href="https://github.com/votre-organisation/MSP-Consolidator/blob/main/docs/installation.md" target="_blank">
                docs/installation.md</a> contient le guide complet, optimisé pour GitHub.
            </div>

            <h3 class="doc-h3">Prérequis</h3>
            <table class="table table-sm table-bordered small">
                <thead class="table-dark"><tr><th>Logiciel</th><th>Version min.</th><th>Notes</th></tr></thead>
                <tbody>
                    <tr><td>PHP</td><td>8.1</td><td>Extensions : <code>pdo_mysql</code>, <code>json</code>, <code>curl</code>, <code>mbstring</code></td></tr>
                    <tr><td>MySQL / MariaDB</td><td>8.0 / 10.6</td><td>Supporte <code>JSON_EXTRACT</code></td></tr>
                    <tr><td>Composer</td><td>2.x</td><td>Gestionnaire de dépendances PHP</td></tr>
                    <tr><td>Serveur web</td><td>Apache 2.4 / Nginx</td><td>Avec <code>mod_rewrite</code></td></tr>
                </tbody>
            </table>

            <h3 class="doc-h3">Étapes résumées</h3>
            <ol class="doc-steps">
                <li><code>git clone</code> du dépôt</li>
                <li><code>composer install --no-dev</code></li>
                <li>Créer la base MySQL, importer <code>database/schema.sql</code> puis les migrations</li>
                <li>Configurer <code>config/app.php</code> (credentials DB)</li>
                <li>Configurer <code>config/providers.php</code> (credentials API fournisseurs)</li>
                <li>Pointer le <code>DocumentRoot</code> du virtualhost vers <code>public/</code></li>
                <li>Lancer une première sync depuis l'interface</li>
            </ol>
        </section>

        <!-- ── Fournisseurs ── -->
        <section id="providers" class="doc-section">
            <h2 class="doc-h2">Fournisseurs (Providers)</h2>

            <p>Chaque fournisseur intégré suit la même architecture modulaire dans <code>app/Modules/{Provider}/</code> :</p>

            <table class="table table-sm table-bordered small mb-3">
                <thead class="table-dark"><tr><th>Fichier</th><th>Rôle</th></tr></thead>
                <tbody>
                    <tr><td><code>{Provider}ApiClient.php</code></td><td>Communication HTTP avec l'API REST du fournisseur</td></tr>
                    <tr><td><code>{Provider}SyncService.php</code></td><td>Synchronisation des données vers la base locale</td></tr>
                    <tr><td><code>{Provider}Controller.php</code></td><td>Pages web (liste, détail, sync, logs)</td></tr>
                    <tr><td><code>{Provider}TokenCache.php</code></td><td>Gestion du token OAuth2 (si applicable)</td></tr>
                </tbody>
            </table>

            <h3 class="doc-h3">Multi-connexions</h3>
            <p>Chaque fournisseur supporte plusieurs connexions simultanées (plusieurs comptes/consoles). La configuration se fait dans <code>config/providers.php</code> en tableau :</p>
            <pre class="doc-code">'eset' => [
    ['key' => 'principale', 'name' => 'Console FCI', 'username' => '...', ...],
    ['key' => 'client_be',  'name' => 'Console BE',  'username' => '...', ...],
],</pre>

            <h3 class="doc-h3">Cycle de synchronisation</h3>
            <ol>
                <li>Déclenchement manuel (bouton <em>Sync maintenant</em>) ou via cron</li>
                <li>Le <code>ApiClient</code> interroge l'API du fournisseur (pagination automatique)</li>
                <li>Le <code>SyncService</code> insère ou met à jour les données en base (upsert)</li>
                <li>Un log est créé dans <code>sync_logs</code> (statut, durée, compteurs, erreurs)</li>
            </ol>

            <!-- ESET -->
            <div id="prov-eset" class="doc-provider-card mt-4">
                <div class="doc-provider-header" style="background:#198754">
                    <i class="bi bi-shield-lock me-2"></i>ESET
                </div>
                <div class="doc-provider-body">
                    <p>Gestion des licences antivirus / EDR via l'API ESET MSP Administrator.</p>
                    <p><strong>Données synchronisées :</strong> sociétés, licences (produit, quantité, usage, clé, expiration).</p>
                    <div class="doc-callout doc-callout-info mb-0">Documentation détaillée à venir.</div>
                </div>
            </div>

            <!-- Be-Cloud -->
            <div id="prov-becloud" class="doc-provider-card mt-3">
                <div class="doc-provider-header" style="background:#0dcaf0;color:#000">
                    <i class="bi bi-cloud-check me-2"></i>Be-Cloud / Microsoft 365
                </div>
                <div class="doc-provider-body">
                    <p>Revendeur CSP Microsoft via l'API CloudCockpit. Authentification OAuth2 (Microsoft Entra, flux <em>client_credentials</em>).</p>
                    <p><strong>Données synchronisées :</strong></p>
                    <ul>
                        <li><strong>Clients</strong> (be_cloud_customers)</li>
                        <li><strong>Abonnements</strong> (be_cloud_subscriptions) — offre, statut, quantité, dates, cycle, prix catalogue</li>
                        <li><strong>Licences M365</strong> (be_cloud_licenses) — consommation réelle par SKU : total / consommées / disponibles / suspendues</li>
                    </ul>
                    <div class="doc-callout doc-callout-info mb-0">Documentation détaillée à venir.</div>
                </div>
            </div>

            <!-- NinjaOne -->
            <div id="prov-ninjaone" class="doc-provider-card mt-3">
                <div class="doc-provider-header" style="background:#ffc107;color:#000">
                    <i class="bi bi-hdd-network me-2"></i>NinjaOne
                </div>
                <div class="doc-provider-body">
                    <p>Plateforme RMM/MDM. Authentification OAuth2 (client_credentials).</p>
                    <p><strong>Données synchronisées :</strong> organisations (compteurs RMM, NMS, MDM, VMM, Cloud), appareils individuels (nom, OS, statut en ligne).</p>
                    <div class="doc-callout doc-callout-info mb-0">Documentation détaillée à venir.</div>
                </div>
            </div>

            <!-- Infomaniak -->
            <div id="prov-infomaniak" class="doc-provider-card mt-3">
                <div class="doc-provider-header" style="background:#dc3545">
                    <i class="bi bi-server me-2"></i>Infomaniak
                </div>
                <div class="doc-provider-body">
                    <p>Hébergeur suisse. Authentification par token API Bearer.</p>
                    <p><strong>Données synchronisées :</strong> comptes, produits (service, nom interne, date d'expiration, statut trial/gratuit).</p>
                    <div class="doc-callout doc-callout-info mb-0">Documentation détaillée à venir.</div>
                </div>
            </div>
        </section>

        <!-- ── Mapping ── -->
        <section id="mapping" class="doc-section">
            <h2 class="doc-h2">Mapping fournisseur ↔ client interne</h2>

            <p>La table <code>client_provider_mappings</code> établit la correspondance entre un <strong>client fournisseur</strong>
            (ex : une société dans la console ESET) et un <strong>client interne</strong> (créé manuellement dans MSP Consolidator).</p>

            <h3 class="doc-h3">Auto-mapping</h3>
            <p>À chaque synchronisation, l'application tente de faire correspondre automatiquement les clients fournisseurs
            aux clients internes par <strong>similarité de nom</strong> (distance de Levenshtein normalisée).
            Les règles de <a href="#normalisation">normalisation</a> sont appliquées avant la comparaison.</p>

            <p>Un mapping auto-détecté est marqué <code>is_confirmed = 0</code> (non confirmé) — il apparaît avec un badge
            <span class="badge bg-warning text-dark small">Mapping non confirmé</span> dans l'interface.
            Il doit être <strong>validé manuellement</strong> depuis la page <strong>Mapping</strong> pour être pris en compte.</p>

            <h3 class="doc-h3">Mapping manuel</h3>
            <p>Depuis la page <strong>Mapping</strong> (bouton disponible sur chaque vue fournisseur), vous pouvez :</p>
            <ul>
                <li>Confirmer un mapping suggéré automatiquement</li>
                <li>Créer un mapping manuellement (recherche du client interne par nom ou numéro)</li>
                <li>Dissocier un mapping existant</li>
            </ul>

            <div class="doc-callout doc-callout-info">
                <strong>Identifiant fournisseur :</strong> certains fournisseurs permettent de stocker un numéro client interne
                directement dans leur console (<em>internal_identifier</em>). MSP Consolidator peut utiliser cet identifiant
                comme critère de correspondance prioritaire — plus fiable que la comparaison par nom.
            </div>
        </section>

        <!-- ── Normalisation ── -->
        <section id="normalisation" class="doc-section">
            <h2 class="doc-h2">Système de normalisation</h2>

            <p>La normalisation améliore la précision de l'auto-mapping en retirant les suffixes administratifs et conventions
            de nommage qui diffèrent entre la console fournisseur et votre base client interne.</p>

            <h3 class="doc-h3">Exemple concret</h3>
            <table class="table table-sm table-bordered small mb-3">
                <thead class="table-dark"><tr><th>Nom fournisseur</th><th>Après normalisation</th><th>Nom client interne</th><th>Résultat</th></tr></thead>
                <tbody>
                    <tr><td>Dupont SA</td><td>dupont</td><td>Dupont</td><td><span class="badge bg-success">Match ✓</span></td></tr>
                    <tr><td>Dupont - Agence Lyon</td><td>dupont</td><td>Dupont</td><td><span class="badge bg-success">Match ✓</span></td></tr>
                    <tr><td>DUPONT SARL</td><td>dupont</td><td>Dupont</td><td><span class="badge bg-success">Match ✓</span></td></tr>
                    <tr><td>Martin & Fils</td><td>martin  fils</td><td>Martin</td><td><span class="badge bg-warning text-dark">Faible</span></td></tr>
                </tbody>
            </table>

            <h3 class="doc-h3">Types de règles</h3>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header small fw-semibold">Formes juridiques (pré-configurées)</div>
                        <div class="card-body small">
                            Retrait automatique des formes juridiques françaises et belges :
                            <code>SA</code>, <code>SAS</code>, <code>SARL</code>, <code>SASU</code>, <code>SRL</code>,
                            <code>SPRL</code>, <code>ASBL</code>, <code>GIE</code>…<br>
                            Supprimées en tant que <em>mots entiers</em> (évite de supprimer <code>SAS</code> dans <code>OASIS</code>).
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header small fw-semibold">Exclusions personnalisées</div>
                        <div class="card-body small">
                            Sous-chaînes supprimées telles quelles (insensible à la casse).<br>
                            Exemples : <code> - Agence</code>, <code> - Groupe</code>, <code>MSP-</code>, <code> (test)</code>
                        </div>
                    </div>
                </div>
            </div>

            <p>Configuration dans <a href="/settings/normalisation"><strong>Paramètres → Normalisation</strong></a>.</p>
        </section>

        <!-- ── Synchronisation ── -->
        <section id="sync" class="doc-section">
            <h2 class="doc-h2">Synchronisation</h2>

            <h3 class="doc-h3">Déclenchement manuel</h3>
            <p>Depuis n'importe quelle page fournisseur, le bouton <strong>Sync maintenant</strong> lance une synchronisation complète
            pour ce fournisseur. Une fenêtre modale affiche la progression en temps réel.</p>

            <h3 class="doc-h3">Scripts CLI</h3>
            <p>Des scripts PHP en ligne de commande sont disponibles dans <code>scripts/</code> :</p>

            <table class="table table-sm table-bordered small mb-3">
                <thead class="table-dark"><tr><th>Script</th><th>Rôle</th></tr></thead>
                <tbody>
                    <tr><td><code>scripts/sync_all.php</code></td><td>Synchronise <strong>tous</strong> les fournisseurs actifs</td></tr>
                    <tr><td><code>scripts/sync_eset.php</code></td><td>ESET uniquement</td></tr>
                    <tr><td><code>scripts/sync_becloud.php</code></td><td>Be-Cloud uniquement</td></tr>
                    <tr><td><code>scripts/sync_ninjaone.php</code></td><td>NinjaOne uniquement</td></tr>
                    <tr><td><code>scripts/sync_infomaniak.php</code></td><td>Infomaniak uniquement</td></tr>
                </tbody>
            </table>

            <pre class="doc-code"># Tous les fournisseurs
php scripts/sync_all.php

# Un seul fournisseur
php scripts/sync_all.php --provider=becloud

# Plusieurs fournisseurs
php scripts/sync_all.php --provider=eset,ninjaone</pre>

            <h3 class="doc-h3">Automatisation cron (Linux)</h3>
            <pre class="doc-code"># Toutes les heures — tous les fournisseurs
0 * * * * www-data php /var/www/MSP-Consolidator/scripts/sync_all.php >> /var/log/msp-sync.log 2>&1

# Ou par fournisseur à fréquences décalées
0  * * * * www-data php /var/www/MSP-Consolidator/scripts/sync_eset.php       >> /var/log/msp-sync.log 2>&1
15 * * * * www-data php /var/www/MSP-Consolidator/scripts/sync_becloud.php     >> /var/log/msp-sync.log 2>&1
30 * * * * www-data php /var/www/MSP-Consolidator/scripts/sync_ninjaone.php    >> /var/log/msp-sync.log 2>&1
45 * * * * www-data php /var/www/MSP-Consolidator/scripts/sync_infomaniak.php  >> /var/log/msp-sync.log 2>&1</pre>

            <h3 class="doc-h3">Planificateur Windows (WAMP)</h3>
            <p>Planificateur de tâches Windows → Créer une tâche de base :</p>
            <ul>
                <li>Déclencheur : Toutes les heures</li>
                <li>Programme : <code>C:\wamp64\bin\php\phpX.X.X\php.exe</code></li>
                <li>Arguments : <code>C:\wamp64\www\MSP-Consolidator\scripts\sync_all.php</code></li>
            </ul>

            <h3 class="doc-h3">Logs de synchronisation</h3>
            <p>Chaque synchronisation génère un enregistrement dans <code>sync_logs</code>, consultable depuis le bouton
            <strong>Logs</strong> sur chaque page fournisseur :</p>
            <ul>
                <li>Date et durée d'exécution</li>
                <li>Statut (<em>success</em> / <em>warning</em> / <em>error</em>)</li>
                <li>Nombre d'éléments synchronisés par étape</li>
                <li>Messages d'erreur détaillés en cas d'échec</li>
            </ul>

            <div class="doc-callout doc-callout-info">
                Les scripts retournent le code de sortie <code>0</code> (succès) ou <code>1</code> (erreurs partielles),
                compatible avec les outils de monitoring cron (Healthchecks.io, Cronitor, etc.).
            </div>
        </section>

        <div class="pb-5"></div>
    </div>
</div>

<style>
.doc-section {
    padding-top: 24px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--bs-border-color);
    margin-bottom: 8px;
}
.doc-section:last-of-type { border-bottom: none; }

.doc-h2 {
    font-size: 1.35rem;
    font-weight: 700;
    margin-bottom: 1rem;
    padding-bottom: .4rem;
    border-bottom: 2px solid var(--bs-primary);
    display: inline-block;
}
.doc-h3 {
    font-size: 1rem;
    font-weight: 600;
    margin: 1.2rem 0 .5rem;
    color: var(--bs-secondary-color);
    text-transform: uppercase;
    letter-spacing: .4px;
    font-size: .8rem;
}

.doc-callout {
    padding: .75rem 1rem;
    border-radius: .375rem;
    margin: .75rem 0;
    font-size: .9rem;
}
.doc-callout-info    { background: rgba(13,202,240,.1);  border-left: 4px solid #0dcaf0; }
.doc-callout-warning { background: rgba(255,193,7,.15);  border-left: 4px solid #ffc107; }
.doc-callout-danger  { background: rgba(220,53,69,.12);  border-left: 4px solid #dc3545; }

.doc-code {
    background: var(--bs-tertiary-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: .375rem;
    padding: .75rem 1rem;
    font-size: .82rem;
    font-family: monospace;
    overflow-x: auto;
    white-space: pre;
}

.doc-steps { padding-left: 1.2rem; }
.doc-steps li { margin-bottom: .35rem; }

.doc-nav-link {
    display: block;
    padding: .3rem .75rem;
    border-radius: .375rem;
    color: var(--bs-body-color);
    text-decoration: none;
    font-size: .875rem;
    transition: background .15s;
}
.doc-nav-link:hover { background: var(--bs-tertiary-bg); color: var(--bs-body-color); }
.doc-nav-link.active { background: var(--bs-primary); color: #fff; }

.doc-provider-card { border: 1px solid var(--bs-border-color); border-radius: .5rem; overflow: hidden; }
.doc-provider-header { padding: .6rem 1rem; color: #fff; font-weight: 600; font-size: .95rem; }
.doc-provider-body { padding: 1rem; }
</style>

<script>
// Scroll-spy simple sur les sections
(function () {
    const navLinks = document.querySelectorAll('.doc-nav-link');
    const sections = document.querySelectorAll('.doc-section');

    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const id = entry.target.id;
                navLinks.forEach(a => {
                    a.classList.toggle('active', a.getAttribute('href') === '#' + id);
                });
            }
        });
    }, { threshold: 0.2, rootMargin: '-80px 0px -60% 0px' });

    sections.forEach(s => observer.observe(s));
})();
</script>
