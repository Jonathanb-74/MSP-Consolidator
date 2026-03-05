<?php
/** @var array $clients */
/** @var array $esetDetails */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var string $search */
/** @var int $tagId */
/** @var array $allTags */
/** @var string $sortBy */
/** @var string $sortDir */
/** @var string $providerFilter */

function licParseTags(?string $raw): array {
    if (!$raw) return [];
    return array_map(function($part) {
        [$id, $name, $color] = array_pad(explode(':', $part, 3), 3, '');
        return ['id' => (int)$id, 'name' => $name, 'color' => $color];
    }, explode(';;', $raw));
}

function licSortLink(string $col, string $current, string $dir, string $label, array $queryParams): string {
    $newDir = ($current === $col && $dir === 'ASC') ? 'DESC' : 'ASC';
    $icon   = $current === $col ? ($dir === 'ASC' ? ' ↑' : ' ↓') : '';
    $params = array_merge($queryParams, ['sort' => $col, 'dir' => $newDir]);
    return '<a href="/licenses?' . http_build_query($params) . '" class="text-white text-decoration-none">'
         . htmlspecialchars($label) . $icon . '</a>';
}
?>

<div class="page-sticky-top">
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h1 class="h3 mb-0">
            Récap Licences
            <span class="badge bg-secondary ms-2"><?= number_format($total) ?></span>
        </h1>
    </div>
</div>

<!-- Filtres -->
<form method="GET" action="/licenses" class="row g-2 mb-2">
    <div class="col-md-4">
        <div class="input-group input-group-sm">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" name="search" class="form-control"
                   placeholder="Rechercher (nom, numéro client)…"
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
    <div class="col-md-3">
        <select name="provider" class="form-select form-select-sm">
            <option value="" <?= $providerFilter === '' ? 'selected' : '' ?>>Tous les clients</option>
            <option value="eset"    <?= $providerFilter === 'eset'    ? 'selected' : '' ?>>Avec licences ESET</option>
            <option value="becloud" <?= $providerFilter === 'becloud' ? 'selected' : '' ?>>Avec abonnements Be-Cloud</option>
        </select>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-outline-secondary">Filtrer</button>
        <a href="/licenses" class="btn btn-sm btn-outline-secondary">Réinitialiser</a>
    </div>
    <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
    <input type="hidden" name="dir"  value="<?= htmlspecialchars($sortDir) ?>">
</form>
</div>

