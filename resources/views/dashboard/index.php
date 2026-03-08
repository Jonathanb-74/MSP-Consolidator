<?php
/** @var int   $totalClients */
/** @var array $tagStats */
/** @var array $esetStats */
/** @var array $bcStats */
/** @var array $ninjaStats */
/** @var array $providerConnections */
/** @var int   $pendingMappings */

$esetTotal    = (int)($esetStats['total_seats']    ?? 0);
$esetUsed     = (int)($esetStats['used_seats']     ?? 0);
$esetUsedPct  = $esetTotal > 0 ? round($esetUsed / $esetTotal * 100) : 0;

$bcTotal      = (int)($bcStats['total_seats']      ?? 0);
$bcAssigned   = (int)($bcStats['assigned_seats']   ?? 0);
$bcPct        = $bcTotal > 0 ? round($bcAssigned / $bcTotal * 100) : 0;

$ninjaDevicesTotal = (int)($ninjaStats['devices_online'] ?? 0) + (int)($ninjaStats['devices_offline'] ?? 0);
$ninjaOnlinePct    = $ninjaDevicesTotal > 0 ? round($ninjaStats['devices_online'] / $ninjaDevicesTotal * 100) : 0;
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0">Dashboard</h1>
    <span class="text-body-secondary small">Vue d'ensemble — <?= date('d/m/Y') ?></span>
</div>

<?php if ($pendingMappings > 0): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 py-2 mb-4" role="alert">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <span><strong><?= $pendingMappings ?> mapping(s)</strong> en attente de confirmation.</span>
    <a href="/mapping?confirmed=0" class="btn btn-sm btn-warning ms-auto">Vérifier</a>
</div>
<?php endif; ?>

<!-- ── KPIs globaux ── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <a href="/clients" class="text-decoration-none">
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
        </a>
    </div>

    <div class="col-6 col-xl-3">
        <a href="/eset/licenses" class="text-decoration-none">
        <div class="card border-0 bg-body-secondary h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-body-secondary small mb-1">Postes ESET</p>
                        <h2 class="mb-0"><?= number_format($esetUsed) ?>
                            <small class="fs-6 text-body-secondary fw-normal">/ <?= number_format($esetTotal) ?></small>
                        </h2>
                        <small class="text-body-secondary"><?= $esetUsedPct ?>% utilisés</small>
                    </div>
                    <i class="bi bi-shield-lock fs-1 text-success opacity-50"></i>
                </div>
            </div>
        </div>
        </a>
    </div>

    <div class="col-6 col-xl-3">
        <a href="/becloud/licenses" class="text-decoration-none">
        <div class="card border-0 bg-body-secondary h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-body-secondary small mb-1">Abonnements Be-Cloud</p>
                        <h2 class="mb-0"><?= number_format($bcStats['active_subscriptions'] ?? 0) ?>
                            <small class="fs-6 text-body-secondary fw-normal">actifs</small>
                        </h2>
                        <small class="text-body-secondary"><?= number_format($bcStats['total_customers'] ?? 0) ?> clients</small>
                    </div>
                    <i class="bi bi-cloud-check fs-1 text-info opacity-50"></i>
                </div>
            </div>
        </div>
        </a>
    </div>

    <div class="col-6 col-xl-3">
        <a href="/ninjaone/licenses" class="text-decoration-none">
        <div class="card border-0 bg-body-secondary h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-body-secondary small mb-1">Équipements NinjaOne</p>
                        <h2 class="mb-0"><?= number_format($ninjaStats['devices_online'] ?? 0) ?>
                            <small class="fs-6 text-body-secondary fw-normal">en ligne</small>
                        </h2>
                        <small class="text-body-secondary"><?= number_format($ninjaDevicesTotal) ?> au total</small>
                    </div>
                    <i class="bi bi-hdd-network fs-1 text-warning opacity-50"></i>
                </div>
            </div>
        </div>
        </a>
    </div>
</div>

