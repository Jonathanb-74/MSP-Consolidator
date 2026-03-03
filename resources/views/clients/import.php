<?php
/** @var array $structures */
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0">Importer des clients</h1>
    <a href="/clients" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Retour à la liste
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">

        <!-- Informations -->
        <div class="alert alert-info mb-4">
            <h5 class="alert-heading"><i class="bi bi-info-circle me-2"></i>Formats acceptés</h5>

            <p class="mb-2 fw-semibold">Format ERP (export logiciel de gestion) :</p>
            <table class="table table-sm table-bordered mb-3">
                <thead class="table-secondary">
                    <tr><th>Colonne Excel</th><th>Obligatoire</th><th>Description</th></tr>
                </thead>
                <tbody>
                    <tr><td><code>Code</code></td><td><span class="badge bg-danger">Oui</span></td><td>Numéro client — <strong>chiffres uniquement</strong> (ex : 000042). Les codes contenant des lettres sont ignorés.</td></tr>
                    <tr><td><code>Raison sociale</code></td><td><span class="badge bg-danger">Oui</span></td><td>Nom officiel du client</td></tr>
                    <tr><td><code>Tél</code></td><td><span class="badge bg-secondary">Non</span></td><td>Téléphone</td></tr>
                    <tr><td><code>E-mail</code></td><td><span class="badge bg-secondary">Non</span></td><td>Adresse email</td></tr>
                    <tr><td><code>Adresse1 / Adresse2 / Adresse3 + CP + Ville</code></td><td><span class="badge bg-secondary">Non</span></td><td>Concaténées en un seul champ adresse</td></tr>
                    <tr><td><code>Actif</code></td><td><span class="badge bg-secondary">Non</span></td><td>"Oui" = actif, autre valeur = inactif</td></tr>
                </tbody>
            </table>

            <p class="mb-2 fw-semibold">Format personnalisé (colonnes en anglais) :</p>
            <table class="table table-sm table-bordered mb-2">
                <thead class="table-secondary">
                    <tr><th>Colonne Excel</th><th>Obligatoire</th><th>Description</th></tr>
                </thead>
                <tbody>
                    <tr><td><code>client_number</code></td><td><span class="badge bg-danger">Oui</span></td><td>Numéro client — <strong>chiffres uniquement</strong></td></tr>
                    <tr><td><code>name</code></td><td><span class="badge bg-danger">Oui</span></td><td>Nom du client</td></tr>
                    <tr><td><code>email</code> / <code>phone</code> / <code>address</code></td><td><span class="badge bg-secondary">Non</span></td><td>Coordonnées</td></tr>
                    <tr><td><code>is_active</code></td><td><span class="badge bg-secondary">Non</span></td><td>1 = actif (défaut), 0 = inactif</td></tr>
                </tbody>
            </table>

            <p class="mb-0 small text-body-secondary">
                <i class="bi bi-funnel me-1"></i>
                <strong>Filtre automatique :</strong> seuls les codes 100% numériques (ex : <code>000042</code>) sont importés.
                Les lignes avec des codes alphanumériques (ex : <code>DEMO01</code>) sont silencieusement ignorées.
                <br><i class="bi bi-arrow-repeat me-1"></i>
                <strong>Upsert :</strong> si le code client existe déjà, ses données sont mises à jour ; sinon il est créé.
            </p>
        </div>

        <!-- Formulaire -->
        <div class="card border-0 bg-body-secondary">
            <div class="card-body">
                <form method="POST" action="/clients/import" enctype="multipart/form-data">

                    <div class="mb-4">
                        <label for="structure" class="form-label fw-semibold">
                            Structure <span class="text-danger">*</span>
                        </label>
                        <select name="structure" id="structure" class="form-select" required>
                            <option value="">Sélectionner une structure…</option>
                            <?php foreach ($structures as $s): ?>
                            <option value="<?= htmlspecialchars($s['code']) ?>">
                                <?= htmlspecialchars($s['code']) ?> — <?= htmlspecialchars($s['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">
                            Tous les clients du fichier seront associés à cette structure.
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="excel_file" class="form-label fw-semibold">
                            Fichier Excel <span class="text-danger">*</span>
                        </label>
                        <input type="file" name="excel_file" id="excel_file"
                               class="form-control" accept=".xlsx,.xls,.csv" required>
                        <div class="form-text">Formats acceptés : .xlsx, .xls, .csv</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload me-1"></i>Importer
                        </button>
                        <a href="/clients" class="btn btn-outline-secondary">Annuler</a>
                    </div>

                </form>
            </div>
        </div>

    </div>
</div>
