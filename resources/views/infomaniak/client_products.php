<?php
/** @var array  $client   */
/** @var array  $products */
/** @var string $sortBy   */
/** @var string $sortDir  */

function ikCpSortLink(string $col, string $current, string $dir, string $label): string {
    $newDir = ($current === $col && $dir === 'ASC') ? 'DESC' : 'ASC';
    $icon   = $current === $col ? ($dir === 'ASC' ? '↑' : '↓') : '';
    $id     = (int)($_GET['id'] ?? 0);
    $params = ['sort' => $col, 'dir' => $newDir];
    return '<a href="/infomaniak/client/' . $id . '?' . http_build_query($params) . '" class="text-white text-decoration-none">'
         . $label . ($icon ? " $icon" : '') . '</a>';
}

$clientId   = (int)$client['id'];
$now        = time();
$in30Days   = $now + 30 * 86400;
?>

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h1 class="h3 mb-0">
            <i class="bi bi-server text-danger me-2"></i>
            Produits Infomaniak — <span class="fw-bold"><?= htmlspecialchars($client['name']) ?></span>
            <span class="badge bg-secondary ms-2"><?= count($products) ?></span>
        </h1>
        <small class="text-body-secondary font-monospace"><?= htmlspecialchars($client['client_number'] ?? '') ?></small>
    </div>
    <div class="d-flex gap-2">
        <a href="/licenses/<?= $clientId ?>/report" class="btn btn-sm btn-outline-secondary" target="_blank">
            <i class="bi bi-file-pdf me-1"></i>Rapport PDF
        </a>
        <a href="/mapping?provider=infomaniak" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-link-45deg me-1"></i>Mapping
        </a>
        <a href="/infomaniak/licenses" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Retour liste
        </a>
    </div>
</div>

<?php if (empty($products)): ?>
<div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>
    Aucun produit Infomaniak trouvé pour ce client. Vérifiez que le mapping est bien confirmé.
</div>
<?php else: ?>

<div class="table-responsive">
    <table class="table table-sm table-hover align-middle">
        <thead class="table-dark small">
            <tr>
                <th><?= ikCpSortLink('service', $sortBy, $sortDir, 'Service') ?></th>
                <th><?= ikCpSortLink('name', $sortBy, $sortDir, 'Produit (nom interne)') ?></th>
                <th><?= ikCpSortLink('customer', $sortBy, $sortDir, 'Nom client') ?></th>
                <th><?= ikCpSortLink('account', $sortBy, $sortDir, 'Compte Infomaniak') ?></th>
                <th>Description</th>
                <th class="text-center"><?= ikCpSortLink('expires', $sortBy, $sortDir, 'Expiration') ?></th>
                <th class="text-center">Statut</th>
                <th class="text-center">ID Produit</th>
                <th class="text-center">Connexion</th>
                <th class="text-center">Dernière sync</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($products as $p):
            $expiredAt      = $p['expired_at'] ? (int)$p['expired_at'] : null;
            $isExpired      = $expiredAt && $expiredAt < $now;
            $isExpiringSoon = $expiredAt && $expiredAt >= $now && $expiredAt <= $in30Days;
            $rowId          = 'raw-' . (int)$p['product_row_id'];
        ?>
        <tr>
            <td>
                <span class="badge bg-secondary"><?= htmlspecialchars($p['service_name'] ?? '—') ?></span>
                <?php if ($p['service_id']): ?>
                    <br><small class="text-body-secondary">ID svc: <?= (int)$p['service_id'] ?></small>
                <?php endif; ?>
            </td>
            <td class="small">
                <?= $p['internal_name'] ? htmlspecialchars($p['internal_name']) : '<em class="text-body-secondary">—</em>' ?>
            </td>
            <td class="small">
                <?= $p['customer_name'] ? htmlspecialchars($p['customer_name']) : '<em class="text-body-secondary">—</em>' ?>
            </td>
            <td class="small">
                <?= htmlspecialchars($p['account_name']) ?>
                <?php if ($p['account_type']): ?>
                    <br><span class="text-body-secondary"><?= htmlspecialchars($p['account_type']) ?></span>
                <?php endif; ?>
                <?php if ($p['mapping_confirmed'] == 0): ?>
                    <br><span class="badge bg-warning text-dark" style="font-size:.65rem">mapping non confirmé</span>
                <?php endif; ?>
            </td>
            <td class="small text-body-secondary">
                <?= $p['description'] ? htmlspecialchars(mb_strimwidth($p['description'], 0, 80, '…')) : '—' ?>
            </td>
            <td class="text-center small <?= $isExpired ? 'text-danger fw-bold' : ($isExpiringSoon ? 'text-warning fw-bold' : '') ?>">
                <?= $expiredAt ? date('d/m/Y', $expiredAt) : '—' ?>
            </td>
            <td class="text-center">
                <?php
                $badges = [];
                if ($isExpired)      $badges[] = '<span class="badge bg-danger">Expiré</span>';
                elseif ($isExpiringSoon) $badges[] = '<span class="badge bg-warning text-dark">Expire bientôt</span>';
                else                 $badges[] = '<span class="badge bg-success">Actif</span>';
                if ($p['is_trial'])  $badges[] = '<span class="badge bg-info text-dark">Essai</span>';
                if ($p['is_free'])   $badges[] = '<span class="badge bg-secondary">Gratuit</span>';
                echo implode(' ', $badges);
                ?>
            </td>
            <td class="text-center font-monospace small"><?= (int)$p['infomaniak_product_id'] ?></td>
            <td class="text-center small text-body-secondary"><?= htmlspecialchars($p['connection_name']) ?></td>
            <td class="text-center small text-body-secondary">
                <?= $p['last_sync_at'] ? date('d/m H:i', strtotime($p['last_sync_at'])) : '—' ?>
            </td>
            <td class="text-center">
                <?php if ($p['raw_data']): ?>
                <button class="btn btn-link btn-sm p-0 text-body-secondary" type="button"
                        data-bs-toggle="collapse" data-bs-target="#<?= $rowId ?>" title="Voir JSON brut">
                    <i class="bi bi-code-slash"></i>
                </button>
                <?php endif; ?>
            </td>
        </tr>
        <?php if ($p['raw_data']): ?>
        <tr class="collapse" id="<?= $rowId ?>">
            <td colspan="11" class="p-0">
                <div class="bg-dark text-light p-3 rounded-bottom" style="font-size:.8rem">
                    <pre class="mb-0" style="white-space:pre-wrap;word-break:break-all"><?= htmlspecialchars(
                        json_encode(json_decode($p['raw_data'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                    ) ?></pre>
                </div>
            </td>
        </tr>
        <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php
// Groupé par service pour un résumé rapide
$byService = [];
foreach ($products as $p) {
    $svc = $p['service_name'] ?? 'inconnu';
    $byService[$svc] = ($byService[$svc] ?? 0) + 1;
}
arsort($byService);
?>
<div class="mt-4">
    <h5 class="small text-body-secondary text-uppercase fw-semibold mb-2">Résumé par service</h5>
    <div class="d-flex flex-wrap gap-2">
        <?php foreach ($byService as $svc => $cnt): ?>
        <span class="badge bg-secondary" style="font-size:.85rem">
            <?= htmlspecialchars($svc) ?> <span class="badge bg-dark ms-1"><?= $cnt ?></span>
        </span>
        <?php endforeach; ?>
    </div>
</div>

<?php endif; ?>
