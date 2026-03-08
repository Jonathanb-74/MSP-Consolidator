<?php
/** @var array $rules  [ ['id'=>int, 'value'=>str, 'type'=>str, 'active'=>int, 'created_at'=>str], ... ] */

$legalForms = array_filter($rules, fn($r) => $r['type'] === 'legal_form');
$customs     = array_filter($rules, fn($r) => $r['type'] === 'custom');
?>

<div class="page-sticky-top">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-0"><i class="bi bi-scissors me-2 text-body-secondary"></i>Normalisation des noms</h4>
            <p class="text-body-secondary small mb-0 mt-1">
                Ces règles sont appliquées avant la comparaison par similarité lors de l'auto-mapping fournisseur ↔ client.
                Ajoutez les suffixes récurrents de vos noms fournisseurs (ex&nbsp;: <code> - Agence</code>, <code> - Groupe</code>).
            </p>
        </div>
    </div>
</div>

<div id="alertZone"></div>

<!-- Exclusions personnalisées -->
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-dash-circle text-danger fs-5"></i>
            <span class="fw-semibold">Exclusions personnalisées</span>
            <span class="text-body-secondary small">— sous-chaîne exacte supprimée du nom</span>
        </div>
        <button class="btn btn-outline-primary btn-sm" id="btnAddCustom">
            <i class="bi bi-plus-lg me-1"></i>Ajouter
        </button>
    </div>

    <!-- Formulaire ajout -->
    <div class="card-body border-bottom d-none" id="formAddCustom">
        <div class="d-flex gap-2 align-items-center">
            <input type="text" class="form-control form-control-sm" id="inputCustomValue"
                   placeholder="ex : - Agence" style="max-width:280px">
            <button class="btn btn-primary btn-sm" onclick="addRule('custom')">
                <i class="bi bi-check-lg me-1"></i>Enregistrer
            </button>
            <button class="btn btn-outline-secondary btn-sm" onclick="toggleForm('formAddCustom')">
                Annuler
            </button>
        </div>
        <div class="text-body-secondary small mt-1">
            La chaîne est supprimée telle quelle (insensible à la casse) partout dans le nom.
        </div>
    </div>

    <div class="card-body p-0">
        <table class="table table-sm mb-0 align-middle" id="tableCustom">
            <thead class="table-dark">
                <tr>
                    <th class="ps-3">Valeur</th>
                    <th style="width:90px">État</th>
                    <th style="width:80px"></th>
                </tr>
            </thead>
            <tbody>
<?php foreach ($customs as $r): ?>
                <tr id="row-<?= $r['id'] ?>">
                    <td class="ps-3">
                        <code><?= htmlspecialchars($r['value']) ?></code>
                    </td>
                    <td>
                        <?php if ($r['active']): ?>
                            <span class="badge bg-success">Actif</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactif</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end pe-3">
                        <button class="btn btn-outline-secondary btn-sm py-0 me-1"
                                title="<?= $r['active'] ? 'Désactiver' : 'Activer' ?>"
                                onclick="toggleRule(<?= $r['id'] ?>)">
                            <i class="bi <?= $r['active'] ? 'bi-pause' : 'bi-play' ?>"></i>
                        </button>
                        <button class="btn btn-outline-danger btn-sm py-0"
                                title="Supprimer"
                                onclick="deleteRule(<?= $r['id'] ?>)">
                            <i class="bi bi-trash3"></i>
                        </button>
                    </td>
                </tr>
<?php endforeach; ?>
<?php if (empty($customs)): ?>
                <tr id="rowEmptyCustom">
                    <td colspan="3" class="text-center text-body-secondary py-3">
                        <i class="bi bi-info-circle me-1"></i>Aucune exclusion personnalisée.
                    </td>
                </tr>
<?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer text-body-secondary small">
        Exemples&nbsp;: <code> - Agence</code>, <code> - Groupe</code>, <code> (test)</code>
    </div>
</div>

<!-- Formes juridiques -->
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-building text-info fs-5"></i>
            <span class="fw-semibold">Formes juridiques</span>
            <span class="text-body-secondary small">— supprimées en tant que mot entier</span>
        </div>
        <button class="btn btn-outline-primary btn-sm" id="btnAddLegal">
            <i class="bi bi-plus-lg me-1"></i>Ajouter
        </button>
    </div>

    <!-- Formulaire ajout -->
    <div class="card-body border-bottom d-none" id="formAddLegal">
        <div class="d-flex gap-2 align-items-center">
            <input type="text" class="form-control form-control-sm" id="inputLegalValue"
                   placeholder="ex: sci" style="max-width:200px">
            <button class="btn btn-primary btn-sm" onclick="addRule('legal_form')">
                <i class="bi bi-check-lg me-1"></i>Enregistrer
            </button>
            <button class="btn btn-outline-secondary btn-sm" onclick="toggleForm('formAddLegal')">
                Annuler
            </button>
        </div>
        <div class="text-body-secondary small mt-1">
            Supprimé uniquement s'il correspond à un mot entier (ex&nbsp;: « SARL » mais pas « sarl-tech »).
        </div>
    </div>

    <div class="card-body p-0">
        <table class="table table-sm mb-0 align-middle" id="tableLegal">
            <thead class="table-dark">
                <tr>
                    <th class="ps-3">Valeur</th>
                    <th style="width:90px">État</th>
                    <th style="width:80px"></th>
                </tr>
            </thead>
            <tbody>
