<?php
/** @var array $licenses */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var string $search */
/** @var int $tagId */
/** @var string $state */
/** @var string $sortBy */
/** @var string $sortDir */
/** @var array|false $lastSync */
/** @var array $allTags */

$today    = new DateTime();
$in30Days = new DateTime('+30 days');

function sortLink(string $col, string $current, string $dir, string $label, array $queryParams): string {
    $newDir = ($current === $col && $dir === 'ASC') ? 'DESC' : 'ASC';
    $icon   = $current === $col ? ($dir === 'ASC' ? '↑' : '↓') : '';
    $params = array_merge($queryParams, ['sort' => $col, 'dir' => $newDir]);
    return '<a href="/eset/licenses?' . http_build_query($params) . '" class="text-white text-decoration-none">'
         . $label . ($icon ? " $icon" : '') . '</a>';
}

/**
 * Parse "id:name:color;;id:name:color" → array
 */
function parseTagsEset(?string $raw): array {
    if (!$raw) return [];
    return array_map(function($part) {
        [$id, $name, $color] = array_pad(explode(':', $part, 3), 3, '');
        return ['id' => (int)$id, 'name' => $name, 'color' => $color];
    }, explode(';;', $raw));
}

$qp = compact('search', 'tagId', 'state', 'page', 'perPage');
?>

<div class="page-sticky-top">
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h1 class="h3 mb-0">
            Licences ESET
            <span class="badge bg-secondary ms-2"><?= number_format($total) ?></span>
        </h1>
        <?php if ($lastSync): ?>
        <small class="text-body-secondary">
            Dernière sync : <?= date('d/m/Y H:i', strtotime($lastSync['finished_at'])) ?>
            <span class="badge bg-<?= $lastSync['status'] === 'success' ? 'success' : 'warning' ?> ms-1">
                <?= htmlspecialchars($lastSync['status']) ?>
            </span>
        </small>
        <?php else: ?>
        <small class="text-body-secondary">Aucune synchronisation effectuée.</small>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <a href="/mapping" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-link-45deg me-1"></i>Mapping
        </a>
        <a href="/eset/sync-logs" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-clock-history me-1"></i>Logs
        </a>
        <a href="#" class="btn btn-sm btn-outline-secondary" id="btnExportCsv">
            <i class="bi bi-download me-1"></i>CSV
        </a>
        <button class="btn btn-sm btn-primary" id="btnPageSync" onclick="window.openSyncModal?.(null, 'eset')">
            <i class="bi bi-arrow-clockwise me-1"></i>Sync maintenant
        </button>
    </div>
</div>

