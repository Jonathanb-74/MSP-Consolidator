<?php
/**
 * @var array $settings  Indexé par clé : ['key'=>str, 'value'=>str, 'label'=>str, 'description'=>str, 'type'=>str]
 */
?>

<div class="page-sticky-top">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-0"><i class="bi bi-sliders me-2 text-body-secondary"></i>Paramètres généraux</h4>
            <p class="text-body-secondary small mb-0 mt-1">
                Réglages globaux de l'application, applicables à tous les fournisseurs.
            </p>
        </div>
    </div>
</div>

<div id="alertZone"></div>

<div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-hdd-network text-warning fs-5"></i>
        <span class="fw-semibold">Équipements</span>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th class="ps-3" style="width:40%">Paramètre</th>
                    <th>Valeur</th>
                    <th style="width:80px"></th>
                </tr>
            </thead>
            <tbody>
<?php
$deviceSettings = ['device_active_days'];
foreach ($deviceSettings as $k):
    if (!isset($settings[$k])) continue;
    $s = $settings[$k];
?>
                <tr id="row-<?= htmlspecialchars($k) ?>">
                    <td class="ps-3">
                        <div class="fw-semibold small"><?= htmlspecialchars($s['label']) ?></div>
                        <?php if ($s['description']): ?>
                        <div class="text-body-secondary" style="font-size:.8em"><?= htmlspecialchars($s['description']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="value-display" id="display-<?= htmlspecialchars($k) ?>">
                            <strong><?= htmlspecialchars($s['value']) ?></strong>
                            <?= $s['type'] === 'integer' ? '<span class="text-body-secondary small ms-1">jours</span>' : '' ?>
                        </span>
                        <span class="value-edit d-none" id="edit-<?= htmlspecialchars($k) ?>">
                            <div class="d-flex align-items-center gap-2">
                                <?php if ($s['type'] === 'integer'): ?>
                                <input type="number" class="form-control form-control-sm" min="0" max="365"
                                       style="width:80px"
                                       id="input-<?= htmlspecialchars($k) ?>"
                                       value="<?= htmlspecialchars($s['value']) ?>">
                                <span class="text-body-secondary small">jours</span>
                                <?php elseif ($s['type'] === 'boolean'): ?>
                                <select class="form-select form-select-sm" style="width:120px"
                                        id="input-<?= htmlspecialchars($k) ?>">
                                    <option value="1" <?= $s['value'] === '1' ? 'selected' : '' ?>>Oui</option>
                                    <option value="0" <?= $s['value'] !== '1' ? 'selected' : '' ?>>Non</option>
                                </select>
                                <?php else: ?>
                                <input type="text" class="form-control form-control-sm"
                                       style="max-width:240px"
                                       id="input-<?= htmlspecialchars($k) ?>"
                                       value="<?= htmlspecialchars($s['value']) ?>">
                                <?php endif; ?>
                                <button class="btn btn-primary btn-sm"
                                        onclick="saveSettings('<?= htmlspecialchars($k) ?>')">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                                <button class="btn btn-outline-secondary btn-sm"
                                        onclick="cancelEdit('<?= htmlspecialchars($k) ?>')">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        </span>
                    </td>
                    <td class="text-end pe-3">
                        <button class="btn btn-outline-secondary btn-sm py-0"
                                title="Modifier"
                                onclick="startEdit('<?= htmlspecialchars($k) ?>')">
                            <i class="bi bi-pencil"></i>
                        </button>
                    </td>
                </tr>
<?php endforeach; ?>
<?php if (empty(array_filter($deviceSettings, fn($k) => isset($settings[$k])))): ?>
                <tr>
                    <td colspan="3" class="text-center text-body-secondary py-3">
                        <i class="bi bi-info-circle me-1"></i>
                        Exécutez la migration <code>006_app_settings.sql</code> pour initialiser les paramètres.
                    </td>
                </tr>
<?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$maxExec    = (int)ini_get('max_execution_time');
$maxInput   = (int)ini_get('max_input_time');
$memLimit   = ini_get('memory_limit');
$uploadMax  = ini_get('upload_max_filesize');
$postMax    = ini_get('post_max_size');
$sapi       = PHP_SAPI;
$serverSw   = $_SERVER['SERVER_SOFTWARE'] ?? '—';
$htaccessOk = $maxExec === 0 || $maxExec > 30;
?>

<div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-cpu text-body-secondary fs-5"></i>
        <span class="fw-semibold">Informations techniques</span>
    </div>
    <div class="card-body">

        <div class="alert alert-<?= $htaccessOk ? 'success' : 'warning' ?> d-flex align-items-center gap-2 py-2 mb-3">
            <i class="bi bi-<?= $htaccessOk ? 'check-circle-fill' : 'exclamation-triangle-fill' ?>"></i>
            <?php if ($htaccessOk): ?>
                <span><strong>.htaccess pris en compte</strong> — <code>max_execution_time</code> = <?= $maxExec === 0 ? '0 (illimité)' : $maxExec . 's' ?></span>
            <?php else: ?>
                <span><strong>.htaccess ignoré ou non appliqué</strong> — <code>max_execution_time</code> = <?= $maxExec ?>s. La synchronisation risque d'être interrompue.</span>
            <?php endif; ?>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <h6 class="text-body-secondary text-uppercase small fw-semibold mb-2">Configuration PHP</h6>
                <table class="table table-sm table-bordered small mb-0">
                    <tbody>
                        <tr>
                            <td class="text-body-secondary" style="width:55%">Version</td>
                            <td><code><?= htmlspecialchars(PHP_VERSION) ?></code></td>
                        </tr>
                        <tr>
                            <td class="text-body-secondary">SAPI</td>
                            <td>
                                <code><?= htmlspecialchars($sapi) ?></code>
                                <?php if ($sapi === 'apache2handler'): ?>
                                    <span class="badge bg-success ms-1">mod_php</span>
                                <?php elseif (str_contains($sapi, 'fpm')): ?>
                                    <span class="badge bg-info ms-1">PHP-FPM</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr class="<?= !$htaccessOk ? 'table-warning' : '' ?>">
                            <td class="text-body-secondary">max_execution_time</td>
                            <td>
                                <code><?= $maxExec === 0 ? '0 (illimité)' : $maxExec . 's' ?></code>
                                <?php if (!$htaccessOk): ?>
                                    <span class="badge bg-warning text-dark ms-1">Trop court</span>
                                <?php else: ?>
                                    <span class="badge bg-success ms-1">OK</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-body-secondary">max_input_time</td>
                            <td><code><?= $maxInput === -1 ? '-1 (illimité)' : $maxInput . 's' ?></code></td>
                        </tr>
                        <tr>
                            <td class="text-body-secondary">memory_limit</td>
                            <td><code><?= htmlspecialchars($memLimit) ?></code></td>
                        </tr>
                        <tr>
                            <td class="text-body-secondary">upload_max_filesize</td>
                            <td><code><?= htmlspecialchars($uploadMax) ?></code></td>
                        </tr>
                        <tr>
                            <td class="text-body-secondary">post_max_size</td>
                            <td><code><?= htmlspecialchars($postMax) ?></code></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="col-md-6">
                <h6 class="text-body-secondary text-uppercase small fw-semibold mb-2">Serveur</h6>
                <table class="table table-sm table-bordered small mb-0">
                    <tbody>
                        <tr>
                            <td class="text-body-secondary" style="width:45%">Software</td>
                            <td><code><?= htmlspecialchars($serverSw) ?></code></td>
                        </tr>
                        <tr>
                            <td class="text-body-secondary">Document root</td>
                            <td><code><?= htmlspecialchars($_SERVER['DOCUMENT_ROOT'] ?? '—') ?></code></td>
                        </tr>
                        <tr>
                            <td class="text-body-secondary">APP_ROOT</td>
                            <td><code><?= htmlspecialchars(defined('APP_ROOT') ? APP_ROOT : '—') ?></code></td>
                        </tr>
                        <tr>
                            <td class="text-body-secondary">Heure serveur</td>
                            <td><code><?= date('Y-m-d H:i:s T') ?></code></td>
                        </tr>
                    </tbody>
                </table>
                <?php if ($sapi !== 'apache2handler'): ?>
                <div class="alert alert-info mt-3 mb-0 small">
                    <i class="bi bi-info-circle me-1"></i>
                    SAPI <strong><?= htmlspecialchars($sapi) ?></strong> — les directives <code>php_value</code> dans <code>.htaccess</code> ne fonctionnent qu'avec <code>mod_php</code>. Avec PHP-FPM, configure <code>max_execution_time</code> dans <code>php.ini</code> ou le pool FPM.
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
function startEdit(key) {
    document.getElementById('display-' + key).classList.add('d-none');
    document.getElementById('edit-' + key).classList.remove('d-none');
    document.getElementById('input-' + key)?.focus();
}

function cancelEdit(key) {
    document.getElementById('display-' + key).classList.remove('d-none');
    document.getElementById('edit-' + key).classList.add('d-none');
}

async function saveSettings(key) {
    const input = document.getElementById('input-' + key);
    const value = input?.value ?? '';

    const res  = await fetch('/settings/general/update', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ key, value }),
    });
    const data = await res.json();

    if (!res.ok) {
        showAlert('❌ ' + data.message, 'danger');
        return;
    }

    // Mettre à jour l'affichage
    const display = document.getElementById('display-' + key);
    const strong  = display.querySelector('strong');
    if (strong) strong.textContent = value;

    cancelEdit(key);
    showAlert('✅ Paramètre enregistré.');
}

function showAlert(msg, type = 'success') {
    const z = document.getElementById('alertZone');
    z.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${msg}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
    setTimeout(() => z.querySelector('.alert')?.classList.remove('show'), 3000);
}
</script>
