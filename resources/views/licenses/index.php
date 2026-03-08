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
    // http_build_query encode les tableaux (tags[], providers[]) correctement
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

<?php
$activeFilters = count($tagIds) + count($providerFilters);
$providerLabels = ['eset' => 'ESET', 'becloud' => 'Be-Cloud', 'ninjaone' => 'NinjaOne'];
?>
<!-- Filtres -->
<form method="GET" action="/licenses" id="licenseFilterForm" class="d-flex flex-wrap gap-2 align-items-center mb-2">

    <!-- Recherche -->
    <div class="input-group input-group-sm" style="width:260px">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="search" class="form-control"
               placeholder="Nom, numéro client…"
               value="<?= htmlspecialchars($search) ?>">
    </div>

    <!-- Tags multi-select -->
    <?php if (!empty($allTags)): ?>
    <div class="dropdown">
        <button class="btn btn-sm btn-outline-secondary dropdown-toggle d-flex align-items-center gap-1"
                type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
            <i class="bi bi-tags"></i>
            Tags
            <?php if (!empty($tagIds)): ?>
                <span class="badge rounded-pill bg-primary" style="font-size:.7rem"><?= count($tagIds) ?></span>
            <?php endif; ?>
        </button>
        <div class="dropdown-menu shadow-sm p-1" style="min-width:220px">
            <!-- Toggle ET / OU -->
            <?php if (count($allTags) > 1): ?>
            <div class="d-flex align-items-center gap-1 px-2 py-1 mb-1 border-bottom">
                <small class="text-body-secondary me-1">Mode :</small>
                <div class="btn-group btn-group-sm" role="group">
                    <input type="radio" class="btn-check filter-auto" name="tag_logic" id="tag_logic_or"
                           value="or" <?= $tagLogic === 'or' ? 'checked' : '' ?> autocomplete="off">
                    <label class="btn btn-outline-secondary py-0 px-2" for="tag_logic_or" style="font-size:.75rem">OU</label>
                    <input type="radio" class="btn-check filter-auto" name="tag_logic" id="tag_logic_and"
                           value="and" <?= $tagLogic === 'and' ? 'checked' : '' ?> autocomplete="off">
                    <label class="btn btn-outline-secondary py-0 px-2" for="tag_logic_and" style="font-size:.75rem">ET</label>
                </div>
            </div>
            <?php endif; ?>
            <?php foreach ($allTags as $t): ?>
            <label class="dropdown-item d-flex align-items-center gap-2 py-1 rounded" style="cursor:pointer">
                <input type="checkbox" class="form-check-input flex-shrink-0 filter-auto"
                       name="tags[]" value="<?= (int)$t['id'] ?>"
                       <?= in_array((int)$t['id'], $tagIds) ? 'checked' : '' ?>>
                <span class="badge rounded-pill" style="background-color:<?= htmlspecialchars($t['color']) ?>">
                    <?= htmlspecialchars($t['name']) ?>
                </span>
            </label>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Fournisseurs multi-select -->
    <div class="dropdown">
        <button class="btn btn-sm btn-outline-secondary dropdown-toggle d-flex align-items-center gap-1"
                type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
            <i class="bi bi-cloud"></i>
            Fournisseurs
            <?php if (!empty($providerFilters)): ?>
                <span class="badge rounded-pill bg-primary" style="font-size:.7rem"><?= count($providerFilters) ?></span>
            <?php endif; ?>
        </button>
        <div class="dropdown-menu shadow-sm p-1" style="min-width:210px">
            <!-- Toggle ET / OU -->
            <div class="d-flex align-items-center gap-1 px-2 py-1 mb-1 border-bottom">
                <small class="text-body-secondary me-1">Mode :</small>
                <div class="btn-group btn-group-sm" role="group">
                    <input type="radio" class="btn-check filter-auto" name="provider_logic" id="prov_logic_or"
                           value="or" <?= $providerLogic === 'or' ? 'checked' : '' ?> autocomplete="off">
                    <label class="btn btn-outline-secondary py-0 px-2" for="prov_logic_or" style="font-size:.75rem">OU</label>
                    <input type="radio" class="btn-check filter-auto" name="provider_logic" id="prov_logic_and"
                           value="and" <?= $providerLogic === 'and' ? 'checked' : '' ?> autocomplete="off">
                    <label class="btn btn-outline-secondary py-0 px-2" for="prov_logic_and" style="font-size:.75rem">ET</label>
                </div>
            </div>
            <?php foreach ($providerLabels as $val => $label): ?>
            <label class="dropdown-item d-flex align-items-center gap-2 py-1 rounded" style="cursor:pointer">
                <input type="checkbox" class="form-check-input flex-shrink-0 filter-auto"
                       name="providers[]" value="<?= $val ?>"
                       <?= in_array($val, $providerFilters) ? 'checked' : '' ?>>
                <?= $label ?>
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Toggle : afficher sans licences -->
    <div class="form-check form-switch mb-0 ms-1">
        <input class="form-check-input filter-auto" type="checkbox"
               id="showAllToggle" name="show_all" value="1"
               <?= $showAll ? 'checked' : '' ?>>
        <label class="form-check-label small text-body-secondary" for="showAllToggle">
            Afficher sans licences
        </label>
    </div>

    <!-- Réinitialiser -->
    <?php if ($activeFilters > 0 || $search !== '' || $showAll): ?>
    <a href="/licenses" class="btn btn-sm btn-outline-danger">
        <i class="bi bi-x-lg me-1"></i>Réinitialiser
    </a>
    <?php endif; ?>

    <input type="hidden" name="sort"    value="<?= htmlspecialchars($sortBy) ?>">
    <input type="hidden" name="dir"     value="<?= htmlspecialchars($sortDir) ?>">
    <input type="hidden" name="perPage" value="<?= $perPage ?>">
