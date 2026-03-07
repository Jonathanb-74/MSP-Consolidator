<!DOCTYPE html>
<html lang="fr" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'MSP Consolidator') ?> — MSP Consolidator</title>
    <!-- Bootstrap 5.3 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom styles -->
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>

<!-- ── Sidebar ────────────────────────────────────────────────── -->
<div class="d-flex" id="wrapper">
    <nav id="sidebar" class="d-flex flex-column flex-shrink-0 p-3 border-end" style="width:260px;">

        <a href="/" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-decoration-none">
            <i class="bi bi-shield-check fs-4 me-2 text-primary"></i>
            <span class="fs-5 fw-semibold">MSP Consolidator</span>
        </a>

        <hr>

        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item">
                <a href="/" class="nav-link <?= (($_SERVER['REQUEST_URI'] === '/' || str_starts_with($_SERVER['REQUEST_URI'], '/dashboard')) ? 'active' : '') ?>">
                    <i class="bi bi-speedometer2 me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="/licenses" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'], '/licenses') ? 'active' : '' ?>">
                    <i class="bi bi-grid-3x3 me-2"></i>Récap Licences
                </a>
            </li>
        </ul>

        <hr>

        <!-- Clients -->
        <p class="text-uppercase text-body-secondary small fw-semibold px-1 mb-1">Clients</p>
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item">
                <a href="/clients" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'], '/clients') ? 'active' : '' ?>">
                    <i class="bi bi-people me-2"></i>Clients
                </a>
            </li>
            <li class="nav-item">
                <a href="/tags" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'], '/tags') ? 'active' : '' ?>">
                    <i class="bi bi-tags me-2"></i>Tags
                </a>
            </li>
        </ul>

        <hr>

        <!-- Fournisseurs (chargés depuis DB) -->
        <?php
        $_sidebarProviderMeta = [
            'eset'       => ['icon' => 'bi-shield-lock',  'color' => 'text-success', 'url' => '/eset/licenses',     'prefix' => '/eset'],
            'becloud'    => ['icon' => 'bi-cloud-check',  'color' => 'text-info',    'url' => '/becloud/licenses', 'prefix' => '/becloud'],
            'ninjaone'   => ['icon' => 'bi-hdd-network',  'color' => 'text-warning', 'url' => '/ninjaone/licenses', 'prefix' => '/ninjaone'],
            'wasabi'     => ['icon' => 'bi-cloud',         'color' => 'text-warning'],
            'veeam'      => ['icon' => 'bi-archive',       'color' => 'text-primary'],
            'infomaniak' => ['icon' => 'bi-server',        'color' => 'text-secondary'],
        ];
        try {
            $_sidebarDb = \App\Core\Database::getInstance();
            $_sidebarProviders = $_sidebarDb->fetchAll("SELECT code, name FROM providers ORDER BY name ASC");
        } catch (\Throwable $_e) {
            $_sidebarProviders = [];
        }
        ?>
        <p class="text-uppercase text-body-secondary small fw-semibold px-1 mb-1">Fournisseurs</p>
        <ul class="nav nav-pills flex-column mb-auto">
        <?php foreach ($_sidebarProviders as $_sp): ?>
            <?php
            $_spMeta   = $_sidebarProviderMeta[$_sp['code']] ?? ['icon' => 'bi-box', 'color' => ''];
            $_spActive = isset($_spMeta['prefix']) && str_starts_with($_SERVER['REQUEST_URI'], $_spMeta['prefix']);
            $_spUrl    = $_spMeta['url'] ?? null;
            ?>
            <li class="nav-item">
                <?php if ($_spUrl): ?>
                    <a href="<?= $_spUrl ?>" class="nav-link <?= $_spActive ? 'active' : '' ?>">
                        <i class="bi <?= $_spMeta['icon'] ?> me-2 <?= $_spMeta['color'] ?>"></i><?= htmlspecialchars($_sp['name']) ?>
                    </a>
                <?php else: ?>
                    <a href="#" class="nav-link disabled text-body-secondary">
                        <i class="bi <?= $_spMeta['icon'] ?> me-2"></i><?= htmlspecialchars($_sp['name']) ?>
                        <span class="badge bg-secondary ms-auto small">bientôt</span>
                    </a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
        </ul>

        <hr>

        <!-- Paramètres -->
        <p class="text-uppercase text-body-secondary small fw-semibold px-1 mb-1">Paramètres</p>
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item">
                <a href="/settings/connections" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'], '/settings/connections') ? 'active' : '' ?>">
                    <i class="bi bi-plug me-2"></i>Connexions
                </a>
            </li>
            <li class="nav-item">
                <a href="/settings/normalisation" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'], '/settings/normalisation') ? 'active' : '' ?>">
                    <i class="bi bi-scissors me-2"></i>Normalisation
                </a>
            </li>
            <li class="nav-item">
                <a href="/settings/general" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'], '/settings/general') ? 'active' : '' ?>">
                    <i class="bi bi-sliders me-2"></i>Général
                </a>
            </li>
        </ul>

        <hr>

        <!-- Toggle dark mode -->
        <div class="d-flex align-items-center gap-2 px-1">
            <i class="bi bi-moon-stars-fill small"></i>
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="darkModeToggle" role="switch">
                <label class="form-check-label small" for="darkModeToggle">Mode sombre</label>
            </div>
        </div>

    </nav>
    <!-- ── /Sidebar ────────────────────────────────────────────── -->

    <!-- ── Contenu principal ──────────────────────────────────── -->
    <div class="flex-grow-1 d-flex flex-column" id="main-panel">

        <!-- Topbar -->
        <header class="px-4 py-2 border-bottom d-flex align-items-center justify-content-between">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <?php foreach ($breadcrumbs ?? [] as $label => $url): ?>
                        <?php if ($url): ?>
                            <li class="breadcrumb-item"><a href="<?= htmlspecialchars($url) ?>"><?= htmlspecialchars($label) ?></a></li>
                        <?php else: ?>
                            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($label) ?></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ol>
            </nav>

            <div class="d-flex align-items-center gap-3">
                <span class="small text-body-secondary">
                    <?= date('d/m/Y H:i') ?>
                </span>
            </div>
        </header>

        <!-- Flash messages -->
        <?php
        if (session_status() === PHP_SESSION_NONE) session_start();
        $flashes = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        ?>
        <?php if (!empty($flashes)): ?>
            <div class="px-4 pt-3">
                <?php foreach ($flashes as $type => $messages): ?>
                    <?php foreach ($messages as $msg): ?>
                        <div class="alert alert-<?= htmlspecialchars($type) ?> alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($msg) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Page content -->
        <main class="flex-grow-1 p-4">
            <?= $content ?>
        </main>

        <footer class="px-4 py-2 border-top text-body-secondary small text-center">
            MSP Consolidator v1.0 &mdash; Usage interne uniquement
        </footer>

    </div>
    <!-- ── /Contenu principal ─────────────────────────────────── -->