<!-- Tableau -->
<div class="table-responsive">
    <?php $qp = ['search' => $search, 'tag' => $tagId ?: '', 'provider' => $providerFilter, 'perPage' => $perPage]; ?>
    <table class="table table-hover align-middle table-sm" id="licenseRecapTable">
        <thead class="table-dark">
            <tr>
                <th style="width:110px"><?= licSortLink('client_number', $sortBy, $sortDir, 'N° Client', $qp) ?></th>
                <th><?= licSortLink('name', $sortBy, $sortDir, 'Nom', $qp) ?></th>
                <th>Tags</th>
                <th>
                    <i class="bi bi-shield-lock me-1 text-success"></i>
                    <?= licSortLink('eset_count', $sortBy, $sortDir, 'ESET', $qp) ?>
                </th>
                <th>
                    <i class="bi bi-cloud-check me-1 text-info"></i>
                    <?= licSortLink('bc_count', $sortBy, $sortDir, 'Be-Cloud', $qp) ?>
                </th>
                <th class="text-body-secondary">
                    <i class="bi bi-hdd-network me-1 opacity-50"></i>
                    <span class="opacity-50">NinjaOne</span>
                    <span class="badge bg-secondary ms-1" style="font-size:.65rem">bientôt</span>
                </th>
                <th class="text-body-secondary">
                    <i class="bi bi-archive me-1 opacity-50"></i>
                    <span class="opacity-50">Veeam</span>
                    <span class="badge bg-secondary ms-1" style="font-size:.65rem">bientôt</span>
                </th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($clients)): ?>
            <tr>
                <td colspan="8" class="text-center text-body-secondary py-5">
                    <i class="bi bi-grid-3x3 fs-1 d-block mb-2 opacity-25"></i>
                    Aucun client trouvé.
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($clients as $client):
                $clientTags  = licParseTags($client['tags_raw'] ?? null);
                $esetMapped  = (int)($client['eset_mapped'] ?? 0);
                $esetCount   = (int)($client['eset_lic_count'] ?? 0);
                $esetTotal   = (int)($client['eset_seats_total'] ?? 0);
                $esetUsed    = (int)($client['eset_seats_used'] ?? 0);
                $esetOver    = $esetTotal > 0 && $esetUsed > $esetTotal;
                $esetPct     = $esetTotal > 0 ? min(100, (int)round($esetUsed / $esetTotal * 100)) : 0;
                $bcCount     = (int)($client['bc_sub_count'] ?? 0);
                $bcTotal     = (int)($client['bc_seats_total'] ?? 0);
                $bcUsed      = (int)($client['bc_seats_used'] ?? 0);
                $bcOver      = $bcTotal > 0 && $bcUsed > $bcTotal;
                $detailId    = 'detail-' . $client['id'];
                $clientDetails = $esetDetails[$client['id']] ?? [];
            ?>
            <!-- Ligne résumé -->
            <tr class="align-middle"
                data-bs-toggle="collapse"
                data-bs-target="#<?= $detailId ?>"
                style="cursor:pointer"
                aria-expanded="false">
                <td><code class="small"><?= htmlspecialchars($client['client_number']) ?></code></td>
                <td class="fw-medium">
                    <i class="bi bi-chevron-right expand-icon me-1 small text-body-secondary" style="transition:transform .2s"></i>
                    <?= htmlspecialchars($client['name']) ?>
                </td>
                <td>
                    <div class="d-flex flex-wrap gap-1">
                        <?php foreach ($clientTags as $tag): ?>
                        <span class="badge rounded-pill" style="background-color:<?= htmlspecialchars($tag['color']) ?>">
                            <?= htmlspecialchars($tag['name']) ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </td>
                <td>
                    <?php if ($esetMapped && $esetCount > 0): ?>
                        <span class="badge bg-secondary me-1"><?= $esetCount ?> lic</span>
                        <?php if ($esetOver): ?>
                            <span class="small fw-semibold text-danger">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i><?= $esetUsed ?>/<?= $esetTotal ?>
                            </span>
                        <?php else: ?>
                            <span class="small text-body-secondary"><?= $esetUsed ?>/<?= $esetTotal ?></span>
                        <?php endif; ?>
                        <div class="progress mt-1" style="height:4px;max-width:80px">
                            <div class="progress-bar <?= $esetOver ? 'bg-danger' : ($esetUsed === $esetTotal ? 'bg-success' : 'bg-primary') ?>"
                                 style="width:100%"></div>
                        </div>
                    <?php elseif ($esetMapped): ?>
                        <span class="text-body-secondary small">—</span>
                    <?php else: ?>
                        <span class="text-body-secondary" style="font-size:.78rem;opacity:.6">· non mappé</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($bcCount > 0): ?>
                        <span class="badge bg-secondary me-1"><?= $bcCount ?> sub</span>
                        <?php if ($bcOver): ?>
                            <span class="small fw-semibold text-danger">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i><?= $bcUsed ?>/<?= $bcTotal ?>
                            </span>
                        <?php else: ?>
                            <span class="small text-body-secondary"><?= $bcUsed ?>/<?= $bcTotal ?></span>
                        <?php endif; ?>
                        <div class="progress mt-1" style="height:4px;max-width:80px">
                            <div class="progress-bar text-info <?= $bcOver ? 'bg-danger' : ($bcUsed === $bcTotal ? 'bg-success' : 'bg-info') ?>"
                                 style="width:100%"></div>
                        </div>
                    <?php else: ?>
                        <span class="text-body-secondary" style="font-size:.78rem;opacity:.6">· non mappé</span>
                    <?php endif; ?>
                </td>
                <td class="text-body-secondary small">—</td>
                <td class="text-body-secondary small">—</td>
            </tr>

            <!-- Ligne détail (collapse) -->
            <tr class="collapse" id="<?= $detailId ?>">
                <td colspan="8" class="p-0">
                    <div class="px-4 py-3 border-start border-4 border-primary-subtle bg-body-secondary">
                        <div class="row g-3">

                            <!-- Bloc ESET -->
                            <div class="col-md-5">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-shield-lock text-success me-2"></i>
                                    <strong class="small">ESET</strong>
                                </div>
                                <?php if (!$esetMapped): ?>
                                    <p class="text-body-secondary small mb-0">
                                        <i class="bi bi-exclamation-circle me-1"></i>
                                        Aucun mapping confirmé.
                                        <a href="/mapping" class="small">Configurer le mapping</a>
                                    </p>
                                <?php elseif (empty($clientDetails)): ?>
                                    <p class="text-body-secondary small mb-0">Aucune licence synchronisée.</p>
                                <?php else: ?>
                                    <table class="table table-sm mb-0" style="font-size:.8rem">
                                        <thead>
                                            <tr>
                                                <th>Produit</th>
                                                <th class="text-center">Lic</th>
                                                <th>Machines</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($clientDetails as $detail):
                                            $dTotal = (int)$detail['seats_total'];
                                            $dUsed  = (int)$detail['seats_used'];
                                            $dOver  = $dTotal > 0 && $dUsed > $dTotal;
                                            $dPct   = $dTotal > 0 ? min(100, (int)round($dUsed / $dTotal * 100)) : 0;
                                        ?>
                                            <tr <?= $dOver ? 'class="table-danger"' : '' ?>>
                                                <td><?= htmlspecialchars($detail['product_name']) ?></td>
                                                <td class="text-center"><?= (int)$detail['lic_count'] ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="progress flex-grow-1" style="height:5px;min-width:50px">
                                                            <div class="progress-bar <?= $dOver ? 'bg-danger' : ($dUsed === $dTotal ? 'bg-success' : 'bg-primary') ?>"
                                                                 style="width:100%"></div>
                                                        </div>
                                                        <?php if ($dOver): ?>
                                                            <span class="fw-semibold text-danger" style="min-width:70px">
                                                                <i class="bi bi-exclamation-triangle-fill me-1"></i><?= $dUsed ?>/<?= $dTotal ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-body-secondary" style="min-width:55px"><?= $dUsed ?>/<?= $dTotal ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>

                            <!-- Bloc Be-Cloud -->
                            <div class="col-md-4">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-cloud-check text-info me-2"></i>
                                    <strong class="small">Be-Cloud</strong>
                                </div>
                                <?php if ($bcCount > 0): ?>
                                    <div class="small text-body-secondary">
                                        <span class="badge bg-secondary me-1"><?= $bcCount ?> abonnement(s)</span>
                                        <?php if ($bcOver): ?>
                                            <span class="fw-semibold text-danger">
                                                <i class="bi bi-exclamation-triangle-fill me-1"></i><?= $bcUsed ?>/<?= $bcTotal ?>
                                            </span>
                                        <?php else: ?>
                                            <?= $bcUsed ?>/<?= $bcTotal ?> assignés
                                        <?php endif; ?>
                                        <div class="progress mt-1" style="height:4px;max-width:80px">
                                            <div class="progress-bar <?= $bcOver ? 'bg-danger' : 'bg-info' ?>"
                                                 style="width:100%"></div>
                                        </div>
                                    </div>
                                    <a href="/becloud/licenses?search=<?= urlencode($client['name']) ?>" class="small">
                                        Voir les abonnements
                                    </a>
                                <?php else: ?>
                                    <p class="text-body-secondary small mb-0">
                                        <i class="bi bi-exclamation-circle me-1"></i>
                                        Aucun mapping confirmé.
                                        <a href="/mapping?provider=becloud" class="small">Configurer</a>
                                    </p>
                                <?php endif; ?>
                            </div>

                            <!-- Blocs futurs fournisseurs -->
                            <div class="col-md-3">
                                <div class="d-flex flex-wrap gap-3 pt-1">
                                    <?php foreach ([
                                        ['bi-hdd-network', 'NinjaOne'],
                                        ['bi-archive',     'Veeam'],
                                    ] as [$icon, $name]): ?>
                                    <div class="text-body-secondary small d-flex align-items-center gap-1 opacity-50">
                                        <i class="bi <?= $icon ?>"></i>
                                        <?= $name ?>
                                        <span class="badge bg-secondary" style="font-size:.6rem">bientôt</span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                        </div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$totalPages = (int)ceil($total / $perPage);
$queryBase  = http_build_query(['search' => $search, 'tag' => $tagId ?: '', 'provider' => $providerFilter, 'sort' => $sortBy, 'dir' => $sortDir, 'perPage' => $perPage]);
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
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="/licenses?<?= $queryBase ?>&page=<?= $i ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
        </ul></nav>
        <?php endif; ?>
    </div>
</div>

<script>
// Rotation de la flèche au clic
document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(function(row) {
    row.addEventListener('click', function() {
        const icon = this.querySelector('.expand-icon');
        if (!icon) return;
        const targetId = this.getAttribute('data-bs-target');
        const target   = document.querySelector(targetId);
        if (!target) return;
        const isOpen = target.classList.contains('show');
        icon.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(90deg)';
    });
});
</script>