</form>

<?php if (!empty($tagIds) || !empty($providerFilters)): ?>
<div class="d-flex flex-wrap gap-1 mb-2">
    <?php foreach ($tagIds as $tid):
        $tData = array_values(array_filter($allTags, fn($t) => (int)$t['id'] === $tid))[0] ?? null;
        if (!$tData) continue;
    ?>
    <span class="badge rounded-pill d-flex align-items-center gap-1"
          style="background-color:<?= htmlspecialchars($tData['color']) ?>">
        <?= htmlspecialchars($tData['name']) ?>
    </span>
    <?php endforeach; ?>
    <?php foreach ($providerFilters as $pf): ?>
    <span class="badge rounded-pill bg-secondary"><?= htmlspecialchars($providerLabels[$pf] ?? $pf) ?></span>
    <?php endforeach; ?>
</div>
<?php endif; ?>
</div>

<!-- Tableau -->
<div class="table-responsive">
    <?php $qp = ['search' => $search, 'tags' => $tagIds, 'tag_logic' => $tagLogic, 'providers' => $providerFilters, 'provider_logic' => $providerLogic, 'show_all' => $showAll ? '1' : '', 'perPage' => $perPage]; ?>
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
                <th>
                    <i class="bi bi-hdd-network me-1 text-warning"></i>
                    <?= licSortLink('ninja_rmm', $sortBy, $sortDir, 'NinjaOne', $qp) ?>
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
                <td colspan="9" class="text-center text-body-secondary py-5">
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
                $ninjaRmm    = (int)($client['ninja_rmm']   ?? 0);
                $ninjaNms    = (int)($client['ninja_nms']   ?? 0);
                $ninjaMdm    = (int)($client['ninja_mdm']   ?? 0);
                $ninjaVmm    = (int)($client['ninja_vmm']   ?? 0);
                $ninjaCloud  = (int)($client['ninja_cloud'] ?? 0);
                $ninjaTot    = $ninjaRmm + $ninjaNms + $ninjaMdm;
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
                    <a href="/licenses/<?= $client['id'] ?>/report"
                       class="btn btn-xs btn-outline-danger py-0 px-1 ms-1"
                       title="Exporter le rapport PDF"
                       onclick="event.stopPropagation()"
                       style="font-size:.7rem">
                        <i class="bi bi-file-earmark-pdf"></i>
                    </a>
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
                <td>
                    <?php if ($ninjaTot > 0): ?>
                        <div class="d-flex flex-wrap gap-1 align-items-center">
                            <?php if ($ninjaRmm > 0): ?>
                            <span class="badge bg-success" title="RMM"><?= $ninjaRmm ?> RMM</span>
                            <?php endif; ?>
                            <?php if ($ninjaNms > 0): ?>
                            <span class="badge bg-info" title="NMS"><?= $ninjaNms ?> NMS</span>
                            <?php endif; ?>
                            <?php if ($ninjaMdm > 0): ?>
                            <span class="badge bg-primary" title="MDM"><?= $ninjaMdm ?> MDM</span>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($ninjaVmm > 0 || $ninjaCloud > 0): ?>
                        <span class="badge bg-secondary" title="VMM/Cloud uniquement"><?= $ninjaVmm + $ninjaCloud ?></span>
                    <?php else: ?>
                        <span class="text-body-secondary" style="font-size:.78rem;opacity:.6">· non mappé</span>
                    <?php endif; ?>
                </td>
                <td class="text-body-secondary small">—</td>
            </tr>

            <!-- Ligne détail (collapse) -->
            <tr class="collapse" id="<?= $detailId ?>">
                <td colspan="9" class="p-0">
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
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($clientDetails as $detail):
                                            $dTotal   = (int)$detail['seats_total'];
                                            $dUsed    = (int)$detail['seats_used'];
                                            $dOver    = $dTotal > 0 && $dUsed > $dTotal;
                                            $dKeys    = !empty($detail['license_keys'])  ? explode(',', $detail['license_keys'])  : [];
                                            $dQtys    = !empty($detail['license_qtys'])  ? explode(',', $detail['license_qtys'])  : [];
                                            $dUseds   = !empty($detail['license_useds']) ? explode(',', $detail['license_useds']) : [];
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
                                                <td class="text-end">
                                                    <?php foreach ($dKeys as $ki => $dKey):
                                                        $kQty  = (int)($dQtys[$ki]  ?? 0);
                                                        $kUsed = (int)($dUseds[$ki] ?? 0);
                                                        $kFree = $kQty - $kUsed;
                                                        $kOver = $kUsed > $kQty;
                                                        $kFull = !$kOver && $kQty > 0 && $kUsed === $kQty;
                                                    ?>
                                                    <button class="btn btn-xs btn-outline-secondary py-0 px-1 btn-show-history"
                                                            data-key="<?= htmlspecialchars($dKey) ?>"
                                                            data-product="<?= htmlspecialchars($detail['product_name']) ?>"
                                                            data-client="<?= htmlspecialchars($client['name']) ?>"
                                                            data-company=""
                                                            data-qty="<?= $kQty ?>"
                                                            data-used="<?= $kUsed ?>"
                                                            data-free="<?= $kFree ?>"
                                                            data-over="<?= $kOver ? '1' : '0' ?>"
                                                            data-full="<?= $kFull ? '1' : '0' ?>"
                                                            title="Historique <?= htmlspecialchars($dKey) ?>"
                                                            style="font-size:.7rem">
                                                        <i class="bi bi-clock-history"></i>
                                                        <?php if (count($dKeys) > 1): ?>
                                                            <span class="font-monospace" style="font-size:.65rem"><?= htmlspecialchars($dKey) ?></span>
                                                        <?php endif; ?>
                                                    </button>
                                                    <?php endforeach; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>

                            <!-- Bloc Be-Cloud -->
                            <div class="col-md-3">
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

                            <!-- Bloc NinjaOne -->
                            <div class="col-md-3">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-hdd-network text-warning me-2"></i>
                                    <strong class="small">NinjaOne</strong>
                                </div>
                                <?php if ($ninjaTot > 0 || $ninjaVmm > 0 || $ninjaCloud > 0): ?>
                                    <div class="d-flex flex-wrap gap-1 mb-1">
                                        <?php if ($ninjaRmm > 0): ?>
                                        <span class="badge bg-success"><?= $ninjaRmm ?> RMM</span>
                                        <?php endif; ?>
                                        <?php if ($ninjaNms > 0): ?>
                                        <span class="badge bg-info"><?= $ninjaNms ?> NMS</span>
                                        <?php endif; ?>
                                        <?php if ($ninjaMdm > 0): ?>
                                        <span class="badge bg-primary"><?= $ninjaMdm ?> MDM</span>
                                        <?php endif; ?>
                                        <?php if ($ninjaVmm > 0): ?>
                                        <span class="badge bg-secondary" title="no license"><?= $ninjaVmm ?> VMM</span>
                                        <?php endif; ?>
                                        <?php if ($ninjaCloud > 0): ?>
                                        <span class="badge bg-secondary" title="no license"><?= $ninjaCloud ?> Cloud</span>
                                        <?php endif; ?>
                                    </div>
                                    <a href="/ninjaone/devices?client_id=<?= $client['id'] ?>" class="small">
                                        <i class="bi bi-hdd-network me-1"></i>Voir les équipements
                                    </a>
                                <?php else: ?>
                                    <p class="text-body-secondary small mb-0">
                                        <i class="bi bi-exclamation-circle me-1"></i>
                                        Aucun mapping confirmé.
                                        <a href="/mapping?provider=ninjaone" class="small">Configurer</a>
                                    </p>
                                <?php endif; ?>
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
$queryBase  = http_build_query(['search' => $search, 'tags' => $tagIds, 'tag_logic' => $tagLogic, 'providers' => $providerFilters, 'provider_logic' => $providerLogic, 'show_all' => $showAll ? '1' : '', 'sort' => $sortBy, 'dir' => $sortDir, 'perPage' => $perPage]);
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

