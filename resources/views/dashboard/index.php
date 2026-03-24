<?php
/** @var int   $totalClients */
/** @var array $tagStats */
/** @var array $esetStats */
/** @var array $bcStats */
/** @var array $ninjaStats */
/** @var array $infoStats */
/** @var array $upcomingExpirations */
/** @var int   $expiringCount */
/** @var array $providerConnections */
/** @var int   $errorConnsCount */
/** @var int   $pendingMappings */

// ESET
$esetTotal   = (int)($esetStats['total_seats'] ?? 0);
$esetUsed    = (int)($esetStats['used_seats']  ?? 0);
$esetUsedPct = $esetTotal > 0 ? round($esetUsed / $esetTotal * 100) : 0;

// Be-Cloud licences M365 réelles
$bcLicTotal    = (int)($bcStats['lic_total']    ?? 0);
$bcLicConsumed = (int)($bcStats['lic_consumed'] ?? 0);
$bcLicPct      = $bcLicTotal > 0 ? round($bcLicConsumed / $bcLicTotal * 100) : 0;

// NinjaOne
$ninjaTotal     = (int)($ninjaStats['devices_online'] ?? 0) + (int)($ninjaStats['devices_offline'] ?? 0);
$ninjaOnlinePct = $ninjaTotal > 0 ? round(($ninjaStats['devices_online'] ?? 0) / $ninjaTotal * 100) : 0;

// Infomaniak
$infoActive     = (int)($infoStats['active_products']  ?? 0);
$infoExpiring   = (int)($infoStats['expiring_30d']     ?? 0);
$infoExpired    = (int)($infoStats['expired_products']  ?? 0);

// Provider badge helpers
$providerBadge = ['becloud' => 'bg-success', 'infomaniak' => 'bg-warning text-dark'];
$providerLabel = ['becloud' => 'Be-Cloud',   'infomaniak' => 'Infomaniak'];

$today = new DateTime();
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0">Dashboard</h1>
    <span class="text-body-secondary small">Vue d'ensemble — <?= date('d/m/Y') ?></span>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     Zone 1 — Alertes prioritaires
     ═══════════════════════════════════════════════════════════════ -->
<?php if ($pendingMappings > 0 || $expiringCount > 0 || $errorConnsCount > 0): ?>
<div class="d-flex flex-wrap gap-2 mb-4">

    <?php if ($errorConnsCount > 0): ?>
    <a href="/settings/connections" class="text-decoration-none">
        <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3 mb-0">
            <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
            <span><strong><?= $errorConnsCount ?></strong> connexion<?= $errorConnsCount > 1 ? 's' : '' ?> en erreur de synchronisation</span>
        </div>
    </a>
    <?php endif; ?>

    <?php if ($expiringCount > 0): ?>
    <a href="/calendar" class="text-decoration-none">
        <div class="alert alert-warning d-flex align-items-center gap-2 py-2 px-3 mb-0">
            <i class="bi bi-calendar-x-fill flex-shrink-0"></i>
            <span><strong><?= $expiringCount ?></strong> expiration<?= $expiringCount > 1 ? 's' : '' ?> dans les 30 prochains jours</span>
        </div>
    </a>
    <?php endif; ?>

    <?php if ($pendingMappings > 0): ?>
    <a href="/mapping?confirmed=0" class="text-decoration-none">
        <div class="alert alert-secondary d-flex align-items-center gap-2 py-2 px-3 mb-0">
            <i class="bi bi-link-45deg flex-shrink-0"></i>
            <span><strong><?= $pendingMappings ?></strong> mapping<?= $pendingMappings > 1 ? 's' : '' ?> en attente de confirmation</span>
        </div>
    </a>
    <?php endif; ?>

