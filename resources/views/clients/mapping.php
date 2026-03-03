<?php
/** @var array $mappings */
/** @var array $unmapped */
/** @var array $clients */
/** @var string $provider */
/** @var array $providerRow */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var string $search */
/** @var string $confirmed */
/** @var int|null $minScore */
/** @var array $autoConfirmPreview */
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0">
        Mapping — <?= htmlspecialchars($providerRow['name']) ?>
        <span class="badge bg-secondary ms-2"><?= number_format($total) ?></span>
    </h1>
</div>

<!-- Filtres -->
<form method="GET" action="/mapping" class="row g-2 mb-3">
    <input type="hidden" name="provider" value="<?= htmlspecialchars($provider) ?>">
    <div class="col-md-4">
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" name="search" class="form-control"
                   placeholder="Company ESET, client, numéro…"
                   value="<?= htmlspecialchars($search) ?>">
        </div>
    </div>
    <div class="col-md-2">
        <select name="confirmed" class="form-select">
            <option value="">Tous</option>
            <option value="0" <?= $confirmed === '0' ? 'selected' : '' ?>>Non confirmés</option>
            <option value="1" <?= $confirmed === '1' ? 'selected' : '' ?>>Confirmés</option>
        </select>
    </div>
    <div class="col-md-2">
        <div class="input-group">
            <span class="input-group-text">Score ≥</span>
            <input type="number" name="min_score" class="form-control"
                   min="0" max="100" placeholder="—"
                   value="<?= $minScore !== null ? $minScore : '' ?>">
            <span class="input-group-text">%</span>
        </div>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-outline-secondary">Filtrer</button>
        <a href="/mapping" class="btn btn-outline-secondary">Réinitialiser</a>
    </div>
</form>

<!-- Barre d'action bulk (masquée par défaut) -->
<div id="bulkActionBar" class="alert alert-primary py-2 px-3 d-none d-flex align-items-center gap-3 sticky-top mb-3" style="top:60px;z-index:1020">
    <i class="bi bi-check2-square"></i>
    <span><strong id="selectedCount">0</strong> mapping(s) sélectionné(s)</span>
    <button id="btnConfirmSelected" class="btn btn-sm btn-success ms-auto">
        <i class="bi bi-check-all me-1"></i>Confirmer la sélection
    </button>
    <button id="btnDeselectAll" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-x"></i> Désélectionner
    </button>
</div>

