<?php
/** @var array  $products     */
/** @var int    $total        */
/** @var int    $page         */
/** @var int    $perPage      */
/** @var string $search       */
/** @var string $serviceName  */
/** @var string $sortBy       */
/** @var string $sortDir      */
/** @var array  $serviceNames */

function ikpSortLink(string $col, string $current, string $dir, string $label): string {
    $newDir = ($current === $col && $dir === 'ASC') ? 'DESC' : 'ASC';
    $icon   = $current === $col ? ($dir === 'ASC' ? ' ↑' : ' ↓') : '';
    $params = array_merge(
        array_filter(['search' => $_GET['search'] ?? '', 'service_name' => $_GET['service_name'] ?? '', 'perPage' => $_GET['perPage'] ?? '']),
        ['sort' => $col, 'dir' => $newDir]
    );
    return '<a href="/infomaniak/products?' . http_build_query($params) . '" class="text-white text-decoration-none">'
         . $label . $icon . '</a>';
}

$now      = time();
$in30Days = $now + 30 * 86400;
?>

<div class="page-sticky-top">
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h1 class="h3 mb-0">
            <i class="bi bi-server text-danger me-2"></i>
            Tous les produits Infomaniak
            <span class="badge bg-secondary ms-2"><?= number_format($total) ?></span>
        </h1>
    </div>
    <div class="d-flex gap-2">
        <a href="/infomaniak/licenses" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-people me-1"></i>Vue par compte
        </a>
        <a href="/mapping?provider=infomaniak" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-link-45deg me-1"></i>Mapping
        </a>
    </div>
</div>

