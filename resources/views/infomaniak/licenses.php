<?php
/** @var array $accounts */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var string $search */
/** @var int $tagId */
/** @var string $serviceName */
/** @var string $sortBy */
/** @var string $sortDir */
/** @var array|false $lastSync */
/** @var array $allTags */
/** @var array $serviceNames */
/** @var array $connections */

function ikSortLink(string $col, string $current, string $dir, string $label, array $queryParams): string {
    $newDir = ($current === $col && $dir === 'ASC') ? 'DESC' : 'ASC';
    $icon   = $current === $col ? ($dir === 'ASC' ? '↑' : '↓') : '';
    $params = array_merge($queryParams, ['sort' => $col, 'dir' => $newDir]);
    return '<a href="/infomaniak/licenses?' . http_build_query($params) . '" class="text-white text-decoration-none">'
         . $label . ($icon ? " $icon" : '') . '</a>';
}

function ikParseTags(?string $raw): array {
    if (!$raw) return [];
    return array_map(function($part) {
        [$id, $name, $color] = array_pad(explode(':', $part, 3), 3, '');
        return ['id' => (int)$id, 'name' => $name, 'color' => $color];
    }, explode(';;', $raw));
}

$qp = compact('search', 'tagId', 'serviceName', 'page', 'perPage');
?>

<div class="page-sticky-top">
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h1 class="h3 mb-0">
            Produits Infomaniak
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
        <a href="/mapping?provider=infomaniak" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-link-45deg me-1"></i>Mapping
        </a>
        <a href="/infomaniak/sync-logs" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-clock-history me-1"></i>Logs
        </a>
        <a href="#" class="btn btn-sm btn-outline-secondary" id="btnExportCsv">
            <i class="bi bi-download me-1"></i>CSV
        </a>
        <button class="btn btn-sm btn-primary" id="btnPageSync" onclick="window.openSyncModal?.(null, 'infomaniak')">
            <i class="bi bi-arrow-clockwise me-1"></i>Sync maintenant
        </button>
    </div>
</div>