<div class="row g-4">

    <!-- Mappings existants -->
    <div class="col-lg-8">
        <h5 class="mb-3">Mappings existants</h5>
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle" id="mappingTable">
                <thead class="table-dark">
                    <tr>
                        <th style="width:36px">
                            <input type="checkbox" id="selectAll" class="form-check-input" title="Tout sélectionner">
                        </th>
                        <th>Company fournisseur</th>
                        <th>Client interne</th>
                        <th>Méthode</th>
                        <th class="text-center" style="width:80px">Score</th>
                        <th class="text-center" style="width:90px">Confirmé</th>
                        <th class="text-center" style="width:60px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($mappings)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-body-secondary py-4">Aucun mapping trouvé.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($mappings as $m): ?>
                    <?php
                        $score = $m['match_score'] !== null ? (int)$m['match_score'] : null;
                        $scoreBadge = match(true) {
                            $score === null            => ['—', 'secondary'],
                            $score >= 90               => [$score . '%', 'success'],
                            $score >= 70               => [$score . '%', 'warning'],
                            default                    => [$score . '%', 'danger'],
                        };
                    ?>
                    <tr class="<?= !$m['is_confirmed'] ? 'table-warning' : '' ?>">
                        <td>
                            <?php if (!$m['is_confirmed']): ?>
                            <input type="checkbox" class="form-check-input mapping-checkbox"
                                   value="<?= $m['id'] ?>">
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="fw-medium"><?= htmlspecialchars($m['provider_client_name'] ?? $m['provider_client_id']) ?></span>
                            <br><small class="text-body-secondary font-monospace"><?= htmlspecialchars($m['provider_client_id']) ?></small>
                        </td>
                        <td>
                            <?= htmlspecialchars($m['client_name']) ?>
                            <br><small class="text-body-secondary"><code><?= htmlspecialchars($m['client_number']) ?></code>
                            <span class="badge bg-secondary"><?= htmlspecialchars($m['structure_code']) ?></span></small>
                        </td>
                        <td>
                            <?php
                            $methodLabels = [
                                'manual'        => ['label' => 'Manuel',    'class' => 'primary'],
                                'client_number' => ['label' => 'N° client', 'class' => 'success'],
                                'name_match'    => ['label' => 'Nom',       'class' => 'info'],
                            ];
                            $ml = $methodLabels[$m['mapping_method']] ?? ['label' => $m['mapping_method'], 'class' => 'secondary'];
                            ?>
                            <span class="badge bg-<?= $ml['class'] ?>"><?= $ml['label'] ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-<?= $scoreBadge[1] ?> <?= $score === null ? 'text-body-secondary' : '' ?>">
                                <?= $scoreBadge[0] ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <?php if ($m['is_confirmed']): ?>
                                <i class="bi bi-check-circle-fill text-success" title="Confirmé"></i>
                            <?php else: ?>
                                <button class="btn btn-xs btn-outline-success btn-confirm-mapping"
                                        data-mapping-id="<?= $m['id'] ?>"
                                        data-client-id="<?= $m['client_id'] ?>"
                                        data-provider-client-id="<?= htmlspecialchars($m['provider_client_id']) ?>"
                                        data-provider="<?= htmlspecialchars($provider) ?>"
                                        title="Confirmer">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-danger btn-unlink-mapping"
                                    data-mapping-id="<?= $m['id'] ?>" title="Supprimer">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total > $perPage): ?>
        <nav>
            <ul class="pagination pagination-sm">
                <?php for ($p = 1; $p <= ceil($total / $perPage); $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?provider=<?= urlencode($provider) ?>&search=<?= urlencode($search) ?>&confirmed=<?= urlencode($confirmed) ?>&min_score=<?= $minScore ?? '' ?>&page=<?= $p ?>">
                        <?= $p ?>
                    </a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>

    <!-- Panneau latéral -->
    <div class="col-lg-4">

        <!-- Validation automatique par score -->
        <div class="card border-0 bg-body-secondary mb-3">
            <div class="card-header bg-transparent border-0">
                <h6 class="mb-0"><i class="bi bi-magic me-2"></i>Validation par score</h6>
            </div>
            <div class="card-body">
                <p class="small text-body-secondary mb-3">
                    Confirme automatiquement tous les mappings non validés dont le score est supérieur ou égal au seuil choisi.
                </p>

                <!-- Preview des seuils -->
                <div class="mb-3">
                    <?php foreach ($autoConfirmPreview as $t => $cnt): ?>
                    <div class="d-flex align-items-center justify-content-between small mb-1">
                        <span>
                            <span class="badge bg-<?= $t >= 90 ? 'success' : ($t >= 70 ? 'warning' : 'danger') ?> me-1"><?= $t ?>%</span>
                        </span>
                        <span class="text-body-secondary"><?= $cnt ?> à confirmer</span>
                        <button class="btn btn-xs btn-outline-success btn-auto-confirm-threshold"
                                data-threshold="<?= $t ?>"
                                data-provider="<?= htmlspecialchars($provider) ?>"
                                <?= $cnt === 0 ? 'disabled' : '' ?>>
                            <i class="bi bi-check2-all"></i>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>

                <hr class="my-2">

                <!-- Seuil personnalisé -->
                <div class="input-group input-group-sm mb-2">
                    <span class="input-group-text">Score ≥</span>
                    <input type="number" id="customThreshold" class="form-control"
                           value="80" min="0" max="100" step="5">
                    <span class="input-group-text">%</span>
                </div>
                <button id="btnAutoConfirmCustom" class="btn btn-sm btn-primary w-100"
                        data-provider="<?= htmlspecialchars($provider) ?>">
                    <i class="bi bi-check2-all me-1"></i>Valider ce seuil
                </button>
            </div>
        </div>

        <!-- Liaison manuelle -->
        <div class="card border-0 bg-body-secondary">
            <div class="card-header bg-transparent border-0">
                <h6 class="mb-0"><i class="bi bi-link-45deg me-2"></i>Liaison manuelle</h6>
            </div>
            <div class="card-body">
                <form id="formManualLink">
                    <div class="mb-3">
                        <label class="form-label small">Company <?= htmlspecialchars($providerRow['name']) ?></label>
                        <select name="provider_client_id" class="form-select form-select-sm" required>
                            <option value="">Sélectionner une company…</option>
                            <?php foreach ($unmapped as $u): ?>
                            <option value="<?= htmlspecialchars($u['eset_company_id']) ?>">
                                <?= htmlspecialchars($u['name']) ?>
                                <?= $u['custom_identifier'] ? ' [' . htmlspecialchars($u['custom_identifier']) . ']' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Client interne</label>
                        <select name="client_id" class="form-select form-select-sm" required>
                            <option value="">Sélectionner un client…</option>
                            <?php foreach ($clients as $c): ?>
                            <option value="<?= $c['id'] ?>">
                                [<?= htmlspecialchars($c['structure_code']) ?>]
                                <?= htmlspecialchars($c['name']) ?>
                                (<?= htmlspecialchars($c['client_number']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Notes (optionnel)</label>
                        <input type="text" name="notes" class="form-control form-control-sm"
                               placeholder="Ex : mapping manuel suite à migration">
                    </div>
                    <input type="hidden" name="provider" value="<?= htmlspecialchars($provider) ?>">
                    <button type="submit" class="btn btn-sm btn-primary w-100">
                        <i class="bi bi-link me-1"></i>Lier
                    </button>
                </form>
            </div>
        </div>

        <?php if (!empty($unmapped)): ?>
        <div class="alert alert-warning mt-3 small">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <strong><?= count($unmapped) ?></strong> companie(s) sans mapping.
        </div>
        <?php endif; ?>
    </div>

</div>

<script>
// ── Sélection et bulk confirm ───────────────────────────────────────────────

const bulkBar       = document.getElementById('bulkActionBar');
const selectedCount = document.getElementById('selectedCount');
const selectAll     = document.getElementById('selectAll');

function updateBulkBar() {
    const checked = document.querySelectorAll('.mapping-checkbox:checked');
    const n = checked.length;
    selectedCount.textContent = n;
    bulkBar.classList.toggle('d-none', n === 0);
    bulkBar.classList.toggle('d-flex', n > 0);
}

selectAll?.addEventListener('change', function () {
    document.querySelectorAll('.mapping-checkbox').forEach(cb => cb.checked = this.checked);
    updateBulkBar();
});

document.querySelectorAll('.mapping-checkbox').forEach(cb => {
    cb.addEventListener('change', function () {
        if (!this.checked) selectAll.checked = false;
        updateBulkBar();
    });
});

document.getElementById('btnDeselectAll')?.addEventListener('click', function () {
    document.querySelectorAll('.mapping-checkbox').forEach(cb => cb.checked = false);
    if (selectAll) selectAll.checked = false;
    updateBulkBar();
});

document.getElementById('btnConfirmSelected')?.addEventListener('click', function () {
    const ids = [...document.querySelectorAll('.mapping-checkbox:checked')].map(cb => cb.value);
    if (!ids.length) return;
    if (!confirm(`Confirmer ${ids.length} mapping(s) ?`)) return;

    const data = new FormData();
    data.append('mapping_ids', ids.join(','));

    fetch('/mapping/confirm-bulk', { method: 'POST', body: data })
        .then(r => r.json())
        .then(d => {
            alert(d.success ? '✅ ' + d.message : '❌ ' + d.message);
            if (d.success) location.reload();
        })
        .catch(() => alert('❌ Erreur réseau'));
});

// ── Validation automatique par seuil ───────────────────────────────────────

function autoConfirm(threshold, providerCode) {
    if (!confirm(`Confirmer tous les mappings avec un score ≥ ${threshold}% ?`)) return;

    const data = new FormData();
    data.append('threshold', threshold);
    data.append('provider', providerCode);

    fetch('/mapping/auto-confirm', { method: 'POST', body: data })
        .then(r => r.json())
        .then(d => {
            alert(d.success ? '✅ ' + d.message : '❌ ' + d.message);
            if (d.success) location.reload();
        })
        .catch(() => alert('❌ Erreur réseau'));
}

document.querySelectorAll('.btn-auto-confirm-threshold').forEach(btn => {
    btn.addEventListener('click', function () {
        autoConfirm(this.dataset.threshold, this.dataset.provider);
    });
});

document.getElementById('btnAutoConfirmCustom')?.addEventListener('click', function () {
    const threshold = document.getElementById('customThreshold').value;
    autoConfirm(threshold, this.dataset.provider);
});

// ── Liaison manuelle ────────────────────────────────────────────────────────

document.getElementById('formManualLink').addEventListener('submit', function(e) {
    e.preventDefault();
    const data = new FormData(this);
    fetch('/mapping/link', { method: 'POST', body: data })
        .then(r => r.json())
        .then(d => { alert(d.success ? '✅ ' + d.message : '❌ ' + d.message); if (d.success) location.reload(); })
        .catch(() => alert('❌ Erreur réseau'));
});

// ── Confirmer mapping individuel ────────────────────────────────────────────

document.querySelectorAll('.btn-confirm-mapping').forEach(btn => {
    btn.addEventListener('click', function() {
        const data = new FormData();
        data.append('client_id', this.dataset.clientId);
        data.append('provider_client_id', this.dataset.providerClientId);
        data.append('provider', this.dataset.provider);
        fetch('/mapping/link', { method: 'POST', body: data })
            .then(r => r.json())
            .then(d => { if (d.success) location.reload(); else alert('❌ ' + d.message); });
    });
});

// ── Supprimer mapping ───────────────────────────────────────────────────────

document.querySelectorAll('.btn-unlink-mapping').forEach(btn => {
    btn.addEventListener('click', function() {
        if (!confirm('Supprimer ce mapping ?')) return;
        const data = new FormData();
        data.append('mapping_id', this.dataset.mappingId);
        fetch('/mapping/unlink', { method: 'POST', body: data })
            .then(r => r.json())
            .then(d => { if (d.success) location.reload(); else alert('❌ ' + d.message); });
    });
});
</script>
