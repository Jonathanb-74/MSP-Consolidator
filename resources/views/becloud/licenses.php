<?php
/** @var array $licenses */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var string $search */
/** @var int $tagId */
/** @var string $sortBy */
/** @var string $sortDir */
/** @var array|false $lastSync */
/** @var array $allTags */
/** @var array $connections */

function bcLicSortLink(string $col, string $current, string $dir, string $label, array $queryParams): string {
    $newDir = ($current === $col && $dir === 'ASC') ? 'DESC' : 'ASC';
    $icon   = $current === $col ? ($dir === 'ASC' ? '↑' : '↓') : '';
    $params = array_merge($queryParams, ['sort' => $col, 'dir' => $newDir]);
    return '<a href="/becloud/licenses?' . http_build_query($params) . '" class="text-white text-decoration-none">'
         . $label . ($icon ? " $icon" : '') . '</a>';
}

function bcLicParseTags(?string $raw): array {
    if (!$raw) return [];
    return array_map(function($part) {
        [$id, $name, $color] = array_pad(explode(':', $part, 3), 3, '');
        return ['id' => (int)$id, 'name' => $name, 'color' => $color];
    }, explode(';;', $raw));
}

$qp = compact('search', 'tagId', 'page', 'perPage', 'sortBy', 'sortDir');
?>

<div class="page-sticky-top">
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h1 class="h3 mb-0">
            Licences Be-Cloud
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
        <a href="/becloud/customers" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-people me-1"></i>Clients
        </a>
        <a href="/mapping?provider=becloud" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-link-45deg me-1"></i>Mapping
        </a>
        <a href="/becloud/sync-logs" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-clock-history me-1"></i>Logs
        </a>
        <a href="#" class="btn btn-sm btn-outline-secondary" id="btnExportCsv">
            <i class="bi bi-download me-1"></i>CSV
        </a>
        <button class="btn btn-sm btn-primary" onclick="window.openSyncModal?.(null, 'becloud')">
            <i class="bi bi-arrow-clockwise me-1"></i>Sync maintenant
        </button>
    </div>
</div>

