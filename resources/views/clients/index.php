<?php
/** @var array $clients */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var string $search */
/** @var int $tagId */
/** @var array $allTags */
/** @var string $sortBy */
/** @var string $sortDir */

/**
 * Parse tags_raw "id:name:color;;id:name:color" → array of ['id','name','color']
 */
function parseTags(?string $raw): array {
    if (!$raw) return [];
    return array_map(function($part) {
        [$id, $name, $color] = array_pad(explode(':', $part, 3), 3, '');
        return ['id' => (int)$id, 'name' => $name, 'color' => $color];
    }, explode(';;', $raw));
}

function clientSortLink(string $col, string $current, string $dir, string $label, array $queryParams): string {
    $newDir = ($current === $col && $dir === 'ASC') ? 'DESC' : 'ASC';
    $icon   = $current === $col ? ($dir === 'ASC' ? ' ↑' : ' ↓') : '';
    $params = array_merge($queryParams, ['sort' => $col, 'dir' => $newDir]);
    return '<a href="/clients?' . http_build_query($params) . '" class="text-white text-decoration-none">'
         . htmlspecialchars($label) . $icon . '</a>';
}
?>

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h1 class="h3 mb-0">
            Clients
            <span class="badge bg-secondary ms-2"><?= number_format($total) ?></span>
        </h1>
    </div>
    <div class="d-flex gap-2">
        <a href="/tags" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-tags me-1"></i>Gérer les tags
        </a>
        <a href="/clients/import" class="btn btn-sm btn-primary">
            <i class="bi bi-file-earmark-excel me-1"></i>Importer Excel
        </a>
    </div>
</div>

<!-- Filtres -->
<form method="GET" action="/clients" class="row g-2 mb-4" id="filterForm">
    <div class="col-md-5">
        <div class="input-group input-group-sm">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" name="search" class="form-control"
                   placeholder="Rechercher (nom, numéro client, email)…"
                   value="<?= htmlspecialchars($search) ?>">
        </div>
    </div>
    <div class="col-md-3">
        <select name="tag" class="form-select form-select-sm">
            <option value="">Tous les tags</option>
            <?php foreach ($allTags as $t): ?>
                <option value="<?= (int)$t['id'] ?>" <?= $tagId === (int)$t['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($t['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-outline-secondary">Filtrer</button>
        <a href="/clients" class="btn btn-sm btn-outline-secondary">Réinitialiser</a>
    </div>
</form>

<!-- Tableau -->
<div class="table-responsive">
    <table class="table table-hover align-middle table-sm">
        <?php $qp = ['search' => $search, 'tag' => $tagId ?: '']; ?>
        <thead class="table-dark">
            <tr>
                <th><?= clientSortLink('client_number', $sortBy, $sortDir, 'N° Client', $qp) ?></th>
                <th><?= clientSortLink('name', $sortBy, $sortDir, 'Nom', $qp) ?></th>
                <th>Tags</th>
                <th><?= clientSortLink('email', $sortBy, $sortDir, 'Email', $qp) ?></th>
                <th class="text-center"><?= clientSortLink('providers', $sortBy, $sortDir, 'Fournisseurs', $qp) ?></th>
                <th class="text-center"><?= clientSortLink('status', $sortBy, $sortDir, 'Statut', $qp) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($clients)): ?>
            <tr>
                <td colspan="6" class="text-center text-body-secondary py-5">
                    <i class="bi bi-people fs-1 d-block mb-2 opacity-25"></i>
                    Aucun client trouvé.
                    <a href="/clients/import">Importer un fichier Excel</a>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($clients as $client):
                $clientTags = parseTags($client['tags_raw'] ?? null);
            ?>
            <tr>
                <td><code class="small"><?= htmlspecialchars($client['client_number']) ?></code></td>
                <td class="fw-medium"><?= htmlspecialchars($client['name']) ?></td>
                <td style="min-width:180px">
                    <div class="d-flex flex-wrap gap-1 align-items-center">
                        <?php foreach ($clientTags as $tag): ?>
                        <span class="badge rounded-pill client-tag"
                              style="background-color:<?= htmlspecialchars($tag['color']) ?>;cursor:pointer"
                              title="Cliquer pour retirer"
                              onclick="toggleTag(<?= (int)$client['id'] ?>, <?= (int)$tag['id'] ?>, this)">
                            <?= htmlspecialchars($tag['name']) ?>
                        </span>
                        <?php endforeach; ?>

                        <!-- Bouton ajouter tag -->
                        <div class="dropdown d-inline-block">
                            <button class="btn btn-xs btn-outline-secondary rounded-pill py-0 px-1"
                                    style="font-size:.7rem;line-height:1.6"
                                    type="button"
                                    data-bs-toggle="dropdown"
                                    data-bs-auto-close="outside"
                                    title="Ajouter un tag">
                                <i class="bi bi-plus"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-sm shadow" style="min-width:150px">
                                <?php if (empty($allTags)): ?>
                                <li><span class="dropdown-item-text small text-body-secondary">
                                    <a href="/tags" class="small">Créer des tags d'abord</a>
                                </span></li>
                                <?php else: ?>
                                <?php
                                $assignedIds = array_column($clientTags, 'id');
                                foreach ($allTags as $at):
                                    $isAssigned = in_array((int)$at['id'], $assignedIds, true);
                                ?>
                                <li>
                                    <a class="dropdown-item small d-flex align-items-center gap-2 tag-option <?= $isAssigned ? 'text-body-secondary' : '' ?>"
                                       href="#"
                                       data-client="<?= (int)$client['id'] ?>"
                                       data-tag="<?= (int)$at['id'] ?>"
                                       data-color="<?= htmlspecialchars($at['color']) ?>"
                                       data-name="<?= htmlspecialchars($at['name']) ?>"
                                       onclick="toggleTagFromMenu(this, event)">
                                        <span class="badge rounded-pill" style="background-color:<?= htmlspecialchars($at['color']) ?>;width:10px;height:10px;padding:0"></span>
                                        <?= htmlspecialchars($at['name']) ?>
                                        <?php if ($isAssigned): ?><i class="bi bi-check ms-auto"></i><?php endif; ?>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </td>
                <td class="small text-body-secondary"><?= htmlspecialchars($client['email'] ?? '—') ?></td>
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
$queryBase  = http_build_query(['search' => $search, 'tag' => $tagId ?: '', 'sort' => $sortBy, 'dir' => $sortDir]);
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

