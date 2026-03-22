<?php
/** @var array $customer      — infos customer Be-Cloud + mapping client système */
/** @var array $licenses      — licences M365 (be_cloud_licenses) */
/** @var array $subscriptions — abonnements (be_cloud_subscriptions) avec list_price */

$h = fn(mixed $v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$today    = new DateTime();
$in30Days = new DateTime('+30 days');
?>

<!-- Header -->
<div class="d-flex align-items-start justify-content-between mb-4">
    <div>
        <nav aria-label="breadcrumb" class="mb-1">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="/becloud/customers">Clients Be-Cloud</a></li>
                <li class="breadcrumb-item active"><?= $h($customer['name']) ?></li>
            </ol>
        </nav>
        <h1 class="h3 mb-1"><?= $h($customer['name']) ?></h1>
        <div class="d-flex flex-wrap gap-3 text-body-secondary small">
            <span><i class="bi bi-plug me-1"></i><?= $h($customer['connection_name'] ?? '—') ?></span>
            <?php if (!empty($customer['internal_identifier'])): ?>
            <span><i class="bi bi-hash me-1"></i><?= $h($customer['internal_identifier']) ?></span>
            <?php endif; ?>
            <?php if (!empty($customer['client_name'])): ?>
            <span>
                <i class="bi bi-building me-1"></i>
                <strong class="text-body"><?= $h($customer['client_name']) ?></strong>
                <?php if (!empty($customer['client_number'])): ?>
                    <span class="font-monospace">(<?= $h($customer['client_number']) ?>)</span>
                <?php endif; ?>
                <?php if (isset($customer['is_confirmed']) && (int)$customer['is_confirmed'] === 0): ?>
                    <span class="badge bg-warning text-dark ms-1">Mapping non confirmé</span>
                <?php endif; ?>
            </span>
            <?php else: ?>
            <span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Aucun client système mappé</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="/mapping?provider=becloud" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-link-45deg me-1"></i>Mapping
        </a>
        <a href="/becloud/licenses" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-list-ul me-1"></i>Licences
        </a>
        <a href="/becloud/customers" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Retour
        </a>
    </div>
</div>

<!-- Tableau Licences M365 -->
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="mb-0">
            <i class="bi bi-person-badge me-2 text-primary"></i>
            Licences M365
            <span class="badge bg-secondary ms-2"><?= count($licenses) ?></span>
        </h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($licenses)): ?>
        <div class="text-center text-body-secondary py-4">
            <i class="bi bi-person-badge fs-2 d-block mb-2 opacity-25"></i>
            Aucune licence synchronisée pour ce client.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>Licence</th>
                        <th>SKU</th>
                        <th class="text-center">Total</th>
                        <th class="text-center">Consommées</th>
                        <th class="text-center">Disponibles</th>
                        <th class="text-center">Suspendues</th>
                        <th class="text-center" style="min-width:100px">Usage %</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($licenses as $lic):
                    $total_l   = (int)($lic['total_licenses'] ?? 0);
                    $consumed  = (int)($lic['consumed_licenses'] ?? 0);
                    $available = (int)($lic['available_licenses'] ?? 0);
                    $suspended = (int)($lic['suspended_licenses'] ?? 0);
                    $pct       = $total_l > 0 ? round($consumed / $total_l * 100) : 0;
                    $barClass  = $pct >= 90 ? 'bg-danger' : ($pct >= 70 ? 'bg-warning' : 'bg-success');
                ?>
                <tr>
                    <td class="fw-medium"><?= $h($lic['name'] ?? '—') ?></td>
                    <td><code class="small text-body-secondary"><?= $h($lic['sku_id'] ?? '') ?></code></td>
                    <td class="text-center"><?= number_format($total_l) ?></td>
                    <td class="text-center"><?= number_format($consumed) ?></td>
                    <td class="text-center <?= $available === 0 && $total_l > 0 ? 'text-danger fw-bold' : '' ?>">
                        <?= number_format($available) ?>
                    </td>
                    <td class="text-center <?= $suspended > 0 ? 'text-warning fw-medium' : 'text-body-secondary' ?>">
                        <?= $suspended > 0 ? number_format($suspended) : '—' ?>
                    </td>
                    <td style="min-width:100px">
                        <?php if ($total_l > 0): ?>
                        <div class="progress mb-1" style="height:6px">
                            <div class="progress-bar <?= $barClass ?>" style="width:<?= min($pct, 100) ?>%"></div>
                        </div>
                        <small class="text-body-secondary"><?= $pct ?>%
                            (<?= number_format($consumed) ?> / <?= number_format($total_l) ?>)
                        </small>
                        <?php else: ?>
                        <small class="text-body-secondary">—</small>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Tableau Abonnements -->
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="mb-0">
            <i class="bi bi-receipt me-2 text-secondary"></i>
            Abonnements
            <span class="badge bg-secondary ms-2"><?= count($subscriptions) ?></span>
        </h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($subscriptions)): ?>
        <div class="text-center text-body-secondary py-4">
            <i class="bi bi-receipt fs-2 d-block mb-2 opacity-25"></i>
            Aucun abonnement synchronisé pour ce client.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" id="subSortTable">
                <thead class="table-light small">
                    <tr>
                        <th data-col="0" data-type="str"  class="bc-sortable">Offre</th>
                        <th data-col="1" data-type="str"  class="bc-sortable">Type</th>
                        <th data-col="2" data-type="str"  class="bc-sortable text-center">Statut</th>
                        <th data-col="3" data-type="num"  class="bc-sortable text-center">Qté</th>
                        <th data-col="4" data-type="date" class="bc-sortable">Début</th>
                        <th data-col="5" data-type="date" class="bc-sortable">Fin</th>
                        <th data-col="6" data-type="str"  class="bc-sortable">Fréquence</th>
                        <th data-col="7" data-type="str"  class="bc-sortable">Durée</th>
                        <th data-col="8" data-type="num"  class="bc-sortable text-end">Prix unit.</th>
                        <th class="text-center">Options</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($subscriptions as $sub):
                    $endDate      = !empty($sub['end_date']) ? new DateTime($sub['end_date']) : null;
                    $expiringSoon = $endDate && $endDate >= $today && $endDate <= $in30Days;
                    $statusVal    = $sub['status'] ?? '';

                    if ($expiringSoon) {
                        $statusBadge = '<span class="badge bg-warning text-dark">Expire bientôt</span>';
                    } elseif ($statusVal === 'Active') {
                        $statusBadge = '<span class="badge bg-success">Actif</span>';
                    } elseif ($statusVal === 'Suspended') {
                        $statusBadge = '<span class="badge bg-warning text-dark">Suspendu</span>';
                    } elseif (in_array($statusVal, ['Deleted', 'Expired']) || ($endDate && $endDate < $today)) {
                        $statusBadge = '<span class="badge bg-danger">' . $h($statusVal ?: 'Expiré') . '</span>';
                    } else {
                        $statusBadge = '<span class="badge bg-secondary">' . $h($statusVal ?: '?') . '</span>';
                    }

                    $price    = $sub['list_price'] ?? null;
                    $currency = $sub['currency'] ?? '';
                    $priceStr = ($price !== null && $price !== '') ? number_format((float)$price, 2) . ' ' . $h($currency) : '—';
                    $priceVal = ($price !== null && $price !== '') ? (float)$price : -1;
                ?>
                <tr>
                    <td data-val="<?= $h($sub['subscription_name'] ?? $sub['offer_name'] ?? '') ?>">
                        <span class="fw-medium small"><?= $h($sub['subscription_name'] ?? $sub['offer_name'] ?? '—') ?></span>
                    </td>
                    <td class="small text-body-secondary" data-val="<?= $h($sub['offer_type'] ?? '') ?>"><?= $h($sub['offer_type'] ?? '—') ?></td>
                    <td class="text-center" data-val="<?= $h($statusVal) ?>"><?= $statusBadge ?></td>
                    <td class="text-center" data-val="<?= (int)($sub['quantity'] ?? 0) ?>"><?= number_format((int)($sub['quantity'] ?? 0)) ?></td>
                    <td class="small"       data-val="<?= $sub['start_date'] ?? '' ?>"><?= !empty($sub['start_date']) ? date('d/m/Y', strtotime($sub['start_date'])) : '—' ?></td>
                    <td class="small <?= $expiringSoon ? 'text-warning fw-medium' : ($endDate && $endDate < $today ? 'text-danger' : '') ?>"
                        data-val="<?= $sub['end_date'] ?? '' ?>">
                        <?= $endDate ? date('d/m/Y', $endDate->getTimestamp()) : '—' ?>
                    </td>
                    <td class="small text-body-secondary" data-val="<?= $h($sub['billing_frequency'] ?? '') ?>"><?= $h($sub['billing_frequency'] ?? '—') ?></td>
                    <td class="small text-body-secondary" data-val="<?= $h($sub['term_duration'] ?? '') ?>"><?= $h($sub['term_duration'] ?? '—') ?></td>
                    <td class="text-end small" data-val="<?= $priceVal ?>"><?= $priceStr ?></td>
                    <td class="text-center">
                        <?php if (!empty($sub['is_trial'])): ?>
                            <span class="badge bg-info me-1">Trial</span>
                        <?php endif; ?>
                        <?php if (!empty($sub['auto_renewal'])): ?>
                            <span class="badge bg-secondary" title="Renouvellement automatique">Auto</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

