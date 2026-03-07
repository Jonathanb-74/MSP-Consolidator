<?php
/** @var array $entities */
/** @var array $clients */
/** @var string $provider */
/** @var array $providerRow */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var string $search */
/** @var string $confirmed */
/** @var array $autoConfirmPreview */
/** @var string $sortBy */
/** @var string $sortDir */

function mSortLink(string $col, string $cur, string $dir, string $label, array $qp): string {
    $d = ($cur === $col && $dir === 'ASC') ? 'DESC' : 'ASC';
    $i = $cur === $col ? ($dir === 'ASC' ? ' ↑' : ' ↓') : '';
    return '<a href="/mapping?' . http_build_query(array_merge($qp, ['sort' => $col, 'dir' => $d])) . '" class="text-white text-decoration-none">'
         . htmlspecialchars($label) . $i . '</a>';
}

$qp = ['provider' => $provider, 'search' => $search, 'confirmed' => $confirmed, 'perPage' => $perPage];

$providerTabs = [
    'eset'     => ['label' => 'ESET',     'icon' => 'bi-shield-lock', 'color' => 'text-success'],
    'becloud'  => ['label' => 'Be-Cloud', 'icon' => 'bi-cloud-check', 'color' => 'text-info'],
    'ninjaone' => ['label' => 'NinjaOne', 'icon' => 'bi-hdd-network', 'color' => 'text-warning'],
];

$isUnmappedTab = ($confirmed === '0');
?>

<div class="page-sticky-top">
<div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">
        <i class="bi bi-link-45deg me-2"></i>Mapping fournisseurs
        <span class="badge bg-secondary ms-2"><?= number_format($total) ?></span>
    </h1>
</div>

<!-- Onglets providers -->
<ul class="nav nav-tabs mb-3">
    <?php foreach ($providerTabs as $code => $tab): ?>
    <li class="nav-item">
        <a class="nav-link <?= $provider === $code ? 'active' : '' ?>"
           href="/mapping?provider=<?= $code ?>">
            <i class="bi <?= $tab['icon'] ?> <?= $tab['color'] ?> me-1"></i><?= $tab['label'] ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<!-- Onglets À mapper / Confirmés -->
<div class="d-flex align-items-center justify-content-between mb-3">
    <div class="btn-group" role="group">
        <a href="?<?= http_build_query(array_merge($qp, ['confirmed' => '0', 'page' => 1])) ?>"
           class="btn btn-sm <?= $isUnmappedTab ? 'btn-warning' : 'btn-outline-warning' ?>">
            <i class="bi bi-exclamation-circle me-1"></i>À mapper
            <?php if ($isUnmappedTab): ?>
            <span class="badge bg-dark ms-1"><?= number_format($total) ?></span>
            <?php endif; ?>
        </a>
        <a href="?<?= http_build_query(array_merge($qp, ['confirmed' => '1', 'page' => 1])) ?>"
           class="btn btn-sm <?= !$isUnmappedTab ? 'btn-success' : 'btn-outline-success' ?>">
            <i class="bi bi-check-circle me-1"></i>Confirmés
            <?php if (!$isUnmappedTab): ?>
            <span class="badge bg-dark ms-1"><?= number_format($total) ?></span>
            <?php endif; ?>
        </a>
    </div>

    <?php if ($isUnmappedTab): ?>
    <div class="d-flex gap-2">
        <button id="btnSaveAll" class="btn btn-sm btn-primary d-none">
            <i class="bi bi-check-all me-1"></i>Sauvegarder tout
            <span id="saveAllCount" class="badge bg-light text-dark ms-1">0</span>
        </button>
        <button class="btn btn-sm btn-outline-primary" type="button"
                data-bs-toggle="collapse" data-bs-target="#autoConfirmPanel">
            <i class="bi bi-magic me-1"></i>Auto-confirmer
        </button>
    </div>
    <?php endif; ?>
</div>