<!-- Modal Historique licence ESET -->
<div class="modal fade" id="licenseHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div class="flex-grow-1 me-3">
                    <h5 class="modal-title mb-1">
                        <i class="bi bi-clock-history me-2 text-success"></i>
                        Historique — <span id="histModalKey" class="font-monospace small"></span>
                    </h5>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <small class="fw-semibold text-body-secondary" id="histModalClient"></small>
                        <span id="histModalStats"></span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" id="histModalBody">
                <div class="text-center py-4">
                    <div class="spinner-border spinner-border-sm text-secondary"></div>
                </div>
            </div>
            <div class="modal-footer">
                <small class="text-body-secondary me-auto" id="histModalCount"></small>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-submit sur changement de checkbox/switch dans les filtres
document.querySelectorAll('.filter-auto').forEach(function(el) {
    el.addEventListener('change', function() {
        document.getElementById('licenseFilterForm').submit();
    });
});

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

// ── Historique licence ──────────────────────────────────────────────────────
(function () {
    const TYPE_LABELS = {
        '1':  { label: 'Annulation',              color: 'danger'   },
        '2':  { label: 'Conversion (trial→full)', color: 'success'  },
        '3':  { label: 'Extension d\'essai',      color: 'info'     },
        '4':  { label: 'Nouvelle commande',        color: 'success'  },
        '5':  { label: 'Mise à jour quantité',     color: 'primary'  },
        '6':  { label: 'Suspension',               color: 'warning'  },
        '7':  { label: 'Réactivation',             color: 'success'  },
        '8':  { label: 'Changement de clé',        color: 'secondary'},
        '9':  { label: 'Upgrade',                  color: 'success'  },
        '10': { label: 'Downgrade',                color: 'warning'  },
    };

    let PRODUCT_NAMES = {};

    function formatDate(raw) {
        if (!raw) return '—';
        const d = new Date(raw);
        return isNaN(d) ? raw : d.toLocaleDateString('fr-FR', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' });
    }

    function buildDetail(entry) {
        const parts = [];
        if (entry.PreviousUnits && entry.RequestedUnits) parts.push(`${entry.PreviousUnits} → ${entry.RequestedUnits} unités`);
        else if (entry.RequestedUnits)                   parts.push(`${entry.RequestedUnits} unité(s)`);
        if (entry.PreviousProductCode && entry.RequestedProductCode) {
            const prev = PRODUCT_NAMES[entry.PreviousProductCode] ?? `#${entry.PreviousProductCode}`;
            const next = PRODUCT_NAMES[entry.RequestedProductCode] ?? `#${entry.RequestedProductCode}`;
            parts.push(`Produit : ${prev} → ${next}`);
        }
        if (entry.PreviousLicenseKey && entry.RequestedLicenseKey) parts.push(`Clé : ${entry.PreviousLicenseKey} → ${entry.RequestedLicenseKey}`);
        if (entry.TrialExtensionCount) parts.push(`Extension n°${entry.TrialExtensionCount}`);
        if (entry.LicenseTypeId === '1') parts.push('Licence complète');
        return parts.join(' &nbsp;·&nbsp; ');
    }

    function renderHistory(data) {
        if (data.products && typeof data.products === 'object') {
            PRODUCT_NAMES = Object.assign({}, data.products);
        }
        const history = data.history ?? [];
        const count   = data.total_count ?? history.length;
        document.getElementById('histModalCount').textContent = count + ' événement' + (count > 1 ? 's' : '');

        if (!history.length) {
            document.getElementById('histModalBody').innerHTML =
                '<p class="text-center text-body-secondary py-4">Aucun historique disponible.</p>';
            return;
        }

        const rows = history.map(entry => {
            const t      = TYPE_LABELS[entry.Type] ?? { label: 'Type ' + entry.Type, color: 'secondary' };
            const detail = buildDetail(entry);
            return `<tr>
                <td class="small text-body-secondary text-nowrap">${formatDate(entry.Date)}</td>
                <td><span class="badge bg-${t.color}">${t.label}</span></td>
                <td class="small">${detail ? `<span class="text-body-secondary">${detail}</span>` : ''}</td>
                <td class="small text-body-secondary">${entry.User ?? '—'}</td>
            </tr>`;
        }).join('');

        document.getElementById('histModalBody').innerHTML = `
            <table class="table table-sm table-hover small mb-0">
                <thead class="table-dark">
                    <tr><th>Date</th><th>Opération</th><th>Détail</th><th>Utilisateur</th></tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>`;
    }

    const modalEl = document.getElementById('licenseHistoryModal');

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-show-history');
        if (!btn) return;

        const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        const key     = btn.dataset.key;
        const product = btn.dataset.product;
        const client  = btn.dataset.client;
        const qty     = parseInt(btn.dataset.qty,  10);
        const used    = parseInt(btn.dataset.used, 10);
        const free    = parseInt(btn.dataset.free, 10);
        const over    = btn.dataset.over === '1';
        const full    = btn.dataset.full === '1';

        document.getElementById('histModalKey').textContent    = key + (product ? ' — ' + product : '');
        document.getElementById('histModalClient').textContent = client || '';
        document.getElementById('histModalCount').textContent  = '';

        const qtyColor  = over ? 'danger' : (full ? 'success' : 'primary');
        const freeColor = over ? 'danger' : (full ? 'success' : 'secondary');
        document.getElementById('histModalStats').innerHTML = `
            <span class="badge bg-secondary">${qty} commandés</span>
            <span class="badge bg-${qtyColor}">${used} utilisés</span>
            <span class="badge bg-${freeColor}">${free} libres</span>`;

        document.getElementById('histModalBody').innerHTML =
            '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-secondary"></div></div>';

        bsModal.show();

        fetch('/eset/debug-history?license_key=' + encodeURIComponent(key))
            .then(r => r.json())
            .then(renderHistory)
            .catch(err => {
                document.getElementById('histModalBody').innerHTML =
                    `<div class="alert alert-danger m-3">Erreur : ${err.message}</div>`;
            });
    });
})();
</script>