<!-- Filtres -->
<form method="GET" action="/infomaniak/products" id="ikpFilterForm" class="d-flex flex-wrap gap-2 align-items-center mb-3">

    <div class="input-group input-group-sm" style="width:280px">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="search" class="form-control"
               placeholder="Client, produit, nom client, compte…"
               value="<?= htmlspecialchars($search) ?>">
        <?php if ($search !== ''): ?>
        <button type="button" class="btn btn-outline-secondary"
                onclick="document.querySelector('[name=search]').value='';this.form.submit()">
            <i class="bi bi-x-lg"></i>
        </button>
        <?php endif; ?>
    </div>

    <?php if (!empty($serviceNames)): ?>
    <select name="service_name" class="form-select form-select-sm" style="width:auto"
            onchange="this.form.submit()">
        <option value="">Tous les types</option>
        <?php foreach ($serviceNames as $sn): ?>
        <option value="<?= htmlspecialchars($sn['service_name']) ?>"
                <?= $serviceName === $sn['service_name'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($sn['service_name']) ?>
        </option>
        <?php endforeach; ?>
    </select>
    <?php endif; ?>

    <button type="submit" class="btn btn-sm btn-primary">
        <i class="bi bi-search me-1"></i>Rechercher
    </button>

    <?php if ($search !== '' || $serviceName !== ''): ?>
    <a href="/infomaniak/products" class="btn btn-sm btn-outline-danger">
        <i class="bi bi-x-lg me-1"></i>Réinitialiser
    </a>
    <?php endif; ?>

    <input type="hidden" name="sort"    value="<?= htmlspecialchars($sortBy) ?>">
    <input type="hidden" name="dir"     value="<?= htmlspecialchars($sortDir) ?>">
    <input type="hidden" name="perPage" value="<?= $perPage ?>">
</form>
</div>

<div class="table-responsive">
<table class="table table-sm table-hover align-middle">
    <thead class="table-dark small">
        <tr>
            <th><?= ikpSortLink('client', $sortBy, $sortDir, 'Client') ?></th>
            <th><?= ikpSortLink('service', $sortBy, $sortDir, 'Service') ?></th>
            <th><?= ikpSortLink('name', $sortBy, $sortDir, 'Produit') ?></th>
            <th><?= ikpSortLink('customer', $sortBy, $sortDir, 'Nom client produit') ?></th>
            <th><?= ikpSortLink('account', $sortBy, $sortDir, 'Compte Infomaniak') ?></th>
            <th class="text-center"><?= ikpSortLink('expires', $sortBy, $sortDir, 'Expiration') ?></th>
            <th class="text-center">Statut</th>
            <th class="text-center text-body-secondary" style="font-weight:normal;font-size:.75rem">Sync</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($products)): ?>
    <tr>
        <td colspan="8" class="text-center text-body-secondary py-5">
            <i class="bi bi-server fs-1 d-block mb-2 opacity-25"></i>
            Aucun produit trouvé.
            <?php if ($search !== '' || $serviceName !== ''): ?>
            <br><a href="/infomaniak/products" class="small">Réinitialiser les filtres</a>
            <?php endif; ?>
        </td>
    </tr>
    <?php else: ?>
    <?php foreach ($products as $p):
        $exp       = $p['expired_at'] ? (int)$p['expired_at'] : null;
        $isExpired = $exp && $exp < $now;
        $isSoon    = $exp && !$isExpired && $exp <= $in30Days;
    ?>
    <tr <?= $isExpired ? 'class="table-danger"' : ($isSoon ? 'class="table-warning"' : '') ?>>
        <td>
            <?php if ($p['client_id']): ?>
                <a href="/infomaniak/client/<?= (int)$p['client_id'] ?>" class="fw-medium text-decoration-none">
                    <?= htmlspecialchars($p['client_name']) ?>
                </a>
                <?php if ($p['client_number']): ?>
                <br><small class="text-body-secondary font-monospace"><?= htmlspecialchars($p['client_number']) ?></small>
                <?php endif; ?>
            <?php else: ?>
                <em class="text-body-secondary small">Non mappé</em>
            <?php endif; ?>
        </td>
        <td>
            <span class="badge bg-secondary"><?= htmlspecialchars($p['service_name'] ?? '—') ?></span>
        </td>
        <td class="small">
            <?= $p['internal_name'] ? htmlspecialchars($p['internal_name']) : '<em class="text-body-secondary">—</em>' ?>
        </td>
        <td class="small text-body-secondary">
            <?= htmlspecialchars($p['customer_name'] ?? '—') ?>
        </td>
        <td class="small text-body-secondary">
            <?= htmlspecialchars($p['account_name']) ?>
        </td>
        <td class="text-center small <?= $isExpired ? 'text-danger fw-bold' : ($isSoon ? 'text-warning fw-bold' : '') ?>">
            <?= $exp ? date('d/m/Y', $exp) : '—' ?>
        </td>
        <td class="text-center">
            <?php if ($isExpired): ?>
                <span class="badge bg-danger">Expiré</span>
            <?php elseif ($isSoon): ?>
                <span class="badge bg-warning text-dark">Bientôt</span>
            <?php else: ?>
                <span class="badge bg-success">Actif</span>
            <?php endif; ?>
            <?php if ($p['is_trial']): ?>
                <span class="badge bg-info">Essai</span>
            <?php endif; ?>
            <?php if ($p['is_free']): ?>
                <span class="badge bg-secondary">Gratuit</span>
            <?php endif; ?>
        </td>
        <td class="text-center small text-body-secondary">
            <?= $p['last_sync_at'] ? date('d/m H:i', strtotime($p['last_sync_at'])) : '—' ?>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>
</div>

<?php
$totalPages = (int)ceil($total / $perPage);
$queryBase  = http_build_query(array_filter(['search' => $search, 'service_name' => $serviceName, 'sort' => $sortBy, 'dir' => $sortDir, 'perPage' => $perPage]));
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
                <a class="page-link" href="/infomaniak/products?<?= $queryBase ?>&page=<?= $i ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
            <?php if ($totalPages > 20): ?>
            <li class="page-item disabled"><span class="page-link">…</span></li>
            <?php endif; ?>
        </ul></nav>
        <?php endif; ?>
    </div>
</div>
