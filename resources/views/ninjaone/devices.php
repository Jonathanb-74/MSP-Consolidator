<?php
/**
 * @var array       $devices   Liste des équipements
 * @var array|false $client    Client filtré (ou null)
 * @var int         $total     Total d'équipements
 * @var int         $page
 * @var int         $perPage
 * @var string      $search
 * @var string      $group
 * @var string      $sortBy
 * @var string      $sortDir
 * @var int         $clientId
 */

use App\Core\AppSettings;

$activeThresholdDays = AppSettings::get('device_active_days', 2);
$activeThresholdSecs = $activeThresholdDays * 86400;

$groups = ['RMM', 'NMS', 'MDM', 'VMM', 'CLOUD_MONITORING'];

$groupLabels = [
    'RMM'              => ['label' => 'RMM',   'color' => 'success'],
    'NMS'              => ['label' => 'NMS',   'color' => 'info'],
    'MDM'              => ['label' => 'MDM',   'color' => 'primary'],
    'VMM'              => ['label' => 'VMM',   'color' => 'secondary'],
    'CLOUD_MONITORING' => ['label' => 'Cloud', 'color' => 'secondary'],
    'OTHER'            => ['label' => 'Autre', 'color' => 'dark'],
];

$totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;

function deviceSortUrl(string $col, string $currentSort, string $currentDir, array $extra = []): string {
    $dir = ($col === $currentSort && $currentDir === 'ASC') ? 'DESC' : 'ASC';
    return '/ninjaone/devices?' . http_build_query(array_merge($extra, ['sort' => $col, 'dir' => $dir]));
}

function sortIcon(string $col, string $currentSort, string $currentDir): string {
    if ($col !== $currentSort) return '<i class="bi bi-chevron-expand text-body-tertiary ms-1"></i>';
    return $currentDir === 'ASC'
        ? '<i class="bi bi-chevron-up ms-1"></i>'
        : '<i class="bi bi-chevron-down ms-1"></i>';
}

function formatLastContact(?string $dt): string {
    if (!$dt || $dt === '0000-00-00 00:00:00') return '<span class="text-body-tertiary">—</span>';
    $ts   = strtotime($dt);
    $diff = time() - $ts;
    if ($diff < 60)     $rel = "à l'instant";
    elseif ($diff < 3600)  $rel = 'il y a ' . floor($diff / 60) . ' min';
    elseif ($diff < 86400) $rel = 'il y a ' . floor($diff / 3600) . 'h';
    elseif ($diff < 604800) $rel = 'il y a ' . floor($diff / 86400) . 'j';
    else                    $rel = date('d/m/Y', $ts);
    return '<span title="' . htmlspecialchars(date('d/m/Y H:i', $ts)) . '">' . $rel . '</span>';
}

$extraParams = array_filter([
    'client_id' => $clientId ?: null,
    'search'    => $search,
    'group'     => $group,
    'perPage'   => $perPage,
]);
?>

