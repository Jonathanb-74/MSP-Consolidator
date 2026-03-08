<?php
/** @var array $providers  [ ['provider'=>[...], 'connections'=>[...], 'has_config'=>bool], ... ] */

$providerIcons = [
    'eset'       => ['icon' => 'bi-shield-lock',  'color' => 'text-success'],
    'becloud'    => ['icon' => 'bi-cloud-check',  'color' => 'text-info'],
    'ninjaone'   => ['icon' => 'bi-hdd-network',  'color' => 'text-warning'],
    'wasabi'     => ['icon' => 'bi-cloud',         'color' => 'text-warning'],
    'veeam'      => ['icon' => 'bi-archive',       'color' => 'text-primary'],
    'infomaniak' => ['icon' => 'bi-server',        'color' => 'text-secondary'],
];

function relativeTime(?string $dt): string {
    if (!$dt) return '—';
    $diff = time() - strtotime($dt);
    if ($diff < 60)    return "à l'instant";
    if ($diff < 3600)  return 'il y a ' . floor($diff / 60) . ' min';
    if ($diff < 86400) return 'il y a ' . floor($diff / 3600) . 'h';
    return 'il y a ' . floor($diff / 86400) . 'j';
}

function syncStatusBadge(?string $status): string {
    return match($status) {
        'running' => '<span class="badge bg-primary"><span class="spinner-border spinner-border-sm" style="width:.55em;height:.55em"></span> En cours</span>',
        'success' => '<span class="badge bg-success">OK</span>',
        'error'   => '<span class="badge bg-danger">Erreur</span>',
        default   => '<span class="badge bg-secondary">Inactif</span>',
    };
}
?>

<div class="page-sticky-top">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-0"><i class="bi bi-plug me-2 text-body-secondary"></i>Connexions fournisseurs</h4>
            <p class="text-body-secondary small mb-0 mt-1">
                Les connexions sont définies dans <code>config/providers.php</code>.
                Cette page affiche leur état et permet de les renommer.
            </p>
        </div>
        <button class="btn btn-outline-secondary btn-sm" id="btnSyncConfig">
            <i class="bi bi-arrow-repeat me-1"></i>Synchroniser depuis la config
        </button>
    </div>
</div>

<?php foreach ($providers as $providerData): ?>
    <?php
    $provider = $providerData['provider'];
    $code     = $provider['code'];
    $icon     = $providerIcons[$code]['icon']  ?? 'bi-box';
    $color    = $providerIcons[$code]['color'] ?? '';
    $conns    = $providerData['connections'];
    ?>

    <div class="card mb-4">
        <div class="card-header d-flex align-items-center gap-2">
            <i class="bi <?= $icon ?> <?= $color ?> fs-5"></i>
            <span class="fw-semibold"><?= htmlspecialchars($provider['name']) ?></span>
            <span class="text-body-secondary small ms-1 me-auto">(<?= htmlspecialchars($code) ?>)</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($conns)): ?>
                <div class="text-body-secondary small p-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Aucune connexion définie dans <code>config/providers.php</code>.
                </div>
            <?php else: ?>
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Nom</th>
                            <th>Clé config</th>
                            <th>Statut</th>
                            <th>Dernière sync</th>
                            <th>Sync</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($conns as $row): ?>
                        <?php $db = $row['db']; ?>
                        <tr>
                            <td>
                                <span class="fw-medium conn-name-display" data-id="<?= $db ? (int)$db['id'] : '' ?>">
                                    <?= htmlspecialchars($db['name'] ?? $row['config_name']) ?>
                                </span>
                                <form class="conn-rename-form d-none d-flex gap-1 align-items-center"
                                      data-id="<?= $db ? (int)$db['id'] : '' ?>">
                                    <input type="text" class="form-control form-control-sm"
                                           value="<?= htmlspecialchars($db['name'] ?? $row['config_name']) ?>"
                                           style="max-width:200px">
                                    <button type="submit" class="btn btn-sm btn-success py-0 px-1">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-secondary py-0 px-1 btn-rename-cancel">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </form>
                            </td>
                            <td><code class="small"><?= htmlspecialchars($row['config_key']) ?></code></td>
                            <td>
                                <?php if (!$db): ?>
                                    <span class="badge bg-warning text-dark">Non enregistrée</span>
                                <?php elseif (!$db['is_enabled']): ?>
                                    <span class="badge bg-secondary">Désactivée</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Active</span>
                                <?php endif; ?>
                                <?php if (!($row['config_enabled'] ?? true)): ?>
                                    <span class="badge bg-secondary ms-1">disabled dans config</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-body-secondary">
                                <?= $db ? relativeTime($db['last_sync_at']) : '—' ?>
                                <?php if ($db && $db['last_sync_at']): ?>
                                    <span class="ms-1"><?= syncStatusBadge($db['sync_status'] ?? null) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($db && $db['is_enabled'] && in_array($code, ['eset', 'becloud', 'ninjaone'])): ?>
                                    <button class="btn btn-sm btn-outline-primary py-0 px-2 btn-sync-conn"
                                            data-connection-id="<?= (int)$db['id'] ?>"
                                            data-provider-code="<?= htmlspecialchars($code) ?>"
                                            data-connection-name="<?= htmlspecialchars($db['name']) ?>">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                <?php elseif ($db): ?>
                                    <span class="text-body-secondary small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($db): ?>
                                    <button class="btn btn-sm btn-outline-secondary py-0 px-1 btn-rename"
                                            data-id="<?= (int)$db['id'] ?>" title="Renommer">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="text-body-secondary small" title="Synchro config requise">
                                        <i class="bi bi-exclamation-circle"></i>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php if (in_array($code, ['eset', 'becloud', 'ninjaone'])): ?>
        <div class="card-footer text-body-secondary small">
            <i class="bi bi-info-circle me-1"></i>
            Pour ajouter une connexion, éditez <code>config/providers.php</code>,
            puis cliquez sur <strong>Synchroniser depuis la config</strong>.
        </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<script>