<!-- Filtres -->
<form method="GET" action="/infomaniak/licenses" class="row g-2 mb-2" id="filterForm">
    <div class="col-md-4">
        <div class="input-group input-group-sm">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" name="search" class="form-control"
                   placeholder="Client, compte, service…"
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
        <select name="service_name" class="form-select form-select-sm">
            <option value="">Tous les services</option>
            <?php foreach ($serviceNames as $sn): ?>
                <option value="<?= htmlspecialchars($sn['service_name']) ?>"
                    <?= $serviceName === $sn['service_name'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($sn['service_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
    <input type="hidden" name="dir"  value="<?= htmlspecialchars($sortDir) ?>">
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-outline-secondary">Filtrer</button>
        <a href="/infomaniak/licenses" class="btn btn-sm btn-outline-secondary">Reset</a>
    </div>
</form>
</div>

<!-- Tableau -->
<div class="table-responsive">
    <table class="table table-hover table-sm align-middle" id="licensesTable">
        <thead class="table-dark small">
            <tr>
                <th><?= ikSortLink('client', $sortBy, $sortDir, 'Client', $qp) ?></th>
                <th>Tags</th>
                <th><?= ikSortLink('account', $sortBy, $sortDir, 'Compte Infomaniak', $qp) ?></th>
                <th><?= ikSortLink('service', $sortBy, $sortDir, 'Services', $qp) ?></th>
                <th class="text-center">Produits</th>
                <th class="text-center"><?= ikSortLink('expires', $sortBy, $sortDir, 'Prochaine expiration', $qp) ?></th>
                <th class="text-center">Statut</th>
                <th class="text-center">Sync</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($accounts)): ?>
            <tr>
                <td colspan="8" class="text-center text-body-secondary py-5">
                    <i class="bi bi-hdd-rack fs-1 d-block mb-2 opacity-25"></i>
                    Aucun compte Infomaniak trouvé.
                    <?php if (!$lastSync): ?>
                    <br><button class="btn btn-sm btn-primary mt-2" id="btnFirstSync">
                        <i class="bi bi-arrow-clockwise me-1"></i>Lancer la première sync
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($accounts as $acc):
                $clientTags = ikParseTags($acc['client_tags_raw'] ?? null);

                $nextExpiry     = $acc['next_expiry'] ? (int)$acc['next_expiry'] : null;
                $now            = time();
                $in30Days       = $now + 30 * 86400;
                $isExpired      = $nextExpiry && $nextExpiry < $now;
                $isExpiringSoon = $nextExpiry && $nextExpiry >= $now && $nextExpiry <= $in30Days;

                $expiredCount      = (int)$acc['expired_count'];
                $expiringSoonCount = (int)$acc['expiring_soon_count'];
                $productCount      = (int)$acc['product_count'];
            ?>
            <tr>
                <td>
                    <?php if ($acc['client_name']): ?>
                        <a href="/infomaniak/client/<?= (int)$acc['client_id'] ?>" class="fw-medium text-decoration-none">
                            <?= htmlspecialchars($acc['client_name']) ?>
                        </a>
                        <br><small class="text-body-secondary font-monospace"><?= htmlspecialchars($acc['client_number']) ?></small>
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
                    <?= htmlspecialchars($acc['account_name']) ?>
                    <?php if ($acc['mapping_confirmed'] === '0'): ?>
                        <span class="badge bg-warning text-dark ms-1 small">Mapping non confirmé</span>
                    <?php endif; ?>
                    <?php if ($acc['account_type']): ?>
                        <br><span class="text-body-secondary"><?= htmlspecialchars($acc['account_type']) ?></span>
                    <?php endif; ?>
                </td>
                <td class="small">
                    <?php
                    $services = array_filter(explode(', ', $acc['services_list'] ?? ''));
                    foreach ($services as $svc): ?>
                    <span class="badge bg-secondary me-1"><?= htmlspecialchars($svc) ?></span>
                    <?php endforeach; ?>
                </td>
                <td class="text-center">
                    <?php if ($acc['client_id']): ?>
                        <a href="/infomaniak/client/<?= (int)$acc['client_id'] ?>" class="text-decoration-none">
                            <?= $productCount ?>
                        </a>
                    <?php else: ?>
                        <?= $productCount ?>
                    <?php endif; ?>
                </td>
                <td class="text-center small <?= $isExpired ? 'text-danger' : ($isExpiringSoon ? 'text-warning' : '') ?>">
                    <?= $nextExpiry ? date('d/m/Y', $nextExpiry) : '—' ?>
                </td>
                <td class="text-center">
                    <?php if ($expiredCount > 0): ?>
                        <span class="badge bg-danger"><?= $expiredCount ?> expiré<?= $expiredCount > 1 ? 's' : '' ?></span>
                    <?php elseif ($expiringSoonCount > 0): ?>
                        <span class="badge bg-warning text-dark"><?= $expiringSoonCount ?> expire bientôt</span>
                    <?php else: ?>
                        <span class="badge bg-success">OK</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <small class="text-body-secondary" title="<?= htmlspecialchars($acc['last_sync_at'] ?? '') ?>">
                        <?php if ($acc['last_sync_at']): ?>
                            <?= date('d/m H:i', strtotime($acc['last_sync_at'])) ?>
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
$queryBase  = http_build_query(['search' => $search, 'tag' => $tagId ?: '', 'service_name' => $serviceName, 'sort' => $sortBy, 'dir' => $sortDir, 'perPage' => $perPage]);
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
                <a class="page-link" href="/infomaniak/licenses?<?= $queryBase ?>&page=<?= $i ?>"><?= $i ?></a>
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
document.getElementById('btnFirstSync')?.addEventListener('click', () => window.openSyncModal?.(null, 'infomaniak'));

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
    a.download = 'infomaniak_produits_' + new Date().toISOString().slice(0,10) + '.csv';
    a.click();
    URL.revokeObjectURL(url);
});
</script>
