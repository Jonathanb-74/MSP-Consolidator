<?php
/** @var array $tags */
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0">Tags clients</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTagModal">
        <i class="bi bi-plus-circle me-1"></i>Nouveau tag
    </button>
</div>

<?php if (empty($tags)): ?>
<div class="text-center py-5 text-body-secondary">
    <i class="bi bi-tags fs-1 d-block mb-2 opacity-25"></i>
    Aucun tag créé.
    <br>
    <button class="btn btn-sm btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#createTagModal">
        Créer le premier tag
    </button>
</div>
<?php else: ?>

<div class="card border-0 bg-body-secondary mb-4">
    <div class="card-body p-3">
        <p class="small text-body-secondary mb-0">
            <i class="bi bi-info-circle me-1"></i>
            Faites glisser les tags pour modifier leur ordre d'affichage. Cet ordre est utilisé dans la liste des clients et dans la vue ESET.
        </p>
    </div>
</div>

<div id="tagsList" class="row g-3">
    <?php foreach ($tags as $tag): ?>
    <div class="col-md-4 col-lg-3 tag-item" data-id="<?= (int)$tag['id'] ?>">
        <div class="card border-0 bg-body-secondary h-100" style="border-left: 4px solid <?= htmlspecialchars($tag['color']) ?> !important; border-left-style: solid !important;">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="drag-handle text-body-secondary" style="cursor:grab" title="Glisser pour réordonner">
                        <i class="bi bi-grip-vertical"></i>
                    </span>
                    <span class="badge rounded-pill fs-6 px-3 py-1 tag-badge"
                          style="background-color:<?= htmlspecialchars($tag['color']) ?>;color:#fff;">
                        <?= htmlspecialchars($tag['name']) ?>
                    </span>
                </div>
                <div class="small text-body-secondary mb-3">
                    <?= (int)$tag['client_count'] ?> client<?= $tag['client_count'] > 1 ? 's' : '' ?>
                </div>
                <div class="d-flex gap-1">
                    <button class="btn btn-xs btn-outline-secondary flex-grow-1"
                            style="font-size:.75rem;padding:.2rem .5rem"
                            onclick="editTag(<?= (int)$tag['id'] ?>, '<?= htmlspecialchars(addslashes($tag['name'])) ?>', '<?= htmlspecialchars($tag['color']) ?>')">
                        <i class="bi bi-pencil me-1"></i>Modifier
                    </button>
                    <button class="btn btn-xs btn-outline-danger"
                            style="font-size:.75rem;padding:.2rem .5rem"
                            onclick="deleteTag(<?= (int)$tag['id'] ?>, '<?= htmlspecialchars(addslashes($tag['name'])) ?>', <?= (int)$tag['client_count'] ?>)">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>

<!-- Modal Créer tag -->
<div class="modal fade" id="createTagModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-tag me-2"></i>Nouveau tag</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nom <span class="text-danger">*</span></label>
                    <input type="text" id="createTagName" class="form-control" placeholder="Ex : FCI, Prioritaire…" maxlength="50">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Couleur</label>
                    <div class="d-flex align-items-center gap-2">
                        <input type="color" id="createTagColor" class="form-control form-control-color" value="#0d6efd" style="width:50px">
                        <div class="flex-grow-1">
                            <div class="d-flex flex-wrap gap-1" id="colorPresets">
                                <button type="button" class="btn btn-sm p-0" style="width:24px;height:24px;background:#0d6efd;border-radius:4px" onclick="setCreateColor('#0d6efd')"></button>
                                <button type="button" class="btn btn-sm p-0" style="width:24px;height:24px;background:#198754;border-radius:4px" onclick="setCreateColor('#198754')"></button>
                                <button type="button" class="btn btn-sm p-0" style="width:24px;height:24px;background:#dc3545;border-radius:4px" onclick="setCreateColor('#dc3545')"></button>
                                <button type="button" class="btn btn-sm p-0" style="width:24px;height:24px;background:#fd7e14;border-radius:4px" onclick="setCreateColor('#fd7e14')"></button>
                                <button type="button" class="btn btn-sm p-0" style="width:24px;height:24px;background:#ffc107;border-radius:4px" onclick="setCreateColor('#ffc107')"></button>
                                <button type="button" class="btn btn-sm p-0" style="width:24px;height:24px;background:#0dcaf0;border-radius:4px" onclick="setCreateColor('#0dcaf0')"></button>
                                <button type="button" class="btn btn-sm p-0" style="width:24px;height:24px;background:#6f42c1;border-radius:4px" onclick="setCreateColor('#6f42c1')"></button>
                                <button type="button" class="btn btn-sm p-0" style="width:24px;height:24px;background:#6c757d;border-radius:4px" onclick="setCreateColor('#6c757d')"></button>
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="form-label small text-body-secondary">Aperçu</label>
                    <div>
                        <span id="createTagPreview" class="badge rounded-pill px-3 py-2 fs-6" style="background-color:#0d6efd">Nom du tag</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" onclick="submitCreateTag()">Créer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Modifier tag -->