</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════════
     Zone 2 — KPIs globaux (5 cartes)
     ═══════════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-4">

    <!-- Clients actifs -->
    <div class="col-6 col-xl">
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

    <!-- ESET -->
    <div class="col-6 col-xl">
        <a href="/eset/licenses" class="text-decoration-none">
        <div class="card border-0 bg-body-secondary h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-body-secondary small mb-1">ESET — Sièges</p>
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

    <!-- Be-Cloud M365 -->
    <div class="col-6 col-xl">
        <a href="/becloud/licenses" class="text-decoration-none">
        <div class="card border-0 bg-body-secondary h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-body-secondary small mb-1">Be-Cloud — M365</p>
                        <h2 class="mb-0"><?= number_format($bcLicConsumed) ?>
                            <small class="fs-6 text-body-secondary fw-normal">/ <?= number_format($bcLicTotal) ?></small>
                        </h2>
                        <small class="text-body-secondary"><?= number_format($bcStats['total_customers'] ?? 0) ?> clients</small>
                    </div>
                    <i class="bi bi-cloud-check fs-1 text-info opacity-50"></i>
                </div>
            </div>
        </div>
        </a>
    </div>

    <!-- NinjaOne -->
    <div class="col-6 col-xl">
        <a href="/ninjaone/devices" class="text-decoration-none">
        <div class="card border-0 bg-body-secondary h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-body-secondary small mb-1">NinjaOne — Équipements</p>
                        <h2 class="mb-0"><?= number_format($ninjaStats['devices_online'] ?? 0) ?>
                            <small class="fs-6 text-body-secondary fw-normal">en ligne</small>
                        </h2>
                        <small class="text-body-secondary"><?= number_format($ninjaTotal) ?> au total</small>
                    </div>
                    <i class="bi bi-hdd-network fs-1 text-warning opacity-50"></i>
                </div>
            </div>
        </div>
        </a>
    </div>

    <!-- Infomaniak -->
    <div class="col-6 col-xl">
        <a href="/infomaniak/licenses" class="text-decoration-none">
        <div class="card border-0 bg-body-secondary h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-body-secondary small mb-1">Infomaniak</p>
                        <h2 class="mb-0"><?= number_format($infoActive) ?>
                            <small class="fs-6 text-body-secondary fw-normal">produits</small>
                        </h2>
                        <?php if ($infoExpiring > 0): ?>
                            <small class="text-warning"><?= $infoExpiring ?> expirent bientôt</small>
                        <?php else: ?>
                            <small class="text-body-secondary"><?= number_format($infoStats['total_accounts'] ?? 0) ?> comptes</small>
                        <?php endif; ?>
                    </div>
                    <i class="bi bi-globe2 fs-1 opacity-50" style="color:#9b59b6"></i>
                </div>
            </div>
        </div>
        </a>
    </div>

</div>

<!-- ═══════════════════════════════════════════════════════════════
     Zone 3 — Prochaines expirations + Tags
     ═══════════════════════════════════════════════════════════════ -->
<div class="row g-4 mb-4">

    <!-- Prochaines expirations -->
    <div class="col-lg-<?= !empty($tagStats) ? '6' : '12' ?>">
        <div class="card border-0 bg-body-secondary h-100">
            <div class="card-header bg-transparent border-0 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-calendar-event me-2 text-danger"></i>Prochaines expirations
                    <?php if ($expiringCount > 0): ?>
                        <span class="badge bg-danger fw-normal ms-1"><?= $expiringCount ?></span>
                    <?php endif; ?>
                </h5>
                <a href="/calendar" class="btn btn-sm btn-outline-secondary">Voir tout</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($upcomingExpirations)): ?>
                <p class="text-body-secondary small p-3 mb-0">
                    <i class="bi bi-check-circle text-success me-1"></i>Aucune expiration dans les 30 prochains jours.
                </p>
                <?php else: ?>
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th class="ps-3">Date</th>
                            <th>J−X</th>
                            <th>Provider</th>
                            <th>Client</th>
                            <th class="pe-3">Produit</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($upcomingExpirations as $exp):
                        $expDate  = new DateTime($exp['expiry_date']);
                        $daysLeft = (int)$today->diff($expDate)->days;
                        $urgCls   = $daysLeft <= 7 ? 'bg-danger' : ($daysLeft <= 14 ? 'bg-warning text-dark' : 'bg-success');
                    ?>
                        <tr>
                            <td class="ps-3 text-nowrap small"><?= $expDate->format('d/m/Y') ?></td>
                            <td><span class="badge <?= $urgCls ?>">J−<?= $daysLeft ?></span></td>
                            <td>
                                <span class="badge <?= $providerBadge[$exp['provider']] ?? 'bg-secondary' ?>">
                                    <?= $providerLabel[$exp['provider']] ?? $exp['provider'] ?>
                                </span>
                            </td>
                            <td class="small"><?= htmlspecialchars($exp['client_name'] ?? '—') ?></td>
                            <td class="pe-3 small text-truncate" style="max-width:160px"
                                title="<?= htmlspecialchars($exp['item_name'] ?? '') ?>">
                                <?= htmlspecialchars($exp['item_name'] ?? '—') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

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