<div class="page-sticky-top">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-0">
                <i class="bi bi-hdd-network me-2 text-warning"></i>
                Équipements NinjaOne
                <?php if ($client): ?>
                    <span class="text-body-secondary fw-normal">— <?= htmlspecialchars($client['name']) ?></span>
                <?php endif; ?>
            </h4>
            <p class="text-body-secondary small mb-0 mt-1">
                <?= number_format($total) ?> équipement<?= $total > 1 ? 's' : '' ?> trouvé<?= $total > 1 ? 's' : '' ?>
                <?php if ($client): ?>
                    · <a href="/ninjaone/devices" class="small">Voir tous les équipements</a>
                <?php endif; ?>
            </p>
        </div>
        <?php if ($client): ?>
        <a href="/licenses?search=<?= urlencode($client['name']) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Retour Récap
        </a>
        <?php endif; ?>
    </div>

    <!-- Filtres -->
    <form method="get" action="/ninjaone/devices" class="row g-2 mb-3 align-items-center">
        <?php if ($clientId): ?>
            <input type="hidden" name="client_id" value="<?= $clientId ?>">
        <?php endif; ?>
        <div class="col-auto flex-grow-1">
            <input type="text" class="form-control form-control-sm" name="search"
                   value="<?= htmlspecialchars($search) ?>" placeholder="Recherche nom, DNS, organisation…">
        </div>
        <div class="col-auto">
            <select name="group" class="form-select form-select-sm">
                <option value="">Tous les groupes</option>
                <?php foreach ($groups as $g): ?>
                    <option value="<?= $g ?>" <?= $group === $g ? 'selected' : '' ?>>
                        <?= $groupLabels[$g]['label'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <select name="perPage" class="form-select form-select-sm">
                <?php foreach ([50, 100, 250, 500] as $pp): ?>
                    <option value="<?= $pp ?>" <?= $perPage === $pp ? 'selected' : '' ?>><?= $pp ?> / page</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-search me-1"></i>Filtrer
            </button>
        </div>
    </form>
</div>

<p class="text-body-secondary small mb-2">
    <i class="bi bi-info-circle me-1"></i>
    Un équipement est <strong>Actif</strong> s'il est en ligne ou vu dans les
    <strong><?= $activeThresholdDays ?> dernier<?= $activeThresholdDays > 1 ? 's jours' : ' jour' ?></strong>.
    <a href="/settings/general" class="ms-1">Modifier ce seuil</a>
</p>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-dark">
                <tr>
                    <th class="ps-3" style="width:30px"></th>
                    <th>
                        <a href="<?= deviceSortUrl('name', $sortBy, $sortDir, $extraParams) ?>" class="text-decoration-none text-body">
                            Nom <?= sortIcon('name', $sortBy, $sortDir) ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?= deviceSortUrl('org', $sortBy, $sortDir, $extraParams) ?>" class="text-decoration-none text-body">
                            Organisation <?= sortIcon('org', $sortBy, $sortDir) ?>
                        </a>
                    </th>
                    <th style="width:120px">
                        <a href="<?= deviceSortUrl('group', $sortBy, $sortDir, $extraParams) ?>" class="text-decoration-none text-body">
                            Groupe <?= sortIcon('group', $sortBy, $sortDir) ?>
                        </a>
                    </th>
                    <th style="width:160px">
                        <a href="<?= deviceSortUrl('os', $sortBy, $sortDir, $extraParams) ?>" class="text-decoration-none text-body">
                            OS <?= sortIcon('os', $sortBy, $sortDir) ?>
                        </a>
                    </th>
                    <th style="width:160px">
                        <a href="<?= deviceSortUrl('brand', $sortBy, $sortDir, $extraParams) ?>" class="text-decoration-none text-body">
                            Marque / Modèle <?= sortIcon('brand', $sortBy, $sortDir) ?>
                        </a>
                    </th>
                    <th style="width:170px">
                        <a href="<?= deviceSortUrl('user', $sortBy, $sortDir, $extraParams) ?>" class="text-decoration-none text-body">
                            Dernier utilisateur <?= sortIcon('user', $sortBy, $sortDir) ?>
                        </a>
                    </th>
                    <th style="width:140px">
                        <a href="<?= deviceSortUrl('contact', $sortBy, $sortDir, $extraParams) ?>" class="text-decoration-none text-body">
                            Dernière connexion <?= sortIcon('contact', $sortBy, $sortDir) ?>
                        </a>
                    </th>
                    <th style="width:80px">
                        <a href="<?= deviceSortUrl('online', $sortBy, $sortDir, $extraParams) ?>" class="text-decoration-none text-body">
                            État <?= sortIcon('online', $sortBy, $sortDir) ?>
                        </a>
                    </th>
                </tr>
            </thead>
            <tbody>
<?php if (empty($devices)): ?>
                <tr>
                    <td colspan="9" class="text-center text-body-secondary py-4">
                        <i class="bi bi-hdd-network me-2"></i>
                        Aucun équipement trouvé.
                        <?php if (!$total): ?>
                            Relancez une synchronisation NinjaOne pour peupler cette table.
                        <?php endif; ?>
                    </td>
                </tr>
<?php else: ?>
<?php foreach ($devices as $d): ?>
<?php
    $g = $groupLabels[$d['node_group']] ?? $groupLabels['OTHER'];
    // Actif si is_online OU vu dans les N derniers jours (configurable dans /settings/general)
    $isOnline   = (bool)$d['is_online'];
    $recentSeen = $d['last_contact'] && (time() - strtotime($d['last_contact'])) < $activeThresholdSecs;
    $active     = $isOnline || $recentSeen;
?>
                <tr>
                    <td class="ps-3">
                        <span class="d-inline-block rounded-circle" style="width:8px;height:8px;background:<?= $active ? '#198754' : '#6c757d' ?>"
                              title="<?= $active ? 'En ligne / récemment actif' : 'Hors ligne' ?>"></span>
                    </td>
                    <td>
                        <div class="fw-semibold small"><?= htmlspecialchars($d['display_name'] ?: '—') ?></div>
                        <?php if ($d['dns_name'] && $d['dns_name'] !== $d['display_name']): ?>
                        <div class="text-body-secondary" style="font-size:.75em"><?= htmlspecialchars($d['dns_name']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="small text-body-secondary"><?= htmlspecialchars($d['org_name']) ?></td>
                    <td>
                        <span class="badge bg-<?= $g['color'] ?>"><?= $g['label'] ?></span>
                        <div class="text-body-tertiary" style="font-size:.7em"><?= htmlspecialchars($d['node_class']) ?></div>
                    </td>
                    <td class="small text-body-secondary"><?= htmlspecialchars($d['os_name'] ?? '—') ?></td>
                    <td class="small">
                        <?php if ($d['manufacturer'] || $d['model']): ?>
                            <div><?= htmlspecialchars($d['manufacturer'] ?? '') ?></div>
                            <div class="text-body-secondary" style="font-size:.85em"><?= htmlspecialchars($d['model'] ?? '') ?></div>
                        <?php else: ?>
                            <span class="text-body-tertiary">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="small text-body-secondary"><?= htmlspecialchars($d['last_logged_user'] ?? '—') ?></td>
                    <td class="small"><?= formatLastContact($d['last_contact']) ?></td>
                    <td>
                        <?php if ($active): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle">Actif</span>
                        <?php else: ?>
                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Inactif</span>
                        <?php endif; ?>
                    </td>
                </tr>
<?php endforeach; ?>
<?php endif; ?>
            </tbody>
        </table>
    </div>

<?php if ($totalPages > 1): ?>
    <div class="card-footer d-flex align-items-center justify-content-between">
        <small class="text-body-secondary">
            Page <?= $page ?> / <?= $totalPages ?> — <?= number_format($total) ?> équipement<?= $total > 1 ? 's' : '' ?>
        </small>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php for ($p = max(1, $page - 3); $p <= min($totalPages, $page + 3); $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="/ninjaone/devices?<?= http_build_query(array_merge($extraParams, ['sort' => $sortBy, 'dir' => $sortDir, 'page' => $p])) ?>">
                            <?= $p ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
<?php endif; ?>
</div>