<div class="modal fade" id="editTagModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Modifier le tag</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editTagId">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nom <span class="text-danger">*</span></label>
                    <input type="text" id="editTagName" class="form-control" maxlength="50">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Couleur</label>
                    <div class="d-flex align-items-center gap-2">
                        <input type="color" id="editTagColor" class="form-control form-control-color" style="width:50px">
                        <div class="flex-grow-1">
                            <div class="d-flex flex-wrap gap-1">
                                <button type="button" class="btn btn-sm p-0" style="width:24px;height:24px;background:#0d6efd;border-radius:4px" onclick="setEditColor('#0d6efd')"></button>
                                <button type="button" class="btn btn-sm p-0" style="width:24px;height:24px;background:#198754;border-radius:4px" onclick="setEditColor('#198754')"></button>
                                <button type="button" class="btn btn-sm p-0" style="width:24px;height:24px;background:#dc3545;border-radius:4px" onclick="setEditColor('#dc3545')"></button>
                                <button type="button" class="btn btn-sm p-0" style="width:24px;height:24px;background:#fd7e14;border-radius:4px" onclick="setEditColor('#fd7e14')"></button>
                                <button type="button" class="btn btn-sm p-0" style="width:24px;height:24px;background:#ffc107;border-radius:4px" onclick="setEditColor('#ffc107')"></button>
                                <button type="button" class="btn btn-sm p-0" style="width:24px;height:24px;background:#0dcaf0;border-radius:4px" onclick="setEditColor('#0dcaf0')"></button>
                                <button type="button" class="btn btn-sm p-0" style="width:24px;height:24px;background:#6f42c1;border-radius:4px" onclick="setEditColor('#6f42c1')"></button>
                                <button type="button" class="btn btn-sm p-0" style="width:24px;height:24px;background:#6c757d;border-radius:4px" onclick="setEditColor('#6c757d')"></button>
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="form-label small text-body-secondary">Aperçu</label>
                    <div>
                        <span id="editTagPreview" class="badge rounded-pill px-3 py-2 fs-6">Aperçu</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" onclick="submitEditTag()">Enregistrer</button>
            </div>
        </div>
    </div>
</div>

<!-- SortableJS via CDN -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

<script>
// ── Aperçu couleur ──────────────────────────────────────────

document.getElementById('createTagColor')?.addEventListener('input', function () {
    const name = document.getElementById('createTagName').value || 'Nom du tag';
    const prev = document.getElementById('createTagPreview');
    prev.style.backgroundColor = this.value;
    prev.textContent = name;
});
document.getElementById('createTagName')?.addEventListener('input', function () {
    document.getElementById('createTagPreview').textContent = this.value || 'Nom du tag';
});
document.getElementById('editTagColor')?.addEventListener('input', function () {
    const prev = document.getElementById('editTagPreview');
    prev.style.backgroundColor = this.value;
});
document.getElementById('editTagName')?.addEventListener('input', function () {
    document.getElementById('editTagPreview').textContent = this.value || 'Aperçu';
});

function setCreateColor(hex) {
    document.getElementById('createTagColor').value = hex;
    document.getElementById('createTagPreview').style.backgroundColor = hex;
}
function setEditColor(hex) {
    document.getElementById('editTagColor').value = hex;
    document.getElementById('editTagPreview').style.backgroundColor = hex;
}

// ── Créer tag ───────────────────────────────────────────────

function submitCreateTag() {
    const name  = document.getElementById('createTagName').value.trim();
    const color = document.getElementById('createTagColor').value;
    if (!name) { alert('Le nom est requis.'); return; }

    const fd = new FormData();
    fd.append('name', name);
    fd.append('color', color);

    fetch('/tags/create', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                location.reload();
            } else {
                alert(d.message || 'Erreur.');
            }
        })
        .catch(() => alert('Erreur réseau.'));
}

// ── Modifier tag ────────────────────────────────────────────

function editTag(id, name, color) {
    document.getElementById('editTagId').value   = id;
    document.getElementById('editTagName').value = name;
    document.getElementById('editTagColor').value = color;
    const prev = document.getElementById('editTagPreview');
    prev.style.backgroundColor = color;
    prev.textContent = name;
    new bootstrap.Modal(document.getElementById('editTagModal')).show();
}

function submitEditTag() {
    const id    = document.getElementById('editTagId').value;
    const name  = document.getElementById('editTagName').value.trim();
    const color = document.getElementById('editTagColor').value;
    if (!name) { alert('Le nom est requis.'); return; }

    const fd = new FormData();
    fd.append('id', id);
    fd.append('name', name);
    fd.append('color', color);

    fetch('/tags/update', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                location.reload();
            } else {
                alert(d.message || 'Erreur.');
            }
        })
        .catch(() => alert('Erreur réseau.'));
}

// ── Supprimer tag ───────────────────────────────────────────

function deleteTag(id, name, clientCount) {
    const msg = clientCount > 0
        ? `Supprimer le tag « ${name} » ? Il est assigné à ${clientCount} client(s) — les associations seront supprimées.`
        : `Supprimer le tag « ${name} » ?`;

    if (!confirm(msg)) return;

    const fd = new FormData();
    fd.append('id', id);

    fetch('/tags/delete', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                location.reload();
            } else {
                alert(d.message || 'Erreur.');
            }
        })
        .catch(() => alert('Erreur réseau.'));
}

// ── Drag & Drop (SortableJS) ────────────────────────────────

const tagsList = document.getElementById('tagsList');
if (tagsList) {
    Sortable.create(tagsList, {
        handle: '.drag-handle',
        animation: 150,
        onEnd: function () {
            const ids = Array.from(tagsList.querySelectorAll('.tag-item'))
                            .map(el => el.dataset.id)
                            .join(',');
            const fd = new FormData();
            fd.append('ids', ids);
            fetch('/tags/reorder', { method: 'POST', body: fd }).catch(() => {});
        }
    });
}

// Modal créer : fermeture = reset
document.getElementById('createTagModal')?.addEventListener('hidden.bs.modal', () => {
    document.getElementById('createTagName').value = '';
    document.getElementById('createTagColor').value = '#0d6efd';
    document.getElementById('createTagPreview').style.backgroundColor = '#0d6efd';
    document.getElementById('createTagPreview').textContent = 'Nom du tag';
});
</script>