<!-- Panel auto-confirm (collapsible, seulement sur l'onglet À mapper) -->
<?php if ($isUnmappedTab): ?>
<div class="collapse mb-3" id="autoConfirmPanel">
    <div class="card border-0 bg-body-secondary">
        <div class="card-body py-2">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <span class="small text-body-secondary me-2">Confirmer tous les mappings avec score ≥ :</span>
                <?php foreach ($autoConfirmPreview as $t => $cnt): ?>
                <button class="btn btn-sm btn-outline-<?= $t >= 90 ? 'success' : ($t >= 70 ? 'warning' : 'danger') ?> btn-auto-confirm"
                        data-threshold="<?= $t ?>" data-provider="<?= htmlspecialchars($provider) ?>"
                        <?= $cnt === 0 ? 'disabled' : '' ?>>
                    <?= $t ?>% <span class="badge bg-secondary ms-1"><?= $cnt ?></span>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Barre de recherche -->
<form method="GET" action="/mapping" class="row g-2 mb-2">
    <input type="hidden" name="provider"   value="<?= htmlspecialchars($provider) ?>">
    <input type="hidden" name="confirmed"  value="<?= htmlspecialchars($confirmed) ?>">
    <input type="hidden" name="perPage"    value="<?= $perPage ?>">
    <div class="col-md-5">
        <div class="input-group input-group-sm">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" name="search" class="form-control"
                   placeholder="Nom entité, client, numéro…"
                   value="<?= htmlspecialchars($search) ?>">
            <?php if ($search): ?>
            <a href="?<?= http_build_query(array_merge($qp, ['search' => '', 'page' => 1])) ?>"
               class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-outline-secondary">Filtrer</button>
    </div>
</form>
</div>

<!-- Tableau -->
<div class="table-responsive">
<table class="table table-hover table-sm align-middle" id="mappingTable">
    <thead class="table-dark sticky-top">
        <tr>
            <th><?= mSortLink('entity', $sortBy, $sortDir, 'Entité fournisseur', $qp) ?></th>
            <?php if ($isUnmappedTab): ?>
            <th style="min-width:320px">Client interne</th>
            <?php else: ?>
            <th><?= mSortLink('client', $sortBy, $sortDir, 'Client interne', $qp) ?></th>
            <?php endif; ?>
            <th class="text-center" style="width:80px"><?= mSortLink('score', $sortBy, $sortDir, 'Score', $qp) ?></th>
            <th class="text-center" style="width:90px"><?= mSortLink('method', $sortBy, $sortDir, 'Méthode', $qp) ?></th>
            <?php if (!$isUnmappedTab): ?>
            <th class="text-center" style="width:80px">Statut</th>
            <?php endif; ?>
            <th class="text-center" style="width:80px">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($entities)): ?>
        <tr>
            <td colspan="<?= $isUnmappedTab ? 5 : 6 ?>" class="text-center text-body-secondary py-5">
                <i class="bi bi-<?= $isUnmappedTab ? 'check-circle text-success' : 'inbox' ?> fs-2 d-block mb-2 opacity-50"></i>
                <?= $isUnmappedTab ? 'Tout est mappé !' : 'Aucun mapping confirmé.' ?>
            </td>
        </tr>
        <?php else: ?>
        <?php foreach ($entities as $e):
            $mapped     = !empty($e['mapping_id']);
            $isConf     = $mapped && $e['is_confirmed'];
            $score      = ($mapped && $e['match_score'] !== null) ? (int)$e['match_score'] : null;
            $scoreCls   = $score === null ? 'secondary' : ($score >= 90 ? 'success' : ($score >= 70 ? 'warning' : 'danger'));
            $methodMap  = ['manual' => ['Manuel','primary'], 'name_match' => ['Nom','info'], 'client_number' => ['N°client','success']];
            $ml         = $methodMap[$e['mapping_method'] ?? ''] ?? ['—','secondary'];

            // Texte affiché dans l'autocomplete (client actuel si mappé)
            $currentClientText = '';
            if ($mapped && $e['client_name']) {
                $currentClientText = $e['client_name'];
                if ($e['client_number']) $currentClientText .= ' (' . $e['client_number'] . ')';
            }
        ?>
        <tr>
            <td>
                <span class="fw-medium"><?= htmlspecialchars($e['provider_name']) ?></span>
                <br><small class="text-body-secondary font-monospace"><?= htmlspecialchars($e['provider_client_id']) ?></small>
            </td>
            <td>
                <?php if ($isUnmappedTab): ?>
                <!-- Autocomplete client -->
                <div class="d-flex align-items-center gap-2"
                     data-provider-client-id="<?= htmlspecialchars($e['provider_client_id']) ?>"
                     data-provider="<?= htmlspecialchars($provider) ?>">
                    <div class="client-autocomplete position-relative flex-grow-1">
                        <input type="text"
                               class="form-control form-control-sm client-search-input"
                               placeholder="Chercher un client…"
                               value="<?= htmlspecialchars($currentClientText) ?>"
                               autocomplete="off">
                        <input type="hidden"
                               class="client-id-input"
                               value="<?= (int)($e['client_id'] ?? 0) ?>"
                               data-original="<?= (int)($e['client_id'] ?? 0) ?>">
                        <div class="client-dropdown list-group shadow-sm position-absolute w-100 d-none"
                             style="z-index:1050;max-height:220px;overflow-y:auto;top:100%"></div>
                    </div>
                    <button class="btn btn-sm btn-primary btn-save-mapping flex-shrink-0"
                            title="Sauvegarder" disabled>
                        <i class="bi bi-check-lg"></i>
                    </button>
                </div>
                <?php else: ?>
                <!-- Vue confirmée : lecture seule -->
                <span class="fw-medium"><?= htmlspecialchars($e['client_name'] ?? '—') ?></span>
                <?php if ($e['client_number']): ?>
                <br><small class="text-body-secondary"><code><?= htmlspecialchars($e['client_number']) ?></code></small>
                <?php endif; ?>
                <?php endif; ?>
            </td>
            <td class="text-center">
                <?php if ($score !== null): ?>
                <span class="badge bg-<?= $scoreCls ?>"><?= $score ?>%</span>
                <?php else: ?><span class="text-body-secondary small">—</span><?php endif; ?>
            </td>
            <td class="text-center">
                <span class="badge bg-<?= $ml[1] ?>"><?= $ml[0] ?></span>
            </td>
            <?php if (!$isUnmappedTab): ?>
            <td class="text-center">
                <i class="bi bi-check-circle-fill text-success" title="Confirmé"></i>
            </td>
            <?php endif; ?>
            <td class="text-center">
                <?php if ($isUnmappedTab && $mapped && !$isConf): ?>
                <button class="btn btn-sm btn-outline-success btn-confirm-mapping"
                        data-mapping-id="<?= $e['mapping_id'] ?>" title="Confirmer sans changer">
                    <i class="bi bi-check-lg"></i>
                </button>
                <?php endif; ?>
                <?php if ($mapped): ?>
                <button class="btn btn-sm btn-outline-danger btn-unlink-mapping ms-1"
                        data-mapping-id="<?= $e['mapping_id'] ?>" title="Supprimer">
                    <i class="bi bi-trash"></i>
                </button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