</div>

<!-- ═══════════════════════════════════════════════════════════════
     Zone 4 — Détail par provider (2×2)
     ═══════════════════════════════════════════════════════════════ -->
<div class="row g-4 mb-4">

    <!-- ESET -->
    <div class="col-lg-6 col-xl-3">
        <div class="card border-0 bg-body-secondary h-100">
            <div class="card-header bg-transparent border-0 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="bi bi-shield-lock me-2 text-success"></i>ESET</h5>
                <a href="/eset/licenses" class="btn btn-sm btn-outline-secondary">Licences</a>
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-7 fw-normal text-body-secondary">Sociétés</dt>
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
    <div class="col-lg-6 col-xl-3">
        <div class="card border-0 bg-body-secondary h-100">
            <div class="card-header bg-transparent border-0 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="bi bi-cloud-check me-2 text-info"></i>Be-Cloud</h5>
                <a href="/becloud/licenses" class="btn btn-sm btn-outline-secondary">Licences</a>
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-7 fw-normal text-body-secondary">Clients</dt>
                    <dd class="col-5 text-end mb-2"><?= number_format($bcStats['total_customers'] ?? 0) ?></dd>

                    <dt class="col-7 fw-normal text-body-secondary">Abonnements actifs</dt>
                    <dd class="col-5 text-end mb-2"><?= number_format($bcStats['active_subscriptions'] ?? 0) ?></dd>

                    <dt class="col-7 fw-normal text-body-secondary">Licences M365 total</dt>
                    <dd class="col-5 text-end mb-2"><?= number_format($bcLicTotal) ?></dd>

                    <dt class="col-7 fw-normal text-body-secondary">Licences consommées</dt>
                    <dd class="col-5 text-end mb-2"><?= number_format($bcLicConsumed) ?></dd>
                </dl>

                <?php if ($bcLicTotal > 0): ?>
                <div class="mt-2 mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="text-body-secondary">Consommation M365</small>
                        <small><?= $bcLicPct ?>%</small>
                    </div>
                    <div class="progress" style="height:6px">
                        <div class="progress-bar <?= $bcLicPct >= 95 ? 'bg-danger' : ($bcLicPct > 80 ? 'bg-warning' : 'bg-info') ?>"
                             style="width:<?= min(100, $bcLicPct) ?>%"></div>
                    </div>
                </div>
                <?php else: ?>
                <p class="text-body-secondary small mb-3">Aucune licence M365 synchronisée</p>
                <?php endif; ?>

                <?php if (($bcStats['sub_expiring_30d'] ?? 0) > 0): ?>
                <div class="mb-3">
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                        <i class="bi bi-calendar-x me-1"></i><?= $bcStats['sub_expiring_30d'] ?> renouvellement<?= $bcStats['sub_expiring_30d'] > 1 ? 's' : '' ?> dans 30j
                    </span>
                </div>
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
    <div class="col-lg-6 col-xl-3">
        <div class="card border-0 bg-body-secondary h-100">
            <div class="card-header bg-transparent border-0 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="bi bi-hdd-network me-2 text-warning"></i>NinjaOne</h5>
                <a href="/ninjaone/devices" class="btn btn-sm btn-outline-secondary">Équipements</a>
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

                <?php if ($ninjaTotal > 0): ?>
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

    <!-- Infomaniak -->
    <div class="col-lg-6 col-xl-3">
        <div class="card border-0 bg-body-secondary h-100">
            <div class="card-header bg-transparent border-0 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-globe2 me-2" style="color:#9b59b6"></i>Infomaniak
                </h5>
                <a href="/infomaniak/licenses" class="btn btn-sm btn-outline-secondary">Produits</a>
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-7 fw-normal text-body-secondary">Comptes</dt>
                    <dd class="col-5 text-end mb-2"><?= number_format($infoStats['total_accounts'] ?? 0) ?></dd>

                    <dt class="col-7 fw-normal text-body-secondary">Produits total</dt>
                    <dd class="col-5 text-end mb-2"><?= number_format($infoStats['total_products'] ?? 0) ?></dd>

                    <dt class="col-7 fw-normal text-body-secondary">Produits actifs</dt>
                    <dd class="col-5 text-end mb-2"><?= number_format($infoActive) ?></dd>

                    <dt class="col-7 fw-normal text-body-secondary">Expirés</dt>
                    <dd class="col-5 text-end mb-2">
                        <?php if ($infoExpired > 0): ?>
                            <span class="text-danger fw-semibold"><?= number_format($infoExpired) ?></span>
                        <?php else: ?>
                            <span class="text-success">0</span>
                        <?php endif; ?>
                    </dd>
                </dl>

                <div class="d-flex gap-2 flex-wrap mb-3">
                    <?php if ($infoExpired > 0): ?>
                    <span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle">
                        <i class="bi bi-exclamation-circle me-1"></i><?= $infoExpired ?> expiré<?= $infoExpired > 1 ? 's' : '' ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($infoExpiring > 0): ?>
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                        <i class="bi bi-calendar-x me-1"></i><?= $infoExpiring ?> dans 30j
                    </span>
                    <?php endif; ?>
                    <?php if ($infoExpired === 0 && $infoExpiring === 0 && $infoActive > 0): ?>
                    <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">
                        <i class="bi bi-check-circle me-1"></i>Tout est à jour
                    </span>
                    <?php endif; ?>
                    <?php if (($infoStats['total_products'] ?? 0) === 0): ?>
                    <span class="text-body-secondary small">Aucun produit synchronisé</span>
                    <?php endif; ?>
                </div>

                <div class="d-flex gap-2">
                    <a href="/infomaniak/sync-logs" class="btn btn-sm btn-outline-secondary flex-fill">
                        <i class="bi bi-clock-history me-1"></i>Logs
                    </a>
                    <button class="btn btn-sm btn-primary flex-fill" onclick="window.openSyncModal?.(null, 'infomaniak')">
                        <i class="bi bi-arrow-clockwise me-1"></i>Sync
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ═══════════════════════════════════════════════════════════════
     Zone 5 — Connexions fournisseurs
     ═══════════════════════════════════════════════════════════════ -->
<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 bg-body-secondary">
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
                        $syncStatus  = $conn['sync_status'] !== 'idle' ? $conn['sync_status'] : ($conn['sl_status'] ?? 'idle');
                        $statusBadge = match($syncStatus) {
                            'success' => ['bg-success', 'OK'],
                            'error'   => ['bg-danger',  'Erreur'],
                            'running' => ['bg-info',    'En cours'],
                            'partial' => ['bg-warning text-dark', 'Partiel'],
                            default   => ['bg-secondary', 'Inactif'],
                        };
                    ?>
                    <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center py-2">
                        <div>
                            <span class="fw-medium small"><?= htmlspecialchars($conn['provider_name']) ?></span>
                            <span class="text-body-secondary small ms-1">— <?= htmlspecialchars($conn['connection_name']) ?></span>
                            <br><small class="text-body-secondary">
                                <?= $syncAgo ?: 'Jamais synchronisé' ?>
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