<style>
.bc-sortable { cursor: pointer; user-select: none; white-space: nowrap; }
.bc-sortable:hover { filter: brightness(0.95); }
.bc-sort-asc::after  { content: ' ↑'; opacity: .7; }
.bc-sort-desc::after { content: ' ↓'; opacity: .7; }
</style>

<script>
(function () {
    const table = document.getElementById('subSortTable');
    if (!table) return;

    let sortCol = null, sortDir = 1;

    table.querySelectorAll('th.bc-sortable').forEach(th => {
        th.addEventListener('click', () => {
            const col  = parseInt(th.dataset.col, 10);
            const type = th.dataset.type;

            if (sortCol === col) {
                sortDir *= -1;
            } else {
                sortCol = col;
                sortDir = 1;
            }

            // Update header indicators
            table.querySelectorAll('th.bc-sortable').forEach(h => {
                h.classList.remove('bc-sort-asc', 'bc-sort-desc');
            });
            th.classList.add(sortDir === 1 ? 'bc-sort-asc' : 'bc-sort-desc');

            const tbody = table.tBodies[0];
            const rows  = Array.from(tbody.rows);

            rows.sort((a, b) => {
                const av = a.cells[col]?.dataset?.val ?? '';
                const bv = b.cells[col]?.dataset?.val ?? '';

                let cmp = 0;
                if (type === 'num') {
                    cmp = (parseFloat(av) || 0) - (parseFloat(bv) || 0);
                } else if (type === 'date') {
                    cmp = (av || '').localeCompare(bv || '');
                } else {
                    cmp = av.localeCompare(bv, undefined, { sensitivity: 'base' });
                }
                return cmp * sortDir;
            });

            rows.forEach(r => tbody.appendChild(r));
        });
    });
})();
</script>
        <?php endif; ?>
    </div>
</div>
