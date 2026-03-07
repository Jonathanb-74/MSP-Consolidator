<?php
/** @var array $organizations */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var string $search */
/** @var int $tagId */
/** @var string $group */
/** @var string $sortBy */
/** @var string $sortDir */
/** @var array|false $lastSync */
/** @var array $allTags */
/** @var array $connections */

function nSortLink(string $col, string $current, string $dir, string $label, array $queryParams): string {
    $newDir = ($current === $col && $dir === 'ASC') ? 'DESC' : 'ASC';
    $icon   = $current === $col ? ($dir === 'ASC' ? '↑' : '↓') : '';
    $params = array_merge($queryParams, ['sort' => $col, 'dir' => $newDir]);
    return '<a href="/ninjaone/licenses?' . http_build_query($params) . '" class="text-white text-decoration-none">'
         . $label . ($icon ? " $icon" : '') . '</a>';
}

$qp = compact('search', 'tagId', 'group', 'page', 'perPage');
?>

<div class="page-sticky-top">
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h1 class="h3 mb-0">
            <i class="bi bi-hdd-network text-warning me-2"></i>Équipements NinjaOne
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
        <a href="/mapping?provider=ninjaone" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-link-45deg me-1"></i>Mapping
        </a>
        <a href="/ninjaone/sync-logs" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-clock-history me-1"></i>Logs
        </a>
        <a href="#" class="btn btn-sm btn-outline-secondary" id="btnExportCsv">
            <i class="bi bi-download me-1"></i>CSV
        </a>
        <button class="btn btn-sm btn-warning text-dark" id="btnPageSync" onclick="window.openSyncModal?.(null, 'ninjaone')">
            <i class="bi bi-arrow-clockwise me-1"></i>Sync maintenant
        </button>
    </div>
</div>

<!-- Filtres -->
<form method="GET" action="/ninjaone/licenses" class="row g-2 mb-2" id="filterForm">
    <div class="col-md-4">
        <div class="input-group input-group-sm">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" name="search" class="form-control"
                   placeholder="Client, organisation…"
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
        <select name="group" class="form-select form-select-sm">
            <option value="">Tous les groupes</option>
            <option value="RMM"   <?= $group === 'RMM'   ? 'selected' : '' ?>>RMM License</option>
            <option value="NMS"   <?= $group === 'NMS'   ? 'selected' : '' ?>>NMS License</option>
            <option value="MDM"   <?= $group === 'MDM'   ? 'selected' : '' ?>>MDM License</option>
            <option value="VMM"   <?= $group === 'VMM'   ? 'selected' : '' ?>>VMM (no license)</option>
            <option value="CLOUD" <?= $group === 'CLOUD' ? 'selected' : '' ?>>Cloud Monitoring</option>
        </select>
    </div>
    <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
    <input type="hidden" name="dir"  value="<?= htmlspecialchars($sortDir) ?>">
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-outline-secondary">Filtrer</button>
        <a href="/ninjaone/licenses" class="btn btn-sm btn-outline-secondary">Reset</a>
    </div>
</form>
</div>