</div>

<!-- Modal Synchronisation ESET -->
<div class="modal fade" id="syncModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-labelledby="syncModalTitle" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="syncModalTitle">
                    <i class="bi bi-shield-lock text-success me-2"></i>Synchronisation ESET
                </h5>
            </div>
            <div class="modal-body" id="syncModalBody">
                <!-- Contenu injecté par JS -->
            </div>
            <div class="modal-footer">
                <a href="/eset/sync-logs" class="btn btn-outline-secondary btn-sm me-auto">
                    <i class="bi bi-clock-history me-1"></i>Historique
                </a>
                <button type="button" class="btn btn-primary" id="syncModalClose" data-bs-dismiss="modal" disabled>
                    Fermer
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- App JS -->
<script src="/assets/js/app.js"></script>

<script>
// ── Sync modal (générique : ESET, BeCloud, NinjaOne) ────────────────────────
(function () {
    const modalEl    = document.getElementById('syncModal');
    const modalBody  = document.getElementById('syncModalBody');
    const modalClose = document.getElementById('syncModalClose');
    const bsModal    = new bootstrap.Modal(modalEl);
    let elapsedTimer = null;
    let syncDone     = false;

    function esc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function showRunning(providerCode) {
        syncDone = false;
        let seconds = 0;
        const hint = providerCode === 'becloud'
            ? 'Récupération des customers et des abonnements Be-Cloud'
            : providerCode === 'ninjaone'
            ? 'Récupération des organisations et des équipements NinjaOne'
            : 'Récupération des companies et des licences ESET';
        modalBody.innerHTML = `
            <div class="text-center py-3">
                <div class="spinner-border text-primary mb-3" style="width:2.5rem;height:2.5rem" role="status"></div>
                <p class="fw-medium mb-1">Synchronisation en cours…</p>
                <p class="text-body-secondary small" id="syncElapsed">0s</p>
                <p class="text-body-secondary small mt-1">${hint}</p>
            </div>`;
        if (modalClose) modalClose.disabled = true;
        clearInterval(elapsedTimer);
        elapsedTimer = setInterval(() => {
            seconds++;
            const el = document.getElementById('syncElapsed');
            if (el) {
                const m = Math.floor(seconds / 60), s = seconds % 60;
                el.textContent = m > 0 ? `${m}min ${s}s` : `${s}s`;
            }
        }, 1000);
    }

    function showResult(data, providerCode) {
        clearInterval(elapsedTimer);
        if (modalClose) modalClose.disabled = false;
        syncDone = true;

        const s      = data.summary ?? {};
        const errors = s.errors ?? [];
        const ok     = data.status === 'success';

        // Clés selon le provider
        let block1, block2;
        if (providerCode === 'becloud') {
            const cu = s.customers      ?? {};
            const su = s.subscriptions  ?? {};
            block1 = { count: cu.fetched ?? 0, label: 'Customers',      created: cu.created ?? 0, updated: cu.updated ?? 0 };
            block2 = { count: su.fetched ?? 0, label: 'Abonnements',    created: su.created ?? 0, updated: su.updated ?? 0 };
        } else if (providerCode === 'ninjaone') {
            const or = s.organizations ?? {};
            block1 = { count: or.fetched ?? 0,         label: 'Organisations', created: or.created ?? 0, updated: or.updated ?? 0 };
            block2 = { count: s.devices_fetched ?? 0,  label: 'Équipements',   created: 0,               updated: 0 };
        } else {
            const co = s.companies ?? {};
            const li = s.licenses  ?? {};
            block1 = { count: co.fetched ?? 0, label: 'Companies',  created: co.created ?? 0, updated: co.updated ?? 0 };
            block2 = { count: li.fetched ?? 0, label: 'Licences',   created: li.created ?? 0, updated: li.updated ?? 0 };
        }

        // Lien logs dans le footer
        const logsLink = document.querySelector('#syncModal .modal-footer a');
        if (logsLink) logsLink.href = '/' + (providerCode || 'eset') + '/sync-logs';

        let errHtml = '';
        const allErrors = errors.length ? errors : (data.message && !ok ? [data.message] : []);
        if (allErrors.length) {
            errHtml = `<div class="alert alert-danger mt-3 mb-0 small">
                <strong>Erreurs :</strong>
                <ul class="mb-0 mt-1">${allErrors.map(e => `<li>${esc(e)}</li>`).join('')}</ul>
            </div>`;
        }

        modalBody.innerHTML = `
            <div class="text-center mb-3">
                <i class="bi bi-${ok ? 'check-circle-fill text-success' : 'exclamation-triangle-fill text-warning'} display-5"></i>
                <p class="mt-2 mb-0 fw-medium">${ok ? 'Synchronisation réussie' : 'Terminée avec des erreurs'}</p>
            </div>
            <div class="row g-2 text-center">
                <div class="col-6">
                    <div class="border rounded p-2">
                        <div class="fs-3 fw-bold">${block1.count}</div>
                        <div class="small text-body-secondary">${block1.label}</div>
                        <div class="small">
                            <span class="text-success">+${block1.created} créés</span>
                            &nbsp;·&nbsp;
                            <span class="text-info">${block1.updated} màj</span>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="border rounded p-2">
                        <div class="fs-3 fw-bold">${block2.count}</div>
                        <div class="small text-body-secondary">${block2.label}</div>
                        <div class="small">
                            <span class="text-success">+${block2.created} créés</span>
                            &nbsp;·&nbsp;
                            <span class="text-info">${block2.updated} màj</span>
                        </div>
                    </div>
                </div>
            </div>
            ${errHtml}`;
    }

    function showError(msg) {
        clearInterval(elapsedTimer);
        if (modalClose) modalClose.disabled = false;
        syncDone = false;
        modalBody.innerHTML = `
            <div class="text-center py-3">
                <i class="bi bi-x-circle-fill text-danger display-5 d-block mb-2"></i>
                <p class="fw-medium mb-1">Erreur de synchronisation</p>
                <p class="text-body-secondary small">${esc(msg)}</p>
            </div>`;
    }

    // Fonction globale — appelable depuis n'importe quelle page
    // connectionId (optionnel) : si fourni, sync uniquement cette connexion
    // providerCode (optionnel) : 'eset' (défaut) ou 'becloud'
    window.openSyncModal = function (connectionId, providerCode) {
        providerCode = providerCode || 'eset';
        const syncUrl   = '/' + providerCode + '/sync';
        const statusUrl = '/' + providerCode + '/sync-status';

        // Adapter le titre de la modal
        const titleEl = document.getElementById('syncModalTitle');
        if (titleEl) {
            if (providerCode === 'becloud') {
                titleEl.innerHTML = '<i class="bi bi-cloud-check text-info me-2"></i>Synchronisation Be-Cloud';
            } else if (providerCode === 'ninjaone') {
                titleEl.innerHTML = '<i class="bi bi-hdd-network text-warning me-2"></i>Synchronisation NinjaOne';
            } else {
                titleEl.innerHTML = '<i class="bi bi-shield-lock text-success me-2"></i>Synchronisation ESET';
            }
        }

        showRunning(providerCode);
        bsModal.show();

        const body = new FormData();
        if (connectionId) body.append('connection_id', connectionId);

        fetch(syncUrl, { method: 'POST', body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'already_running') {
                    showError(data.message);
                } else {
                    showResult(data, providerCode);
                }
            })
            .catch(err => {
                showError('Erreur réseau : ' + (err.message || err));
            });
    };

    // Fermeture de la modal → rechargement si sync réussie
    modalEl?.addEventListener('hidden.bs.modal', () => {
        if (syncDone) location.reload();
    });
})();
</script>
</body>
</html>
