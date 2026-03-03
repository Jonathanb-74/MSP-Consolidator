<?php
/** @var int $totalClients */
/** @var array $structureStats */
/** @var array|false $esetStats */
/** @var array $providers */
/** @var int $pendingMappings */
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0">Dashboard</h1>
    <span class="text-body-secondary small">Vue d'ensemble</span>
</div>

<!-- KPI cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 bg-body-secondary h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-body-secondary small mb-1">Clients actifs</p>
                        <h2 class="mb-0"><?= number_format($totalClients) ?></h2>
                    </div>
                    <i class="bi bi-people fs-1 text-primary opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 bg-body-secondary h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-body-secondary small mb-1">Licences ESET</p>
                        <h2 class="mb-0"><?= number_format($esetStats['total_licenses'] ?? 0) ?></h2>
                    </div>
                    <i class="bi bi-shield-lock fs-1 text-success opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 bg-body-secondary h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-body-secondary small mb-1">Licences expirées</p>
                        <h2 class="mb-0 text-danger"><?= number_format($esetStats['expired_licenses'] ?? 0) ?></h2>
                    </div>
                    <i class="bi bi-x-circle fs-1 text-danger opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 bg-body-secondary h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-body-secondary small mb-1">Expirent dans 30j</p>
                        <h2 class="mb-0 text-warning"><?= number_format($esetStats['expiring_soon'] ?? 0) ?></h2>
                    </div>
                    <i class="bi bi-exclamation-triangle fs-1 text-warning opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Répartition par structure -->
    <div class="col-lg-4">
        <div class="card border-0 bg-body-secondary h-100">
            <div class="card-header bg-transparent border-0 pb-0">
                <h5 class="card-title mb-0"><i class="bi bi-diagram-3 me-2"></i>Structures</h5>
            </div>
            <div class="card-body">
                <?php
                $structureColors = ['FCI' => 'primary', 'LTI' => 'success', 'LNI' => 'info', 'MACSHOP' => 'warning'];
                foreach ($structureStats as $stat):
                    $color = $structureColors[$stat['code']] ?? 'secondary';
                    $pct   = $totalClients > 0 ? round($stat['total'] / $totalClients * 100) : 0;
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span><span class="badge bg-<?= $color ?> me-1"><?= htmlspecialchars($stat['code']) ?></span></span>
                        <span class="small text-body-secondary"><?= $stat['total'] ?> clients (<?= $pct ?>%)</span>
                    </div>
                    <div class="progress" style="height:6px">
                        <div class="progress-bar bg-<?= $color ?>" style="width:<?= $pct ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Statut ESET -->
    <div class="col-lg-4">
        <div class="card border-0 bg-body-secondary h-100">
            <div class="card-header bg-transparent border-0 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="bi bi-shield-lock me-2 text-success"></i>ESET</h5>
                <a href="/eset/licenses" class="btn btn-sm btn-outline-success">Voir les licences</a>
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-7 fw-normal text-body-secondary">Companies</dt>
                    <dd class="col-5 text-end mb-1"><?= number_format($esetStats['total_companies'] ?? 0) ?></dd>

                    <dt class="col-7 fw-normal text-body-secondary">Sièges total</dt>
                    <dd class="col-5 text-end mb-1"><?= number_format($esetStats['total_seats'] ?? 0) ?></dd>

                    <dt class="col-7 fw-normal text-body-secondary">Sièges utilisés</dt>
                    <dd class="col-5 text-end mb-1"><?= number_format($esetStats['used_seats'] ?? 0) ?></dd>

                    <dt class="col-7 fw-normal text-body-secondary">Sièges libres</dt>
                    <dd class="col-5 text-end mb-1">
                        <?= number_format(($esetStats['total_seats'] ?? 0) - ($esetStats['used_seats'] ?? 0)) ?>
                    </dd>
                </dl>

                <?php if (($esetStats['total_seats'] ?? 0) > 0): ?>
                <?php $usedPct = round($esetStats['used_seats'] / $esetStats['total_seats'] * 100); ?>
                <div class="mt-3">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="text-body-secondary">Utilisation sièges</small>
                        <small><?= $usedPct ?>%</small>
                    </div>
                    <div class="progress" style="height:8px">
                        <div class="progress-bar <?= $usedPct > 90 ? 'bg-danger' : ($usedPct > 70 ? 'bg-warning' : 'bg-success') ?>"
                             style="width:<?= $usedPct ?>%"></div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="mt-3 d-flex gap-2">
                    <a href="/eset/sync-logs" class="btn btn-sm btn-outline-secondary flex-fill">
                        <i class="bi bi-clock-history me-1"></i>Logs
                    </a>
                    <button class="btn btn-sm btn-outline-success flex-fill" id="btnSyncEset">
                        <i class="bi bi-arrow-clockwise me-1"></i>Sync maintenant
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Statut fournisseurs -->
    <div class="col-lg-4">
        <div class="card border-0 bg-body-secondary h-100">
            <div class="card-header bg-transparent border-0 pb-0">
                <h5 class="card-title mb-0"><i class="bi bi-cloud-check me-2"></i>Fournisseurs</h5>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush bg-transparent">
                    <?php foreach ($providers as $p): ?>
                    <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center">
                        <div>
                            <span class="fw-medium"><?= htmlspecialchars($p['name']) ?></span>
                            <?php if ($p['last_sync_at']): ?>
                            <br><small class="text-body-secondary">
                                Sync : <?= date('d/m/Y H:i', strtotime($p['last_sync_at'])) ?>
                            </small>
                            <?php endif; ?>
                        </div>
                        <?php if (!$p['is_enabled']): ?>
                            <span class="badge bg-secondary">Désactivé</span>
                        <?php elseif ($p['last_sync_status'] === 'success'): ?>
                            <span class="badge bg-success">OK</span>
                        <?php elseif ($p['last_sync_status'] === 'error'): ?>
                            <span class="badge bg-danger">Erreur</span>
                        <?php elseif ($p['last_sync_status'] === 'running'): ?>
                            <span class="badge bg-info">En cours</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Jamais</span>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php if ($pendingMappings > 0): ?>
            <div class="card-footer bg-transparent border-0">
                <a href="/mapping?confirmed=0" class="btn btn-sm btn-warning w-100">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    <?= $pendingMappings ?> mapping(s) à confirmer
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.getElementById('btnSyncEset')?.addEventListener('click', function () {
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sync en cours...';

    fetch('/eset/sync', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => {
            alert(data.success ? '✅ ' + data.message : '❌ ' + data.message);
            if (data.success) location.reload();
        })
        .catch(() => alert('❌ Erreur réseau'))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Sync maintenant';
        });
});
</script>