<!-- Filtres -->
<form method="GET" action="/becloud/licenses" class="row g-2 mb-2" id="filterForm">
    <div class="col-md-5">
        <div class="input-group input-group-sm">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" name="search" class="form-control"
                   placeholder="Client, customer, licence, SKU…"
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
                <th><?= bcLicSortLink('client', $sortBy, $sortDir, 'Client', $qp) ?></th>
                <th>Tags</th>
                <th><?= bcLicSortLink('customer', $sortBy, $sortDir, 'Customer Be-Cloud', $qp) ?></th>
                <th><?= bcLicSortLink('license', $sortBy, $sortDir, 'Licence M365', $qp) ?></th>
                <th class="text-center"><?= bcLicSortLink('total', $sortBy, $sortDir, 'Total', $qp) ?></th>
                <th class="text-center"><?= bcLicSortLink('consumed', $sortBy, $sortDir, 'Consommées', $qp) ?></th>
                <th class="text-center"><?= bcLicSortLink('available', $sortBy, $sortDir, 'Disponibles', $qp) ?></th>
                <th class="text-center">Suspendues</th>
                <th class="text-center" style="min-width:90px">Usage %</th>
                <th class="text-center">Sync</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($licenses)): ?>
            <tr>
                <td colspan="10" class="text-center text-body-secondary py-5">
                    <i class="bi bi-person-badge fs-1 d-block mb-2 opacity-25"></i>
                    Aucune licence trouvée.
                    <?php if (!$lastSync): ?>
                    <br><button class="btn btn-sm btn-primary mt-2" onclick="window.openSyncModal?.(null, 'becloud')">
                        <i class="bi bi-arrow-clockwise me-1"></i>Lancer la première sync
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($licenses as $lic):
                $total_l    = (int)($lic['total_licenses'] ?? 0);
                $consumed   = (int)($lic['consumed_licenses'] ?? 0);
                $available  = (int)($lic['available_licenses'] ?? 0);
                $suspended  = (int)($lic['suspended_licenses'] ?? 0);
                $pct        = $total_l > 0 ? round($consumed / $total_l * 100) : 0;
                $barClass   = $pct >= 90 ? 'bg-danger' : ($pct >= 70 ? 'bg-warning' : 'bg-success');
                $clientTags = bcLicParseTags($lic['client_tags_raw'] ?? null);
                $detailUrl  = '/becloud/client/' . (int)$lic['bc_customer_id'];
            ?>
            <tr class="cursor-pointer" onclick="window.location='<?= $detailUrl ?>'">
                <td>
                    <?php if ($lic['client_name']): ?>
                        <a href="<?= $detailUrl ?>" class="fw-medium text-decoration-none text-body"
                           onclick="event.stopPropagation()">
                            <?= htmlspecialchars($lic['client_name']) ?>
                        </a>
                        <?php if ($lic['client_number']): ?>
                        <br><small class="text-body-secondary font-monospace"><?= htmlspecialchars($lic['client_number']) ?></small>
                        <?php endif; ?>
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
                    <a href="<?= $detailUrl ?>" class="text-decoration-none text-body" onclick="event.stopPropagation()">
                        <?= htmlspecialchars($lic['customer_name']) ?>
                    </a>
                    <?php if (isset($lic['mapping_confirmed']) && $lic['mapping_confirmed'] === '0'): ?>
                        <span class="badge bg-warning text-dark ms-1 small">Mapping non confirmé</span>
                    <?php endif; ?>
                    <?php if ($lic['internal_identifier']): ?>
                        <br><code class="small text-body-secondary"><?= htmlspecialchars($lic['internal_identifier']) ?></code>
                    <?php endif; ?>
                </td>
                <td class="small">
                    <?= htmlspecialchars($lic['license_name'] ?? '—') ?>
                    <?php if ($lic['sku_id']): ?>
                    <br><code class="small text-body-secondary"><?= htmlspecialchars($lic['sku_id']) ?></code>
                    <?php endif; ?>
                </td>
                <td class="text-center"><?= number_format($total_l) ?></td>
                <td class="text-center"><?= number_format($consumed) ?></td>
                <td class="text-center <?= $available === 0 && $total_l > 0 ? 'text-danger fw-bold' : '' ?>">
                    <?= number_format($available) ?>
                </td>
                <td class="text-center <?= $suspended > 0 ? 'text-warning fw-medium' : 'text-body-secondary' ?>">
                    <?= $suspended > 0 ? number_format($suspended) : '—' ?>
                </td>
                <td style="min-width:90px">
                    <?php if ($total_l > 0): ?>
                    <div class="progress" style="height:6px">
                        <div class="progress-bar <?= $barClass ?>" style="width:<?= min($pct, 100) ?>%" title="<?= $pct ?>%"></div>
                    </div>
                    <small class="text-body-secondary"><?= $pct ?>%</small>
                    <?php else: ?>
                    <small class="text-body-secondary">—</small>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <small class="text-body-secondary" title="<?= htmlspecialchars($lic['last_sync_at'] ?? '') ?>">
                        <?php if (!empty($lic['last_sync_at'])): ?>
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

<?php
$totalPages = (int)ceil($total / $perPage);
$queryBase  = http_build_query(['search' => $search, 'tag' => $tagId ?: '', 'sort' => $sortBy, 'dir' => $sortDir, 'perPage' => $perPage]);
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

<style>
.cursor-pointer { cursor: pointer; }
.cursor-pointer:hover td { background-color: var(--bs-table-hover-bg); }
</style>

<script>
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
    a.download = 'becloud_licences_' + new Date().toISOString().slice(0,10) + '.csv';
    a.click();
    URL.revokeObjectURL(url);
});
</script>
