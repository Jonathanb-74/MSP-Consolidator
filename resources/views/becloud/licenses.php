<?php
/** @var array $subscriptions */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var string $search */
/** @var int $tagId */
/** @var string $status */
/** @var string $offerType */
/** @var string $sortBy */
/** @var string $sortDir */
/** @var array|false $lastSync */
/** @var array $allTags */
/** @var array $connections */

$today    = new DateTime();
$in30Days = new DateTime('+30 days');

function bcSortLink(string $col, string $current, string $dir, string $label, array $queryParams): string {
    $newDir = ($current === $col && $dir === 'ASC') ? 'DESC' : 'ASC';
    $icon   = $current === $col ? ($dir === 'ASC' ? '↑' : '↓') : '';
    $params = array_merge($queryParams, ['sort' => $col, 'dir' => $newDir]);
    return '<a href="/becloud/licenses?' . http_build_query($params) . '" class="text-white text-decoration-none">'
         . $label . ($icon ? " $icon" : '') . '</a>';
}

function bcParseTags(?string $raw): array {
    if (!$raw) return [];
    return array_map(function($part) {
        [$id, $name, $color] = array_pad(explode(':', $part, 3), 3, '');
        return ['id' => (int)$id, 'name' => $name, 'color' => $color];
    }, explode(';;', $raw));
}

$qp = compact('search', 'tagId', 'status', 'offerType', 'page', 'perPage');
?>

<div class="page-sticky-top">
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h1 class="h3 mb-0">
            Abonnements Be-Cloud
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
        <a href="/mapping?provider=becloud" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-link-45deg me-1"></i>Mapping
        </a>
        <a href="/becloud/sync-logs" class="btn btn-sm btn-outline-secondary">
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
<form method="GET" action="/becloud/licenses" class="row g-2 mb-2" id="filterForm">
    <div class="col-md-4">
        <div class="input-group input-group-sm">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" name="search" class="form-control"
                   placeholder="Client, customer, abonnement…"
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
        <select name="status" class="form-select form-select-sm">
            <option value="">Tous les statuts</option>
            <option value="Active"        <?= $status === 'Active' ? 'selected' : '' ?>>Actif</option>
            <option value="EXPIRING_SOON" <?= $status === 'EXPIRING_SOON' ? 'selected' : '' ?>>Expire bientôt</option>
            <option value="Suspended"     <?= $status === 'Suspended' ? 'selected' : '' ?>>Suspendu</option>
            <option value="Deleted"       <?= $status === 'Deleted' ? 'selected' : '' ?>>Supprimé</option>
        </select>
    </div>
    <div class="col-md-2">
        <select name="offer_type" class="form-select form-select-sm">
            <option value="">Tous les types</option>
            <option value="License"      <?= $offerType === 'License' ? 'selected' : '' ?>>License</option>
            <option value="Subscription" <?= $offerType === 'Subscription' ? 'selected' : '' ?>>Subscription</option>
            <option value="Usage"        <?= $offerType === 'Usage' ? 'selected' : '' ?>>Usage</option>
        </select>
    </div>
    <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
    <input type="hidden" name="dir"  value="<?= htmlspecialchars($sortDir) ?>">
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-outline-secondary">Filtrer</button>
        <a href="/becloud/licenses" class="btn btn-sm btn-outline-secondary">Reset</a>
    </div>
</form>
</div>