<?php foreach ($legalForms as $r): ?>
                <tr id="row-<?= $r['id'] ?>">
                    <td class="ps-3">
                        <code><?= htmlspecialchars($r['value']) ?></code>
                    </td>
                    <td>
                        <?php if ($r['active']): ?>
                            <span class="badge bg-success">Actif</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactif</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end pe-3">
                        <button class="btn btn-outline-secondary btn-sm py-0 me-1"
                                title="<?= $r['active'] ? 'Désactiver' : 'Activer' ?>"
                                onclick="toggleRule(<?= $r['id'] ?>)">
                            <i class="bi <?= $r['active'] ? 'bi-pause' : 'bi-play' ?>"></i>
                        </button>
                        <button class="btn btn-outline-danger btn-sm py-0"
                                title="Supprimer"
                                onclick="deleteRule(<?= $r['id'] ?>)">
                            <i class="bi bi-trash3"></i>
                        </button>
                    </td>
                </tr>
<?php endforeach; ?>
<?php if (empty($legalForms)): ?>
                <tr id="rowEmptyLegal">
                    <td colspan="3" class="text-center text-body-secondary py-3">
                        <i class="bi bi-info-circle me-1"></i>Aucune forme juridique.
                    </td>
                </tr>
<?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer text-body-secondary small">
        Pré-rempli avec les formes juridiques françaises courantes.
    </div>
</div>

<script>
function showAlert(msg, type = 'success') {
    const z = document.getElementById('alertZone');
    z.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${msg}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
    setTimeout(() => { z.querySelector('.alert')?.classList.remove('show'); }, 3000);
}

function toggleForm(id) {
    const el = document.getElementById(id);
    el.classList.toggle('d-none');
    if (!el.classList.contains('d-none')) {
        el.querySelector('input')?.focus();
    }
}

document.getElementById('btnAddCustom').addEventListener('click', () => toggleForm('formAddCustom'));
document.getElementById('btnAddLegal').addEventListener('click', () => toggleForm('formAddLegal'));

async function addRule(type) {
    const inputId = type === 'custom' ? 'inputCustomValue' : 'inputLegalValue';
    const input   = document.getElementById(inputId);
    const value   = input.value;

    if (!value.trim()) {
        showAlert('La valeur ne peut pas être vide.', 'warning');
        return;
    }

    const res  = await fetch('/settings/normalisation/store', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ value, type }),
    });
    const data = await res.json();

    if (!res.ok) {
        showAlert('❌ ' + data.message, 'danger');
        return;
    }

    // Injecter la ligne dans le bon tableau
    const tableId = type === 'custom' ? 'tableCustom' : 'tableLegal';
    const tbody   = document.querySelector(`#${tableId} tbody`);

    // Retirer ligne "vide"
    tbody.querySelector('#rowEmptyCustom, #rowEmptyLegal')?.remove();

    const tr = document.createElement('tr');
    tr.id = `row-${data.id}`;
    tr.innerHTML = `
        <td class="ps-3"><code>${escHtml(data.value)}</code></td>
        <td><span class="badge bg-success">Actif</span></td>
        <td class="text-end pe-3">
            <button class="btn btn-outline-secondary btn-sm py-0 me-1" title="Désactiver" onclick="toggleRule(${data.id})">
                <i class="bi bi-pause"></i>
            </button>
            <button class="btn btn-outline-danger btn-sm py-0" title="Supprimer" onclick="deleteRule(${data.id})">
                <i class="bi bi-trash3"></i>
            </button>
        </td>`;
    tbody.appendChild(tr);

    input.value = '';
    toggleForm(type === 'custom' ? 'formAddCustom' : 'formAddLegal');
    showAlert(`✅ Règle « ${escHtml(data.value)} » ajoutée.`);
}

async function toggleRule(id) {
    const res  = await fetch('/settings/normalisation/toggle', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ id }),
    });
    const data = await res.json();
    if (!res.ok) { showAlert('❌ ' + data.message, 'danger'); return; }

    const row    = document.getElementById(`row-${id}`);
    const badge  = row.querySelector('.badge');
    const btn    = row.querySelector('.btn-outline-secondary');
    const icon   = btn.querySelector('i');

    if (data.active) {
        badge.className = 'badge bg-success';
        badge.textContent = 'Actif';
        icon.className = 'bi bi-pause';
        btn.title = 'Désactiver';
    } else {
        badge.className = 'badge bg-secondary';
        badge.textContent = 'Inactif';
        icon.className = 'bi bi-play';
        btn.title = 'Activer';
    }
}

async function deleteRule(id) {
    if (!confirm('Supprimer cette règle ?')) return;

    const res  = await fetch('/settings/normalisation/delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ id }),
    });
    const data = await res.json();
    if (!res.ok) { showAlert('❌ ' + data.message, 'danger'); return; }

    document.getElementById(`row-${id}`)?.remove();
    showAlert('Règle supprimée.');
}

function escHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
