<?php
/** @var array  $customers */
/** @var int    $total */
/** @var int    $page */
/** @var int    $perPage */
/** @var string $search */
/** @var int    $connectionId */
/** @var array  $connections */
/** @var array|false $lastSync */

function bcCustH(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$qp = compact('search', 'connectionId', 'page', 'perPage');
$totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;
?>

<div class="page-sticky-top">
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h1 class="h3 mb-0">
            Clients Be-Cloud
            <span class="badge bg-secondary ms-2"><?= number_format($total) ?></span>
        </h1>
        <?php if ($lastSync): ?>
        <small class="text-body-secondary">
            Dernière sync : <?= date('d/m/Y H:i', strtotime($lastSync['finished_at'])) ?>
            <span class="badge bg-<?= $lastSync['status'] === 'success' ? 'success' : 'warning' ?> ms-1">
                <?= bcCustH($lastSync['status']) ?>
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
        <a href="/becloud/licenses" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-list-ul me-1"></i>Abonnements
        </a>
        <a href="/becloud/sync-logs" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-clock-history me-1"></i>Logs
        </a>
    </div>
</div>

<!-- Filtres -->
<form method="GET" action="/becloud/customers" id="bcCustFilterForm" class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <div class="input-group input-group-sm" style="width:260px">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="search" class="form-control"
               placeholder="Nom customer, client système…"
               value="<?= bcCustH($search) ?>">
    </div>
    <?php if (count($connections) > 1): ?>
    <select name="connection_id" class="form-select form-select-sm" style="width:auto">
        <option value="0">Toutes les connexions</option>
        <?php foreach ($connections as $conn): ?>
        <option value="<?= (int)$conn['id'] ?>" <?= $connectionId === (int)$conn['id'] ? 'selected' : '' ?>>
            <?= bcCustH($conn['name']) ?>
        </option>
        <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <button type="submit" class="btn btn-sm btn-primary">Filtrer</button>
    <?php if ($search || $connectionId): ?>
    <a href="/becloud/customers" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-x-lg"></i>
    </a>
    <?php endif; ?>
</form>
</div>

<?php if (empty($customers)): ?>
<div class="alert alert-secondary">Aucun client trouvé. Lancez une synchronisation.</div>
<?php else: ?>

<div class="table-responsive">
<table class="table table-sm table-hover align-middle" id="bcCustTable">
    <thead class="table-dark">
        <tr>
            <th style="width:2rem"></th>
            <th>Customer Be-Cloud</th>
            <?php if (count($connections) > 1): ?>
            <th>Connexion</th>
            <?php endif; ?>
            <th>Client système</th>
            <th class="text-center">Abonnements</th>
            <th class="text-center">Licences</th>
            <th class="text-center">Conso.</th>
            <th class="text-end">Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($customers as $row): ?>
    <tr class="bc-cust-row" data-id="<?= (int)$row['id'] ?>" style="cursor:pointer">
        <td class="text-center text-body-secondary">
            <i class="bi bi-chevron-right bc-cust-chevron" style="font-size:.75rem"></i>
        </td>
        <td>
            <span class="fw-medium"><?= bcCustH($row['customer_name']) ?></span>
            <?php if ($row['internal_identifier']): ?>
            <br><small class="text-body-secondary"><?= bcCustH($row['internal_identifier']) ?></small>
            <?php endif; ?>
        </td>
        <?php if (count($connections) > 1): ?>
        <td><small class="text-body-secondary"><?= bcCustH($row['connection_name']) ?></small></td>
        <?php endif; ?>
        <td>
            <?php if ($row['client_id']): ?>
                <a href="/licenses?search=<?= urlencode($row['client_name'] ?? '') ?>"
                   class="text-decoration-none" onclick="event.stopPropagation()">
                    <?= bcCustH($row['client_name']) ?>
                </a>
                <?php if (!$row['mapping_confirmed']): ?>
                <span class="badge bg-warning text-dark ms-1" title="Mapping non confirmé">?</span>
                <?php endif; ?>
            <?php else: ?>
                <span class="text-body-secondary small">— Non mappé</span>
            <?php endif; ?>
        </td>
        <td class="text-center">
            <?php if ($row['sub_count'] > 0): ?>
            <span class="badge bg-info text-dark"><?= (int)$row['sub_count'] ?></span>
            <?php else: ?>
            <span class="text-body-secondary">0</span>
            <?php endif; ?>
        </td>
        <td class="text-center">
            <?php if ($row['lic_count'] > 0): ?>
            <span class="badge bg-primary"><?= (int)$row['lic_count'] ?></span>
            <?php else: ?>
            <span class="text-body-secondary">0</span>
            <?php endif; ?>
        </td>
        <td class="text-center small">
            <?php if ($row['lic_total'] > 0): ?>
            <span title="Consommées / Total">
                <?= (int)$row['lic_consumed'] ?> / <?= (int)$row['lic_total'] ?>
            </span>
            <?php else: ?>
            <span class="text-body-secondary">—</span>
            <?php endif; ?>
        </td>
        <td class="text-end" onclick="event.stopPropagation()">
            <a href="/mapping?provider=becloud&search=<?= urlencode($row['customer_name']) ?>"
               class="btn btn-sm btn-outline-secondary py-0 px-1" title="Mapping">
                <i class="bi bi-link-45deg"></i>
            </a>
        </td>
    </tr>
    <!-- Ligne de détail (collapsed) -->
    <tr class="bc-cust-detail d-none" id="bc-detail-<?= (int)$row['id'] ?>">
        <td colspan="<?= count($connections) > 1 ? 8 : 7 ?>" class="p-0">
            <div class="bc-detail-inner p-3 bg-light border-top border-bottom" style="display:none">
                <div class="text-center text-body-secondary py-2">
                    <span class="spinner-border spinner-border-sm me-1"></span>Chargement…
                </div>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center mb-0">
        <?php for ($p = 1; $p <= $totalPages; $p++):
            $pParams = array_merge($qp, ['page' => $p]);
        ?>
        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
            <a class="page-link" href="/becloud/customers?<?= http_build_query($pParams) ?>"><?= $p ?></a>
        </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php endif; ?>

<script>
(function () {
    // Toggle détail inline avec chargement AJAX
    document.querySelectorAll('.bc-cust-row').forEach(function (row) {
        row.addEventListener('click', function () {
            const id        = this.dataset.id;
            const detailRow = document.getElementById('bc-detail-' + id);
            const inner     = detailRow.querySelector('.bc-detail-inner');
            const chevron   = this.querySelector('.bc-cust-chevron');
            const isOpen    = !detailRow.classList.contains('d-none');

            if (isOpen) {
                inner.style.display = 'none';
                detailRow.classList.add('d-none');
                chevron.classList.replace('bi-chevron-down', 'bi-chevron-right');
                return;
            }

            detailRow.classList.remove('d-none');
            chevron.classList.replace('bi-chevron-right', 'bi-chevron-down');

            // Déjà chargé ?
            if (inner.dataset.loaded === '1') {
                inner.style.display = '';
                return;
            }

            inner.style.display = '';

            fetch('/becloud/customer-detail?id=' + id, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                inner.dataset.loaded = '1';
                inner.innerHTML = renderDetail(data);
            })
            .catch(function () {
                inner.innerHTML = '<p class="text-danger small mb-0">Erreur lors du chargement.</p>';
            });
        });
    });

    function h(v) {
        return String(v ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function fmtDate(d) {
        if (!d) return '—';
        const parts = d.split('-');
        return parts.length === 3 ? parts[2]+'/'+parts[1]+'/'+parts[0] : h(d);
    }

    function renderDetail(data) {
        let html = '<div class="row g-3">';

        // ── Abonnements ──────────────────────────────────────────────
        html += '<div class="col-12 col-xl-7">';
        html += '<h6 class="mb-2"><i class="bi bi-receipt me-1 text-info"></i>Abonnements';
        html += ' <span class="badge bg-secondary">' + data.subscriptions.length + '</span></h6>';

        if (data.subscriptions.length === 0) {
            html += '<p class="text-body-secondary small mb-0">Aucun abonnement.</p>';
        } else {
            html += '<div class="table-responsive"><table class="table table-sm table-bordered mb-0 small">';
            html += '<thead class="table-secondary"><tr>'
                  + '<th>Offre</th><th>Type</th><th>Statut</th>'
                  + '<th class="text-center">Qté</th><th class="text-center">Assignées</th>'
                  + '<th>Début</th><th>Fin</th><th>Fréq.</th>'
                  + '</tr></thead><tbody>';
            data.subscriptions.forEach(function (s) {
                const statusClass = s.status === 'Active' ? 'success'
                                  : s.status === 'Suspended' ? 'warning' : 'secondary';
                html += '<tr>'
                      + '<td>' + h(s.offer_name || s.subscription_name) + '</td>'
                      + '<td><span class="text-body-secondary">' + h(s.offer_type) + '</span></td>'
                      + '<td><span class="badge bg-' + statusClass + '">' + h(s.status) + '</span>'
                      + (s.is_trial ? ' <span class="badge bg-warning text-dark">Trial</span>' : '')
                      + '</td>'
                      + '<td class="text-center">' + h(s.quantity) + '</td>'
                      + '<td class="text-center">' + h(s.assigned_licenses) + '</td>'
                      + '<td>' + fmtDate(s.start_date) + '</td>'
                      + '<td>' + fmtDate(s.end_date) + '</td>'
                      + '<td>' + h(s.billing_frequency) + '</td>'
                      + '</tr>';
            });
            html += '</tbody></table></div>';
        }
        html += '</div>';

        // ── Licences ─────────────────────────────────────────────────
        html += '<div class="col-12 col-xl-5">';
        html += '<h6 class="mb-2"><i class="bi bi-key me-1 text-primary"></i>Licences M365/Cloud';
        html += ' <span class="badge bg-secondary">' + data.licenses.length + '</span></h6>';

        if (data.licenses.length === 0) {
            html += '<p class="text-body-secondary small mb-0">Aucune licence (sync requise ou pas de providerInstanceId).</p>';
        } else {
            html += '<div class="table-responsive"><table class="table table-sm table-bordered mb-0 small">';
            html += '<thead class="table-secondary"><tr>'
                  + '<th>Nom</th>'
                  + '<th class="text-center">Total</th>'
                  + '<th class="text-center">Consommées</th>'
                  + '<th class="text-center">Disponibles</th>'
                  + '<th class="text-center">Suspendues</th>'
                  + '</tr></thead><tbody>';
            data.licenses.forEach(function (l) {
                const usePct = l.total_licenses > 0
                    ? Math.round(l.consumed_licenses / l.total_licenses * 100) : 0;
                const barClass = usePct >= 90 ? 'bg-danger' : usePct >= 70 ? 'bg-warning' : 'bg-success';
                html += '<tr>'
                      + '<td title="SKU: ' + h(l.sku_id) + '">' + h(l.name || l.sku_id) + '</td>'
                      + '<td class="text-center">' + h(l.total_licenses) + '</td>'
                      + '<td class="text-center">'
                      +   '<div>' + h(l.consumed_licenses) + '</div>'
                      +   '<div class="progress mt-1" style="height:4px;min-width:50px">'
                      +     '<div class="progress-bar ' + barClass + '" style="width:' + usePct + '%"></div>'
                      +   '</div>'
                      + '</td>'
                      + '<td class="text-center">' + h(l.available_licenses) + '</td>'
                      + '<td class="text-center">'
                      + (l.suspended_licenses > 0 ? '<span class="text-warning fw-bold">' + h(l.suspended_licenses) + '</span>' : '0')
                      + '</td>'
                      + '</tr>';
            });
            html += '</tbody></table></div>';
        }
        html += '</div>';

        html += '</div>';
        return html;
    }
})();
</script>