(function () {
    // ── Sync depuis config ─────────────────────────────────────────────────
    document.getElementById('btnSyncConfig')?.addEventListener('click', function () {
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:.75em;height:.75em"></span> Sync…';
        fetch('/settings/connections/sync-config', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            alert(data.message ?? 'Synchronisation effectuée.');
            location.reload();
        })
        .catch(() => {
            alert('Erreur lors de la synchronisation.');
            location.reload();
        });
    });

    // ── Renommer ──────────────────────────────────────────────────────────
    document.querySelectorAll('.btn-rename').forEach(btn => {
        btn.addEventListener('click', function () {
            const id   = this.dataset.id;
            const row  = this.closest('tr');
            row.querySelector('.conn-name-display').classList.add('d-none');
            row.querySelector('.conn-rename-form').classList.remove('d-none');
            row.querySelector('.conn-rename-form input').focus();
            this.classList.add('d-none');
        });
    });

    document.querySelectorAll('.btn-rename-cancel').forEach(btn => {
        btn.addEventListener('click', function () {
            const row = this.closest('tr');
            row.querySelector('.conn-name-display').classList.remove('d-none');
            row.querySelector('.conn-rename-form').classList.add('d-none');
            row.querySelector('.btn-rename').classList.remove('d-none');
        });
    });

    document.querySelectorAll('.conn-rename-form').forEach(form => {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const id   = this.dataset.id;
            const name = this.querySelector('input').value.trim();
            if (!name) return;

            const fd = new FormData();
            fd.append('id',   id);
            fd.append('name', name);

            fetch('/settings/connections/rename', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'ok') {
                    const row = this.closest('tr');
                    row.querySelector('.conn-name-display').textContent = data.name;
                    row.querySelector('.conn-name-display').classList.remove('d-none');
                    this.classList.add('d-none');
                    row.querySelector('.btn-rename').classList.remove('d-none');
                } else {
                    alert(data.message ?? 'Erreur.');
                }
            })
            .catch(() => alert('Erreur réseau.'));
        });
    });

    // ── Sync par connexion ────────────────────────────────────────────────
    document.querySelectorAll('.btn-sync-conn').forEach(btn => {
        btn.addEventListener('click', function () {
            const connectionId   = this.dataset.connectionId;
            const providerCode   = this.dataset.providerCode || 'eset';
            if (typeof window.openSyncModal === 'function') {
                window.openSyncModal(parseInt(connectionId), providerCode);
            }
        });
    });
})();
</script>