<script>
// ── Toggle tag via clic sur badge ───────────────────────────

function toggleTag(clientId, tagId, badgeEl) {
    const fd = new FormData();
    fd.append('client_id', clientId);
    fd.append('tag_id', tagId);

    fetch('/clients/tag', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success && d.action === 'removed') {
                badgeEl.remove();
            }
        })
        .catch(() => {});
}

// ── Toggle tag via menu dropdown ────────────────────────────

function toggleTagFromMenu(link, e) {
    e.preventDefault();
    const clientId = parseInt(link.dataset.client);
    const tagId    = parseInt(link.dataset.tag);
    const color    = link.dataset.color;
    const name     = link.dataset.name;

    const fd = new FormData();
    fd.append('client_id', clientId);
    fd.append('tag_id', tagId);

    fetch('/clients/tag', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (!d.success) return;

            // Trouver le conteneur de tags de cette ligne
            const row     = link.closest('tr');
            const tagCell = row.querySelector('td:nth-child(3) .d-flex');

            if (d.action === 'added') {
                // Créer badge
                const span = document.createElement('span');
                span.className = 'badge rounded-pill client-tag';
                span.style.backgroundColor = color;
                span.style.cursor = 'pointer';
                span.title = 'Cliquer pour retirer';
                span.textContent = name;
                span.onclick = function() { toggleTag(clientId, tagId, span); };
                // Insérer avant le dropdown
                const dropdown = tagCell.querySelector('.dropdown');
                tagCell.insertBefore(span, dropdown);

                // Ajouter check dans le menu
                link.classList.remove('text-body-secondary');
                if (!link.querySelector('.bi-check')) {
                    const check = document.createElement('i');
                    check.className = 'bi bi-check ms-auto';
                    link.appendChild(check);
                }
            } else {
                // Retirer badge existant
                tagCell.querySelectorAll('.client-tag').forEach(b => {
                    if (b.textContent.trim() === name) b.remove();
                });
                // Retirer check dans menu
                link.classList.add('text-body-secondary');
                link.querySelector('.bi-check')?.remove();
            }
        })
        .catch(() => {});
}
</script>