<!-- Tableau -->
<div class="table-responsive">
    <table class="table table-hover table-sm align-middle" id="licensesTable">
        <thead class="table-dark small">
            <tr>
                <th><?= nSortLink('client', $sortBy, $sortDir, 'Client', $qp) ?></th>
                <th>Tags</th>
                <th><?= nSortLink('org', $sortBy, $sortDir, 'Organisation NinjaOne', $qp) ?></th>
                <th class="text-center">
                    <?= nSortLink('rmm', $sortBy, $sortDir, 'RMM', $qp) ?>
                    <br><small class="opacity-75 fw-normal">License</small>
                </th>
                <th class="text-center">
                    <?= nSortLink('nms', $sortBy, $sortDir, 'NMS', $qp) ?>
                    <br><small class="opacity-75 fw-normal">License</small>
                </th>
                <th class="text-center">
                    <?= nSortLink('mdm', $sortBy, $sortDir, 'MDM', $qp) ?>
                    <br><small class="opacity-75 fw-normal">License</small>
                </th>
                <th class="text-center">
                    <?= nSortLink('vmm', $sortBy, $sortDir, 'VMM', $qp) ?>
                    <br><small class="opacity-75 fw-normal">no license</small>
                </th>
                <th class="text-center">
                    <?= nSortLink('cloud', $sortBy, $sortDir, 'Cloud', $qp) ?>
                    <br><small class="opacity-75 fw-normal">no license</small>
                </th>
                <th class="text-center">Sync</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($organizations)): ?>
            <tr>
                <td colspan="9" class="text-center text-body-secondary py-5">
                    <i class="bi bi-hdd-network fs-1 d-block mb-2 opacity-25"></i>
                    Aucune organisation trouvée.
                    <?php if (!$lastSync): ?>
                    <br><button class="btn btn-sm btn-warning text-dark mt-2" id="btnFirstSync">
                        <i class="bi bi-arrow-clockwise me-1"></i>Lancer la première sync
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($organizations as $org):
                $rmm   = (int)$org['rmm_count'];
                $nms   = (int)$org['nms_count'];
                $mdm   = (int)$org['mdm_count'];
                $vmm   = (int)$org['vmm_count'];
                $cloud = (int)$org['cloud_count'];

                $tagNames  = $org['tag_names']  ? explode('|||', $org['tag_names'])  : [];
                $tagColors = $org['tag_colors'] ? explode('|||', $org['tag_colors']) : [];
            ?>
            <tr>
                <td>
                    <span class="fw-medium"><?= htmlspecialchars($org['client_name']) ?></span>
                    <?php if ($org['client_number']): ?>
                    <br><small class="text-body-secondary font-monospace"><?= htmlspecialchars($org['client_number']) ?></small>
                    <?php endif; ?>
                </td>
                <td style="min-width:100px">
                    <?php if (!empty($tagNames)): ?>
                        <div class="d-flex flex-wrap gap-1">
                            <?php foreach ($tagNames as $i => $tName): ?>
                            <span class="badge rounded-pill" style="background-color:<?= htmlspecialchars($tagColors[$i] ?? '#6c757d') ?>">
                                <?= htmlspecialchars($tName) ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <span class="text-body-secondary">—</span>
                    <?php endif; ?>
                </td>
                <td class="small">
                    <?= htmlspecialchars($org['name']) ?>
                    <br><small class="text-body-secondary"><?= htmlspecialchars($org['connection_name'] ?? '') ?></small>
                </td>
                <td class="text-center">
                    <?php if ($rmm > 0): ?>
                    <span class="badge bg-success fs-6 px-2"><?= number_format($rmm) ?></span>
                    <?php else: ?>
                    <span class="text-body-secondary">—</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <?php if ($nms > 0): ?>
                    <span class="badge bg-info fs-6 px-2"><?= number_format($nms) ?></span>
                    <?php else: ?>
                    <span class="text-body-secondary">—</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <?php if ($mdm > 0): ?>
                    <span class="badge bg-primary fs-6 px-2"><?= number_format($mdm) ?></span>
                    <?php else: ?>
                    <span class="text-body-secondary">—</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <?php if ($vmm > 0): ?>
                    <span class="badge bg-secondary fs-6 px-2" title="VMM - no license"><?= number_format($vmm) ?></span>
                    <?php else: ?>
                    <span class="text-body-secondary">—</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <?php if ($cloud > 0): ?>
                    <span class="badge bg-secondary fs-6 px-2" title="Cloud Monitoring - no license"><?= number_format($cloud) ?></span>
                    <?php else: ?>
                    <span class="text-body-secondary">—</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <small class="text-body-secondary" title="<?= htmlspecialchars($org['last_sync_at'] ?? '') ?>">
                        <?php if ($org['last_sync_at']): ?>
                            <?= date('d/m H:i', strtotime($org['last_sync_at'])) ?>
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
$queryBase  = http_build_query(['search' => $search, 'tag' => $tagId ?: '', 'group' => $group, 'sort' => $sortBy, 'dir' => $sortDir, 'perPage' => $perPage]);
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
                <a class="page-link" href="/ninjaone/licenses?<?= $queryBase ?>&page=<?= $i ?>"><?= $i ?></a>
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
    a.download = 'ninjaone_equipements_' + new Date().toISOString().slice(0,10) + '.csv';
    a.click();
    URL.revokeObjectURL(url);
});
</script>