<!-- Filtres -->
<form method="GET" action="/eset/licenses" class="row g-2 mb-2" id="filterForm">
    <div class="col-md-4">
        <div class="input-group input-group-sm">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" name="search" class="form-control"
                   placeholder="Client, company, licence, produit…"
                   value="<?= htmlspecialchars($search) ?>">
        </div>
    </div>
    <div class="col-md-2">
        <select name="tag" class="form-select form-select-sm">
            <option value="">Tous les tags</option>
            <?php foreach ($allTags as $t): ?>
                <option value="<?= (int)$t['id'] ?>" <?= $tagId === (int)$t['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($t['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <select name="state" class="form-select form-select-sm">
            <option value="">Tous les états</option>
            <option value="VALID"         <?= $state === 'VALID' ? 'selected' : '' ?>>Valide</option>
            <option value="EXPIRING_SOON" <?= $state === 'EXPIRING_SOON' ? 'selected' : '' ?>>Expire bientôt</option>
            <option value="EXPIRED"       <?= $state === 'EXPIRED' ? 'selected' : '' ?>>Expiré</option>
        </select>
    </div>
    <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
    <input type="hidden" name="dir"  value="<?= htmlspecialchars($sortDir) ?>">
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-outline-secondary">Filtrer</button>
        <a href="/eset/licenses" class="btn btn-sm btn-outline-secondary">Reset</a>
    </div>
</form>
</div>

<!-- Tableau -->
<div class="table-responsive">
    <table class="table table-hover table-sm align-middle" id="licensesTable">
        <thead class="table-dark small">
            <tr>
                <th><?= sortLink('client', $sortBy, $sortDir, 'Client', $qp) ?></th>
                <th>Tags</th>
                <th><?= sortLink('company', $sortBy, $sortDir, 'Company ESET', $qp) ?></th>
                <th><?= sortLink('product', $sortBy, $sortDir, 'Produit', $qp) ?></th>
                <th class="text-center"><?= sortLink('quantity', $sortBy, $sortDir, 'Total', $qp) ?></th>
                <th class="text-center">Utilisés</th>
                <th class="text-center">Libres</th>
                <th class="text-center">Utilisation</th>
                <th class="text-center"><?= sortLink('state', $sortBy, $sortDir, 'État', $qp) ?></th>
                <th><?= sortLink('expiry', $sortBy, $sortDir, 'Expiration', $qp) ?></th>
                <th class="text-center">Sync</th>
                <th class="text-center" title="Équipements (debug API)"><i class="bi bi-pc-display"></i></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($licenses)): ?>
            <tr>
                <td colspan="12" class="text-center text-body-secondary py-5">
                    <i class="bi bi-shield-lock fs-1 d-block mb-2 opacity-25"></i>
                    Aucune licence trouvée.
                    <?php if (!$lastSync): ?>
                    <br><button class="btn btn-sm btn-primary mt-2" id="btnFirstSync">
                        <i class="bi bi-arrow-clockwise me-1"></i>Lancer la première sync
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($licenses as $lic):
                $expDate  = $lic['expiration_date'] ? new DateTime($lic['expiration_date']) : null;
                $isTrial  = (bool)$lic['is_trial'];
                $expiringSoon = $expDate && $expDate >= $today && $expDate <= $in30Days;

                if ($expiringSoon) {
                    $stateBadge = '<span class="badge bg-warning text-dark">Expire bientôt</span>';
                } elseif ($lic['state'] === 'EXPIRED' || ($expDate && $expDate < $today)) {
                    $stateBadge = '<span class="badge bg-danger">Expiré</span>';
                } elseif ($lic['state'] === 'VALID') {
                    $stateBadge = '<span class="badge bg-success">Valide</span>';
                } else {
                    $stateBadge = '<span class="badge bg-secondary">' . htmlspecialchars($lic['state'] ?? '?') . '</span>';
                }

                $qty      = (int)$lic['quantity'];
                $used     = (int)$lic['usage_count'];
                $free     = (int)$lic['seats_free'];
                $pct      = $qty > 0 ? round($used / $qty * 100) : 0;
                $over     = $used > $qty;
                $full     = !$over && $qty > 0 && $used === $qty;
                $barClass = $over ? 'bg-danger' : ($full ? 'bg-success' : 'bg-primary');

                $clientTags = parseTagsEset($lic['client_tags_raw'] ?? null);
            ?>
            <tr>
                <td>
                    <?php if ($lic['client_name']): ?>
                        <span class="fw-medium"><?= htmlspecialchars($lic['client_name']) ?></span>
                        <br><small class="text-body-secondary font-monospace"><?= htmlspecialchars($lic['client_number']) ?></small>
                    <?php else: ?>
                        <em class="text-body-secondary small">Non mappé</em>
                    <?php endif; ?>
                </td>
                <td style="min-width:100px">
                    <?php if (!empty($clientTags)): ?>
                        <div class="d-flex flex-wrap gap-1">
                            <?php foreach ($clientTags as $tag): ?>
                            <span class="badge rounded-pill" style="background-color:<?= htmlspecialchars($tag['color']) ?>">
                                <?= htmlspecialchars($tag['name']) ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <span class="text-body-secondary">—</span>
                    <?php endif; ?>
                </td>
                <td class="small">
                    <?= htmlspecialchars($lic['company_name']) ?>
                    <?php if ($lic['mapping_confirmed'] === '0'): ?>
                        <span class="badge bg-warning text-dark ms-1 small">Mapping non confirmé</span>
                    <?php endif; ?>
                </td>
                <td class="small">
                    <?= htmlspecialchars($lic['product_name'] ?? '—') ?>
                    <?php if ($isTrial): ?>
                        <span class="badge bg-info ms-1">Trial</span>
                    <?php endif; ?>
                    <br><code class="small text-body-secondary"><?= htmlspecialchars($lic['public_license_key']) ?></code>
                </td>
                <td class="text-center"><?= number_format($qty) ?></td>
                <td class="text-center <?= $over ? 'text-danger fw-semibold' : ($full ? 'text-success fw-semibold' : '') ?>">
                    <?php if ($over): ?><i class="bi bi-exclamation-triangle-fill me-1 small"></i><?php endif; ?>
                    <?= number_format($used) ?>
                </td>
                <td class="text-center <?= $over ? 'text-danger fw-bold' : ($free === 0 && $full ? 'text-success' : '') ?>"><?= number_format($free) ?></td>
                <td style="min-width:80px">
                    <div class="progress" style="height:6px">
                        <div class="progress-bar <?= $barClass ?>" style="width:<?= min($pct, 100) ?>%" title="<?= $pct ?>%"></div>
                    </div>
                    <small class="<?= $over ? 'text-danger' : ($full ? 'text-success' : 'text-body-secondary') ?>"><?= $pct ?>%</small>
                </td>
                <td class="text-center"><?= $stateBadge ?></td>
                <td class="small <?= ($expiringSoon ? 'text-warning' : ($expDate && $expDate < $today ? 'text-danger' : '')) ?>">
                    <?= $lic['expiration_date'] ? date('d/m/Y', strtotime($lic['expiration_date'])) : '—' ?>
                </td>
                <td class="text-center">
                    <small class="text-body-secondary" title="<?= htmlspecialchars($lic['last_sync_at'] ?? '') ?>">
                        <?php if ($lic['last_sync_at']): ?>
                            <?= date('d/m H:i', strtotime($lic['last_sync_at'])) ?>
                        <?php else: ?>—<?php endif; ?>
                    </small>
                </td>
                <td class="text-center">
                    <a href="/eset/debug-devices?license_key=<?= urlencode($lic['public_license_key']) ?>"
                       target="_blank"
                       class="btn btn-xs btn-outline-secondary py-0 px-1 me-1"
                       title="Voir le retour API équipements (debug)"
                       style="font-size:.75rem">
                        <i class="bi bi-pc-display"></i>
                    </a>
                    <button class="btn btn-xs btn-outline-secondary py-0 px-1 btn-show-history"
                            data-key="<?= htmlspecialchars($lic['public_license_key']) ?>"
                            data-product="<?= htmlspecialchars($lic['product_name'] ?? '') ?>"
                            data-client="<?= htmlspecialchars($lic['client_name'] ?? '') ?>"
                            data-company="<?= htmlspecialchars($lic['company_name'] ?? '') ?>"
                            data-qty="<?= $qty ?>"
                            data-used="<?= $used ?>"
                            data-free="<?= $free ?>"
                            data-over="<?= $over ? '1' : '0' ?>"
                            data-full="<?= $full ? '1' : '0' ?>"
                            title="Historique de la licence"
                            style="font-size:.75rem">
                        <i class="bi bi-clock-history"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$totalPages = (int)ceil($total / $perPage);