<!-- ── Détail par provider ── -->
<div class="row g-4 mb-4">

    <!-- ESET -->
    <div class="col-lg-4">
        <div class="card border-0 bg-body-secondary h-100">
            <div class="card-header bg-transparent border-0 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="bi bi-shield-lock me-2 text-success"></i>ESET</h5>
                <a href="/eset/licenses" class="btn btn-sm btn-outline-secondary">Licences</a>
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-7 fw-normal text-body-secondary">Sociétés mappées</dt>
                    <dd class="col-5 text-end mb-2"><?= number_format($esetStats['total_companies'] ?? 0) ?></dd>

                    <dt class="col-7 fw-normal text-body-secondary">Sièges commandés</dt>
                    <dd class="col-5 text-end mb-2"><?= number_format($esetTotal) ?></dd>

                    <dt class="col-7 fw-normal text-body-secondary">Sièges utilisés</dt>
                    <dd class="col-5 text-end mb-2"><?= number_format($esetUsed) ?></dd>

                    <dt class="col-7 fw-normal text-body-secondary">Sièges libres</dt>
                    <dd class="col-5 text-end mb-2"><?= number_format($esetTotal - $esetUsed) ?></dd>
                </dl>

                <?php if ($esetTotal > 0): ?>
                <div class="mt-2 mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="text-body-secondary">Utilisation</small>
                        <small><?= $esetUsedPct ?>%</small>
                    </div>
                    <div class="progress" style="height:6px">
                        <div class="progress-bar <?= $esetUsedPct >= 100 ? 'bg-danger' : ($esetUsedPct > 85 ? 'bg-warning' : 'bg-success') ?>"
                             style="width:<?= min(100, $esetUsedPct) ?>%"></div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- États des licences -->
                <div class="d-flex gap-2 flex-wrap mb-3">
                    <?php if (($esetStats['normal_licenses'] ?? 0) > 0): ?>
                    <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">
                        <?= $esetStats['normal_licenses'] ?> Active<?= $esetStats['normal_licenses'] > 1 ? 's' : '' ?>
                    </span>
                    <?php endif; ?>
                    <?php if (($esetStats['full_licenses'] ?? 0) > 0): ?>
                    <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">
                        <?= $esetStats['full_licenses'] ?> Complète<?= $esetStats['full_licenses'] > 1 ? 's' : '' ?>
                    </span>
                    <?php endif; ?>
                    <?php if (($esetStats['suspended_licenses'] ?? 0) > 0): ?>
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                        <?= $esetStats['suspended_licenses'] ?> Suspendue<?= $esetStats['suspended_licenses'] > 1 ? 's' : '' ?>
                    </span>
                    <?php endif; ?>
                    <?php if (($esetStats['problem_licenses'] ?? 0) > 0): ?>
                    <span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle">
                        <?= $esetStats['problem_licenses'] ?> Problème<?= $esetStats['problem_licenses'] > 1 ? 's' : '' ?>
                    </span>
                    <?php endif; ?>
                    <?php if (($esetStats['total_licenses'] ?? 0) === 0): ?>
                    <span class="text-body-secondary small">Aucune licence synchronisée</span>
                    <?php endif; ?>
                </div>

                <div class="d-flex gap-2">
                    <a href="/eset/sync-logs" class="btn btn-sm btn-outline-secondary flex-fill">
                        <i class="bi bi-clock-history me-1"></i>Logs
                    </a>
                    <button class="btn btn-sm btn-primary flex-fill" onclick="window.openSyncModal?.(null, 'eset')">
                        <i class="bi bi-arrow-clockwise me-1"></i>Sync
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Be-Cloud -->
    <div class="col-lg-4">
        <div class="card border-0 bg-body-secondary h-100">
            <div class="card-header bg-transparent border-0 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="bi bi-cloud-check me-2 text-info"></i>Be-Cloud</h5>
                <a href="/becloud/licenses" class="btn btn-sm btn-outline-secondary">Abonnements</a>
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-7 fw-normal text-body-secondary">Clients</dt>
                    <dd class="col-5 text-end mb-2"><?= number_format($bcStats['total_customers'] ?? 0) ?></dd>

                    <dt class="col-7 fw-normal text-body-secondary">Abonnements actifs</dt>
                    <dd class="col-5 text-end mb-2"><?= number_format($bcStats['active_subscriptions'] ?? 0) ?></dd>

                    <dt class="col-7 fw-normal text-body-secondary">Licences disponibles</dt>
                    <dd class="col-5 text-end mb-2"><?= number_format($bcTotal) ?></dd>

                    <dt class="col-7 fw-normal text-body-secondary">Licences assignées</dt>
                    <dd class="col-5 text-end mb-2"><?= number_format($bcAssigned) ?></dd>
                </dl>

                <?php if ($bcTotal > 0): ?>
                <div class="mt-2 mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="text-body-secondary">Taux d'assignation</small>
                        <small><?= $bcPct ?>%</small>
                    </div>
                    <div class="progress" style="height:6px">
                        <div class="progress-bar bg-info" style="width:<?= min(100, $bcPct) ?>%"></div>
                    </div>
                </div>
                <?php else: ?>
                <p class="text-body-secondary small mb-3">Aucun abonnement synchronisé</p>
                <?php endif; ?>

                <div class="d-flex gap-2">
                    <a href="/becloud/sync-logs" class="btn btn-sm btn-outline-secondary flex-fill">
                        <i class="bi bi-clock-history me-1"></i>Logs
                    </a>
                    <button class="btn btn-sm btn-primary flex-fill" onclick="window.openSyncModal?.(null, 'becloud')">
                        <i class="bi bi-arrow-clockwise me-1"></i>Sync
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- NinjaOne -->
    <div class="col-lg-4">
        <div class="card border-0 bg-body-secondary h-100">
            <div class="card-header bg-transparent border-0 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="bi bi-hdd-network me-2 text-warning"></i>NinjaOne</h5>
                <a href="/ninjaone/licenses" class="btn btn-sm btn-outline-secondary">Équipements</a>
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-7 fw-normal text-body-secondary">Organisations</dt>
                    <dd class="col-5 text-end mb-2"><?= number_format($ninjaStats['total_orgs'] ?? 0) ?></dd>

                    <dt class="col-7 fw-normal text-body-secondary">RMM</dt>
                    <dd class="col-5 text-end mb-2"><?= number_format($ninjaStats['rmm_total'] ?? 0) ?></dd>

                    <dt class="col-7 fw-normal text-body-secondary">NMS</dt>
                    <dd class="col-5 text-end mb-2"><?= number_format($ninjaStats['nms_total'] ?? 0) ?></dd>

                    <dt class="col-7 fw-normal text-body-secondary">MDM</dt>
                    <dd class="col-5 text-end mb-2"><?= number_format($ninjaStats['mdm_total'] ?? 0) ?></dd>
                </dl>

                <?php if ($ninjaDevicesTotal > 0): ?>
                <div class="mt-2 mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="text-body-secondary">
                            <span class="text-success"><?= number_format($ninjaStats['devices_online']) ?> en ligne</span>
                            · <?= number_format($ninjaStats['devices_offline']) ?> hors ligne
                        </small>
                        <small><?= $ninjaOnlinePct ?>%</small>
                    </div>
                    <div class="progress" style="height:6px">
                        <div class="progress-bar <?= $ninjaOnlinePct < 80 ? 'bg-warning' : 'bg-success' ?>"
                             style="width:<?= $ninjaOnlinePct ?>%"></div>
                    </div>
                </div>
                <?php else: ?>
                <p class="text-body-secondary small mb-3">Aucun équipement synchronisé</p>
                <?php endif; ?>

                <div class="d-flex gap-2">
                    <a href="/ninjaone/sync-logs" class="btn btn-sm btn-outline-secondary flex-fill">
                        <i class="bi bi-clock-history me-1"></i>Logs
                    </a>
                    <button class="btn btn-sm btn-primary flex-fill" onclick="window.openSyncModal?.(null, 'ninjaone')">
                        <i class="bi bi-arrow-clockwise me-1"></i>Sync
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Tags + Connexions ── -->
<div class="row g-4">

    <!-- Tags -->
    <?php if (!empty($tagStats)): ?>
    <div class="col-lg-6">
        <div class="card border-0 bg-body-secondary h-100">
            <div class="card-header bg-transparent border-0 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="bi bi-tags me-2"></i>Répartition par tag</h5>
                <a href="/tags" class="btn btn-sm btn-outline-secondary">Gérer</a>
            </div>
            <div class="card-body">
                <?php foreach ($tagStats as $stat):
                    $pct = $totalClients > 0 ? round($stat['total'] / $totalClients * 100) : 0;
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="badge rounded-pill" style="background-color:<?= htmlspecialchars($stat['color']) ?>">
                            <?= htmlspecialchars($stat['name']) ?>
                        </span>
                        <span class="small text-body-secondary"><?= $stat['total'] ?> / <?= $totalClients ?> clients</span>
                    </div>
                    <div class="progress" style="height:5px">
                        <div class="progress-bar" style="width:<?= $pct ?>%;background-color:<?= htmlspecialchars($stat['color']) ?>"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Connexions fournisseurs -->
    <div class="col-lg-<?= !empty($tagStats) ? '6' : '12' ?>">
        <div class="card border-0 bg-body-secondary h-100">
            <div class="card-header bg-transparent border-0 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="bi bi-plug me-2"></i>Connexions</h5>
                <a href="/settings/connections" class="btn btn-sm btn-outline-secondary">Gérer</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($providerConnections)): ?>
                <p class="text-body-secondary small p-3 mb-0">Aucune connexion active.</p>
                <?php else: ?>
                <ul class="list-group list-group-flush bg-transparent">
                    <?php foreach ($providerConnections as $conn):
                        // Prendre la date la plus récente entre provider_connections et sync_logs
                        $lastSyncTs = max(
                            $conn['pc_last_sync_at'] ? strtotime($conn['pc_last_sync_at']) : 0,
                            $conn['sl_last_sync_at'] ? strtotime($conn['sl_last_sync_at']) : 0
                        );
                        $syncAgo = '';
                        if ($lastSyncTs > 0) {
                            $diff = time() - $lastSyncTs;
                            if ($diff < 3600)      $syncAgo = 'il y a ' . round($diff / 60) . ' min';
                            elseif ($diff < 86400) $syncAgo = 'il y a ' . round($diff / 3600) . 'h';
                            else                   $syncAgo = 'il y a ' . round($diff / 86400) . 'j';
                        }
                        // Statut : priorité à provider_connections, fallback sur sync_logs
                        $syncStatus = $conn['sync_status'] !== 'idle' ? $conn['sync_status'] : ($conn['sl_status'] ?? 'idle');
                        $statusBadge = match($syncStatus) {
                            'success' => ['bg-success', 'OK'],
                            'error'   => ['bg-danger', 'Erreur'],
                            'running' => ['bg-info', 'En cours'],
                            'partial' => ['bg-warning text-dark', 'Partiel'],
                            default   => ['bg-secondary', 'Inactif'],
                        };
                    ?>
                    <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center py-2">
                        <div>
                            <span class="fw-medium small"><?= htmlspecialchars($conn['provider_name']) ?></span>
                            <span class="text-body-secondary small ms-1">— <?= htmlspecialchars($conn['connection_name']) ?></span>
                            <br><small class="text-body-secondary">
                                <?= $syncAgo ? $syncAgo : 'Jamais synchronisé' ?>
                            </small>
                        </div>
                        <span class="badge <?= $statusBadge[0] ?>"><?= $statusBadge[1] ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