</div>

<!-- Pagination -->
<?php
$totalPages = (int)ceil($total / $perPage);
$base = http_build_query(array_merge($qp, ['sort' => $sortBy, 'dir' => $sortDir]));
?>
<div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
    <small class="text-body-secondary">
        <?= number_format(min(($page - 1) * $perPage + 1, max($total, 1))) ?>–<?= number_format(min($page * $perPage, $total)) ?> sur <?= number_format($total) ?>
    </small>
    <div class="d-flex align-items-center gap-3">
        <div class="d-flex align-items-center gap-2">
            <label class="small text-body-secondary mb-0">Par page :</label>
            <select class="form-select form-select-sm" style="width:auto"
                    onchange="const u=new URL(window.location);u.searchParams.set('perPage',this.value);u.searchParams.set('page','1');window.location=u">
                <?php foreach ([25, 50, 100, 250] as $pp): ?>
                <option value="<?= $pp ?>" <?= $perPage === $pp ? 'selected' : '' ?>><?= $pp ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($totalPages > 1): ?>
        <nav><ul class="pagination pagination-sm mb-0">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                <a class="page-link" href="?<?= $base ?>&page=<?= $p ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
        </ul></nav>
        <?php endif; ?>
    </div>
</div>

<script>
// ── Données clients (pour autocomplete) ─────────────────────────────────────
const CLIENTS_DATA = <?= json_encode(array_map(fn($c) => [
    'id'     => (int)$c['id'],
    'name'   => $c['name'],
    'num'    => $c['client_number'] ?? '',
    'label'  => $c['name'] . ($c['client_number'] ? ' (' . $c['client_number'] . ')' : ''),
    'search' => strtolower($c['name'] . ' ' . ($c['client_number'] ?? '')),
], $clients), JSON_UNESCAPED_UNICODE) ?>;

// ── Suivi des changements en attente ─────────────────────────────────────────
const pendingChanges = new Map(); // provider_client_id → {client_id, provider_client_id}
const btnSaveAll     = document.getElementById('btnSaveAll');
const saveAllCount   = document.getElementById('saveAllCount');

function updateSaveAll() {
    if (!btnSaveAll) return;
    const n = pendingChanges.size;
    btnSaveAll.classList.toggle('d-none', n === 0);
    if (saveAllCount) saveAllCount.textContent = n;
}

btnSaveAll?.addEventListener('click', function() {
    const mappings = [...pendingChanges.values()];
    if (!mappings.length) return;
    if (!confirm(`Sauvegarder ${mappings.length} mapping(s) ?`)) return;

    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sauvegarde…';

    const fd = new FormData();
    fd.append('provider', '<?= htmlspecialchars($provider) ?>');
    fd.append('mappings', JSON.stringify(mappings));

    fetch('/mapping/link-bulk', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) location.reload();
            else {
                alert('❌ ' + d.message);
                this.disabled = false;
                this.innerHTML = '<i class="bi bi-check-all me-1"></i>Sauvegarder tout <span id="saveAllCount" class="badge bg-light text-dark ms-1">' + mappings.length + '</span>';
            }
        })
        .catch(() => { alert('❌ Erreur réseau'); this.disabled = false; });
});