<!-- Tableau -->
<div class="table-responsive">
    <table class="table table-hover table-sm align-middle" id="licensesTable">
        <thead class="table-dark small">
            <tr>
                <th><?= bcSortLink('client', $sortBy, $sortDir, 'Client', $qp) ?></th>
                <th>Tags</th>
                <th><?= bcSortLink('customer', $sortBy, $sortDir, 'Customer Be-Cloud', $qp) ?></th>
                <th><?= bcSortLink('product', $sortBy, $sortDir, 'Abonnement', $qp) ?></th>
                <th>Type</th>
                <th class="text-center"><?= bcSortLink('quantity', $sortBy, $sortDir, 'Qté', $qp) ?></th>
                <th class="text-center"><?= bcSortLink('assigned', $sortBy, $sortDir, 'Assignés', $qp) ?></th>
                <th class="text-center">Libres</th>
                <th class="text-center">Utilisation</th>
                <th class="text-center"><?= bcSortLink('status', $sortBy, $sortDir, 'Statut', $qp) ?></th>
                <th><?= bcSortLink('end_date', $sortBy, $sortDir, 'Renouvellement', $qp) ?></th>
                <th>Cycle</th>
                <th class="text-center">Sync</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($subscriptions)): ?>
            <tr>
                <td colspan="13" class="text-center text-body-secondary py-5">
                    <i class="bi bi-cloud-check fs-1 d-block mb-2 opacity-25"></i>
                    Aucun abonnement trouvé.
                    <?php if (!$lastSync): ?>
                    <br><button class="btn btn-sm btn-success mt-2" id="btnFirstSync">
                        <i class="bi bi-arrow-clockwise me-1"></i>Lancer la première sync
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($subscriptions as $sub):
                $endDate = $sub['end_date'] ? new DateTime($sub['end_date']) : null;
                $isTrial = (bool)$sub['is_trial'];
                $expiringSoon = $endDate && $endDate >= $today && $endDate <= $in30Days;

                $statusVal = $sub['status'] ?? '';
                if ($expiringSoon) {
                    $statusBadge = '<span class="badge bg-warning text-dark">Expire bientôt</span>';
                } elseif (in_array($statusVal, ['Suspended', 'Deleted', 'Expired']) || ($endDate && $endDate < $today)) {
                    $color = match($statusVal) {
                        'Suspended' => 'bg-warning text-dark',
                        default     => 'bg-danger',
                    };
                    $statusBadge = '<span class="badge ' . $color . '">' . htmlspecialchars($statusVal) . '</span>';
                } elseif ($statusVal === 'Active') {
                    $statusBadge = '<span class="badge bg-success">Actif</span>';
                } else {
                    $statusBadge = '<span class="badge bg-secondary">' . htmlspecialchars($statusVal ?: '?') . '</span>';
                }

                $qty    = (int)$sub['quantity'];
                $used   = (int)$sub['assigned_licenses'];
                $free   = (int)$sub['seats_free'];
                $pct    = $qty > 0 ? round($used / $qty * 100) : 0;
                $barClass = $pct >= 90 ? 'bg-danger' : ($pct >= 70 ? 'bg-warning' : 'bg-success');

                $clientTags = bcParseTags($sub['client_tags_raw'] ?? null);
            ?>
            <tr>
                <td>
                    <?php if ($sub['client_name']): ?>
                        <span class="fw-medium"><?= htmlspecialchars($sub['client_name']) ?></span>
                        <br><small class="text-body-secondary font-monospace"><?= htmlspecialchars($sub['client_number']) ?></small>
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
                    <?= htmlspecialchars($sub['customer_name']) ?>
                    <?php if ($sub['mapping_confirmed'] === '0'): ?>
                        <span class="badge bg-warning text-dark ms-1 small">Mapping non confirmé</span>
                    <?php endif; ?>
                    <?php if ($sub['internal_identifier']): ?>
                        <br><code class="small text-body-secondary"><?= htmlspecialchars($sub['internal_identifier']) ?></code>
                    <?php endif; ?>
                </td>
                <td class="small">
                    <?= htmlspecialchars($sub['subscription_name'] ?? $sub['offer_name'] ?? '—') ?>
                    <?php if ($isTrial): ?>
                        <span class="badge bg-info ms-1">Trial</span>
                    <?php endif; ?>
                    <?php if ($sub['auto_renewal']): ?>
                        <span class="badge bg-secondary ms-1" title="Renouvellement automatique">Auto</span>
                    <?php endif; ?>
                </td>
                <td class="small text-body-secondary"><?= htmlspecialchars($sub['offer_type'] ?? '—') ?></td>
                <td class="text-center"><?= number_format($qty) ?></td>
                <td class="text-center"><?= number_format($used) ?></td>
                <td class="text-center <?= $free === 0 && $qty > 0 ? 'text-danger fw-bold' : '' ?>"><?= number_format($free) ?></td>
                <td style="min-width:80px">
                    <?php if ($qty > 0): ?>
                    <div class="progress" style="height:6px">
                        <div class="progress-bar <?= $barClass ?>" style="width:<?= $pct ?>%" title="<?= $pct ?>%"></div>
                    </div>
                    <small class="text-body-secondary"><?= $pct ?>%</small>
                    <?php else: ?>
                    <small class="text-body-secondary">—</small>
                    <?php endif; ?>
                </td>
                <td class="text-center"><?= $statusBadge ?></td>
                <td class="small <?= $expiringSoon ? 'text-warning' : ($endDate && $endDate < $today ? 'text-danger' : '') ?>">
                    <?= $sub['end_date'] ? date('d/m/Y', strtotime($sub['end_date'])) : '—' ?>
                </td>
                <td class="small text-body-secondary">
                    <?php
                    $cycle = $sub['billing_frequency'] ?? '';
                    $term  = $sub['term_duration'] ?? '';
                    echo htmlspecialchars(implode(' / ', array_filter([$cycle, $term])) ?: '—');
                    ?>
                </td>
                <td class="text-center">
                    <small class="text-body-secondary" title="<?= htmlspecialchars($sub['last_sync_at'] ?? '') ?>">
                        <?php if ($sub['last_sync_at']): ?>
                            <?= date('d/m H:i', strtotime($sub['last_sync_at'])) ?>
                        <?php else: ?>—<?php endif; ?>
                    </small>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$totalPages = (int)ceil($total / $perPage);
$queryBase  = http_build_query(['search' => $search, 'tag' => $tagId ?: '', 'status' => $status, 'offer_type' => $offerType, 'sort' => $sortBy, 'dir' => $sortDir, 'perPage' => $perPage]);
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
                <a class="page-link" href="/becloud/licenses?<?= $queryBase ?>&page=<?= $i ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
            <?php if ($totalPages > 20): ?>
            <li class="page-item disabled"><span class="page-link">…</span></li>
            <?php endif; ?>
        </ul></nav>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('btnFirstSync')?.addEventListener('click', () => window.openSyncModal?.());

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
    a.download = 'becloud_abonnements_' + new Date().toISOString().slice(0,10) + '.csv';
    a.click();
    URL.revokeObjectURL(url);
});
</script>
