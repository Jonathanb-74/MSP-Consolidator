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

$qp = compact('search', 'tagId', 'state', 'page');
?>

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
        <a href="/eset/sync-logs" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-clock-history me-1"></i>Logs
        </a>
        <a href="#" class="btn btn-sm btn-outline-secondary" id="btnExportCsv">
            <i class="bi bi-download me-1"></i>CSV
        </a>
        <button class="btn btn-sm btn-success" id="btnPageSync" onclick="window.openSyncModal?.()">
            <i class="bi bi-arrow-clockwise me-1"></i>Sync maintenant
        </button>
    </div>
</div>

<!-- Filtres -->
<form method="GET" action="/eset/licenses" class="row g-2 mb-3" id="filterForm">
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
            </tr>
        </thead>
        <tbody>
            <?php if (empty($licenses)): ?>
            <tr>
                <td colspan="11" class="text-center text-body-secondary py-5">
                    <i class="bi bi-shield-lock fs-1 d-block mb-2 opacity-25"></i>
                    Aucune licence trouvée.
                    <?php if (!$lastSync): ?>
                    <br><button class="btn btn-sm btn-success mt-2" id="btnFirstSync">
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
                $barClass = $pct >= 90 ? 'bg-danger' : ($pct >= 70 ? 'bg-warning' : 'bg-success');

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
                <td class="text-center"><?= number_format($used) ?></td>
                <td class="text-center <?= $free === 0 ? 'text-danger fw-bold' : '' ?>"><?= number_format($free) ?></td>
                <td style="min-width:80px">
                    <div class="progress" style="height:6px">
                        <div class="progress-bar <?= $barClass ?>" style="width:<?= $pct ?>%" title="<?= $pct ?>%"></div>
                    </div>
                    <small class="text-body-secondary"><?= $pct ?>%</small>
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
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if ($total > $perPage): ?>
<?php
$totalPages = (int)ceil($total / $perPage);
$queryBase  = http_build_query(['search' => $search, 'tag' => $tagId ?: '', 'state' => $state, 'sort' => $sortBy, 'dir' => $sortDir]);
?>
<nav>
    <ul class="pagination pagination-sm justify-content-end">
        <?php for ($i = 1; $i <= min($totalPages, 20); $i++): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
            <a class="page-link" href="/eset/licenses?<?= $queryBase ?>&page=<?= $i ?>"><?= $i ?></a>
        </li>
        <?php endfor; ?>
        <?php if ($totalPages > 20): ?>
        <li class="page-item disabled"><span class="page-link">…</span></li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<script>
// "Lancer la première sync" (table vide)
document.getElementById('btnFirstSync')?.addEventListener('click', () => window.openSyncModal?.());

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
</script>