// ── Autocomplete ─────────────────────────────────────────────────────────────
document.querySelectorAll('.client-autocomplete').forEach(function(ac) {
    const input    = ac.querySelector('.client-search-input');
    const hidden   = ac.querySelector('.client-id-input');
    const dropdown = ac.querySelector('.client-dropdown');
    const row      = ac.closest('[data-provider-client-id]');
    const saveBtn  = row.querySelector('.btn-save-mapping');

    function showDropdown(q) {
        const matches = CLIENTS_DATA.filter(c => c.search.includes(q)).slice(0, 12);
        dropdown.innerHTML = '';
        if (!matches.length) { dropdown.classList.add('d-none'); return; }

        matches.forEach(c => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'list-group-item list-group-item-action py-1 px-2 small';
            btn.innerHTML = '<strong>' + esc(c.name) + '</strong>'
                + (c.num ? ' <span class="text-body-secondary">(' + esc(c.num) + ')</span>' : '');
            btn.addEventListener('mousedown', function(e) {
                e.preventDefault(); // évite blur avant click
                input.value  = c.label;
                hidden.value = c.id;
                dropdown.classList.add('d-none');
                const isDirty = String(c.id) !== String(hidden.dataset.original);
                saveBtn.disabled = !isDirty;
                const key = row.dataset.providerClientId;
                if (isDirty) {
                    pendingChanges.set(key, { client_id: c.id, provider_client_id: key });
                } else {
                    pendingChanges.delete(key);
                }
                updateSaveAll();
            });
            dropdown.appendChild(btn);
        });
        dropdown.classList.remove('d-none');
    }

    input.addEventListener('input', function() {
        const q = this.value.toLowerCase().trim();
        if (q.length < 1) { dropdown.classList.add('d-none'); return; }
        showDropdown(q);
    });

    input.addEventListener('focus', function() {
        const q = this.value.toLowerCase().trim();
        if (q.length >= 1) showDropdown(q);
    });

    input.addEventListener('blur', function() {
        setTimeout(() => dropdown.classList.add('d-none'), 150);
    });

    // Si l'utilisateur efface manuellement le texte → réinitialiser
    input.addEventListener('input', function() {
        if (this.value === '') {
            hidden.value = '';
            saveBtn.disabled = hidden.dataset.original === '';
        }
    });

    saveBtn.addEventListener('click', function() {
        const clientId = hidden.value;
        if (!clientId) { alert('Veuillez sélectionner un client.'); return; }

        const fd = new FormData();
        fd.append('client_id', clientId);
        fd.append('provider_client_id', row.dataset.providerClientId);
        fd.append('provider', row.dataset.provider);

        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        fetch('/mapping/link', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    pendingChanges.delete(row.dataset.providerClientId);
                    location.reload();
                } else {
                    alert('❌ ' + d.message);
                    this.disabled = false;
                    this.innerHTML = '<i class="bi bi-check-lg"></i>';
                }
            })
            .catch(() => {
                alert('❌ Erreur réseau');
                this.disabled = false;
                this.innerHTML = '<i class="bi bi-check-lg"></i>';
            });
    });
});

function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── Confirmer mapping (sans changer le client) ───────────────────────────────
document.querySelectorAll('.btn-confirm-mapping').forEach(btn => {
    btn.addEventListener('click', function() {
        const fd = new FormData();
        fd.append('mapping_ids', this.dataset.mappingId);
        fetch('/mapping/confirm-bulk', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => { if (d.success) location.reload(); else alert('❌ ' + d.message); })
            .catch(() => alert('❌ Erreur réseau'));
    });
});

// ── Supprimer mapping ────────────────────────────────────────────────────────
document.querySelectorAll('.btn-unlink-mapping').forEach(btn => {
    btn.addEventListener('click', function() {
        if (!confirm('Supprimer ce mapping ?')) return;
        const fd = new FormData();
        fd.append('mapping_id', this.dataset.mappingId);
        fetch('/mapping/unlink', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => { if (d.success) location.reload(); else alert('❌ ' + d.message); })
            .catch(() => alert('❌ Erreur réseau'));
    });
});

// ── Auto-confirm ─────────────────────────────────────────────────────────────
document.querySelectorAll('.btn-auto-confirm').forEach(btn => {
    btn.addEventListener('click', function() {
        const t = this.dataset.threshold;
        if (!confirm(`Confirmer tous les mappings avec score ≥ ${t}% ?`)) return;
        const fd = new FormData();
        fd.append('threshold', t);
        fd.append('provider', this.dataset.provider);
        fetch('/mapping/auto-confirm', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => { alert(d.success ? '✅ ' + d.message : '❌ ' + d.message); if (d.success) location.reload(); })
            .catch(() => alert('❌ Erreur réseau'));
    });
});
</script>
