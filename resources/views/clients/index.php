<?php
/** @var array $clients */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var string $search */
/** @var string $structure */
/** @var array $structures */
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0">Clients <span class="badge bg-secondary ms-2"><?= number_format($total) ?></span></h1>
    <a href="/clients/import" class="btn btn-primary">
        <i class="bi bi-file-earmark-excel me-1"></i>Importer Excel
    </a>
</div>

<!-- Filtres -->
<form method="GET" action="/clients" class="row g-2 mb-4">
    <div class="col-md-6">
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" name="search" class="form-control"
                   placeholder="Rechercher (nom, numéro client, email)…"
                   value="<?= htmlspecialchars($search) ?>">
        </div>
    </div>
    <div class="col-md-3">
        <select name="structure" class="form-select">
            <option value="">Toutes les structures</option>
            <?php foreach ($structures as $s): ?>
                <option value="<?= htmlspecialchars($s) ?>" <?= $structure === $s ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-outline-secondary">Filtrer</button>
        <a href="/clients" class="btn btn-outline-secondary">Réinitialiser</a>
    </div>
</form>

<!-- Tableau -->
<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead class="table-dark">
            <tr>
                <th>N° Client</th>
                <th>Structure</th>
                <th>Nom</th>
                <th>Email</th>
                <th>Téléphone</th>
                <th class="text-center">Fournisseurs</th>
                <th class="text-center">Statut</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($clients)): ?>
            <tr>
                <td colspan="7" class="text-center text-body-secondary py-5">
                    <i class="bi bi-people fs-1 d-block mb-2 opacity-25"></i>
                    Aucun client trouvé.
                    <a href="/clients/import">Importer un fichier Excel</a>
                </td>
            </tr>
            <?php else: ?>
            <?php
            $structureColors = ['FCI' => 'primary', 'LTI' => 'success', 'LNI' => 'info', 'MACSHOP' => 'warning'];
            foreach ($clients as $client):
                $color = $structureColors[$client['structure_code']] ?? 'secondary';
            ?>
            <tr>
                <td><code><?= htmlspecialchars($client['client_number']) ?></code></td>
                <td><span class="badge bg-<?= $color ?>"><?= htmlspecialchars($client['structure_code']) ?></span></td>
                <td><?= htmlspecialchars($client['name']) ?></td>
                <td class="small text-body-secondary"><?= htmlspecialchars($client['email'] ?? '—') ?></td>
                <td class="small text-body-secondary"><?= htmlspecialchars($client['phone'] ?? '—') ?></td>
                <td class="text-center">
                    <?php if ($client['provider_count'] > 0): ?>
                        <span class="badge bg-success"><?= $client['provider_count'] ?></span>
                    <?php else: ?>
                        <span class="badge bg-light text-dark">0</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <?php if ($client['is_active']): ?>
                        <span class="badge bg-success">Actif</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Inactif</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if ($total > $perPage): ?>
<?php
$totalPages = (int)ceil($total / $perPage);
$queryBase  = http_build_query(['search' => $search, 'structure' => $structure]);
?>
<nav>
    <ul class="pagination pagination-sm justify-content-end">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
            <a class="page-link" href="/clients?<?= $queryBase ?>&page=<?= $i ?>"><?= $i ?></a>
        </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>