$queryBase  = http_build_query(['search' => $search, 'tag' => $tagId ?: '', 'state' => $state, 'sort' => $sortBy, 'dir' => $sortDir, 'perPage' => $perPage]);
?>
<div class="page-sticky-bottom d-flex justify-content-between align-items-center">
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
            <?php for ($i = 1; $i <= min($totalPages, 20); $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="/eset/licenses?<?= $queryBase ?>&page=<?= $i ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
            <?php if ($totalPages > 20): ?>
            <li class="page-item disabled"><span class="page-link">…</span></li>
            <?php endif; ?>
        </ul></nav>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Historique licence ESET -->
<div class="modal fade" id="licenseHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div class="flex-grow-1 me-3">
                    <h5 class="modal-title mb-1">
                        <i class="bi bi-clock-history me-2 text-success"></i>
                        Historique — <span id="histModalKey" class="font-monospace small"></span>
                    </h5>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <small class="text-body-secondary" id="histModalClient"></small>
                        <span id="histModalStats"></span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" id="histModalBody">
                <div class="text-center py-4">
                    <div class="spinner-border spinner-border-sm text-secondary"></div>
                </div>
            </div>
            <div class="modal-footer">
                <small class="text-body-secondary me-auto" id="histModalCount"></small>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
// "Lancer la première sync" (table vide)
document.getElementById('btnFirstSync')?.addEventListener('click', () => window.openSyncModal?.(null, 'eset'));

// Export CSV côté client (table visible)
document.getElementById('btnExportCsv')?.addEventListener('click', function(e) {
    e.preventDefault();
    const rows = document.querySelectorAll('#licensesTable tr');
    const csv  = Array.from(rows).map(row =>
        Array.from(row.querySelectorAll('th,td'))
            .map(cell => '"' + cell.innerText.replace(/"/g, '""').replace(/\n/g, ' ') + '"')
            .join(',')
    ).join('\n');

    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = 'eset_licences_' + new Date().toISOString().slice(0,10) + '.csv';
    a.click();
    URL.revokeObjectURL(url);
});

// ── Historique licence ──────────────────────────────────────────────────────
(function () {
    let PRODUCT_NAMES = {}; // alimenté depuis l'API lors du premier clic

    const TYPE_LABELS = {
        '1':  { label: 'Annulation',              color: 'danger'  },
        '2':  { label: 'Conversion (trial→full)', color: 'success' },
        '3':  { label: 'Extension d\'essai',      color: 'info'    },
        '4':  { label: 'Nouvelle commande',        color: 'success' },
        '5':  { label: 'Mise à jour quantité',     color: 'primary' },
        '6':  { label: 'Suspension',               color: 'warning' },
        '7':  { label: 'Réactivation',             color: 'success' },
        '8':  { label: 'Changement de clé',        color: 'secondary'},
        '9':  { label: 'Upgrade',                  color: 'success' },
        '10': { label: 'Downgrade',                color: 'warning' },
    };

    function formatDate(raw) {
        if (!raw) return '—';
        const d = new Date(raw);
        return isNaN(d) ? raw : d.toLocaleDateString('fr-FR', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' });
    }

    function buildDetail(entry) {
        const parts = [];
        if (entry.PreviousUnits   && entry.RequestedUnits) parts.push(`${entry.PreviousUnits} → ${entry.RequestedUnits} unités`);
        else if (entry.RequestedUnits)                      parts.push(`${entry.RequestedUnits} unité(s)`);
        if (entry.PreviousProductCode && entry.RequestedProductCode) {
            const prev = PRODUCT_NAMES[entry.PreviousProductCode] ?? `#${entry.PreviousProductCode}`;
            const next = PRODUCT_NAMES[entry.RequestedProductCode] ?? `#${entry.RequestedProductCode}`;
            parts.push(`Produit : ${prev} → ${next}`);
        }
        if (entry.PreviousLicenseKey  && entry.RequestedLicenseKey)  parts.push(`Clé : ${entry.PreviousLicenseKey} → ${entry.RequestedLicenseKey}`);
        if (entry.TrialExtensionCount) parts.push(`Extension n°${entry.TrialExtensionCount}`);
        if (entry.LicenseTypeId === '1') parts.push('Licence complète');
        return parts.join(' &nbsp;·&nbsp; ');
    }

    function renderHistory(data) {
        // Enrichir la map produits avec ce que l'API a retourné
        if (data.products && typeof data.products === 'object') {
            PRODUCT_NAMES = Object.assign({}, data.products);
        }

        const history = data.history ?? [];
        const count   = data.total_count ?? history.length;

        document.getElementById('histModalCount').textContent =
            count + ' événement' + (count > 1 ? 's' : '');

        if (!history.length) {
            document.getElementById('histModalBody').innerHTML =
                '<p class="text-center text-body-secondary py-4">Aucun historique disponible.</p>';
            return;
        }

        let rows = history.map(entry => {
            const t      = TYPE_LABELS[entry.Type] ?? { label: 'Type ' + entry.Type, color: 'secondary' };
            const detail = buildDetail(entry);
            return `<tr>
                <td class="small text-body-secondary text-nowrap">${formatDate(entry.Date)}</td>
                <td><span class="badge bg-${t.color}">${t.label}</span></td>
                <td class="small">${detail ? `<span class="text-body-secondary">${detail}</span>` : ''}</td>
                <td class="small text-body-secondary">${entry.User ?? '—'}</td>
            </tr>`;
        }).join('');

        document.getElementById('histModalBody').innerHTML = `
            <table class="table table-sm table-hover small mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Date</th>
                        <th>Opération</th>
                        <th>Détail</th>
                        <th>Utilisateur</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>`;
    }

    const modalEl = document.getElementById('licenseHistoryModal');

    document.querySelectorAll('.btn-show-history').forEach(btn => {
        btn.addEventListener('click', function () {
            const bsModal  = bootstrap.Modal.getOrCreateInstance(modalEl);
            const key      = this.dataset.key;
            const product  = this.dataset.product;
            const client   = this.dataset.client;
            const company  = this.dataset.company;
            const qty      = parseInt(this.dataset.qty,  10);
            const used     = parseInt(this.dataset.used, 10);
            const free     = parseInt(this.dataset.free, 10);
            const over     = this.dataset.over === '1';
            const full     = this.dataset.full === '1';

            document.getElementById('histModalKey').textContent = key + (product ? ' — ' + product : '');

            // Client ou company ESET en fallback
            const clientEl = document.getElementById('histModalClient');
            clientEl.textContent = client || (company ? company : '');
            clientEl.className   = client
                ? 'fw-semibold text-body-secondary'
                : 'fst-italic text-body-secondary small';

            // Badges stats licence
            const statsEl   = document.getElementById('histModalStats');
            const qtyColor  = over ? 'danger' : (full ? 'success' : 'primary');
            const freeColor = over ? 'danger' : (full ? 'success' : 'secondary');
            statsEl.innerHTML = `
                <span class="badge bg-secondary">${qty} commandés</span>
                <span class="badge bg-${qtyColor}">${used} utilisés</span>
                <span class="badge bg-${freeColor}">${free} libres</span>`;

            document.getElementById('histModalCount').textContent = '';
            document.getElementById('histModalBody').innerHTML   =
                '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-secondary"></div></div>';
            document.getElementById('histModalBody').innerHTML   =
                '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-secondary"></div></div>';

            bsModal.show();

            fetch('/eset/debug-history?license_key=' + encodeURIComponent(key))
                .then(r => r.json())
                .then(renderHistory)
                .catch(err => {
                    document.getElementById('histModalBody').innerHTML =
                        `<div class="alert alert-danger m-3">Erreur : ${err.message}</div>`;
                });
        });
    });
})();
</script>
