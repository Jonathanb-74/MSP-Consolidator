<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 10pt;
        color: #1a1a2e;
        background: #ffffff;
    }

    /* ── En-tête document ── */
    .doc-header {
        background: #1a1a2e;
        color: #ffffff;
        padding: 18px 24px;
        margin-bottom: 20px;
    }
    .doc-header-inner {
        display: table;
        width: 100%;
    }
    .doc-header-left {
        display: table-cell;
        vertical-align: middle;
    }
    .doc-header-right {
        display: table-cell;
        text-align: right;
        vertical-align: middle;
    }
    .doc-title {
        font-size: 16pt;
        font-weight: bold;
        letter-spacing: 1px;
        text-transform: uppercase;
    }
    .doc-subtitle {
        font-size: 9pt;
        color: #a0aec0;
        margin-top: 3px;
    }
    .doc-date {
        font-size: 9pt;
        color: #a0aec0;
    }
    .doc-date strong {
        color: #ffffff;
        display: block;
        font-size: 11pt;
    }

    /* ── Bloc client ── */
    .client-block {
        border: 1px solid #e2e8f0;
        border-left: 4px solid #3b82f6;
        border-radius: 4px;
        padding: 14px 18px;
        margin: 0 0 20px 0;
        background: #f8fafc;
    }
    .client-block-title {
        font-size: 8pt;
        text-transform: uppercase;
        color: #64748b;
        letter-spacing: .5px;
        margin-bottom: 8px;
        font-weight: bold;
    }
    .client-name {
        font-size: 14pt;
        font-weight: bold;
        color: #1a1a2e;
        margin-bottom: 6px;
    }
    .client-meta {
        display: table;
        width: 100%;
    }
    .client-meta-col {
        display: table-cell;
        vertical-align: top;
        width: 50%;
        font-size: 9pt;
        color: #475569;
        line-height: 1.7;
    }
    .client-number {
        display: inline-block;
        background: #dbeafe;
        color: #1d4ed8;
        padding: 1px 7px;
        border-radius: 10px;
        font-size: 8pt;
        font-weight: bold;
        margin-bottom: 6px;
    }
    .tag-pill {
        display: inline-block;
        padding: 1px 8px;
        border-radius: 10px;
        font-size: 8pt;
        color: #ffffff;
        margin-right: 3px;
    }

    /* ── Sections fournisseurs ── */
    .section {
        margin-bottom: 18px;
    }
    .section-header {
        display: table;
        width: 100%;
        background: #334155;
        color: #ffffff;
        padding: 8px 14px;
        border-radius: 4px 4px 0 0;
    }
    .section-header-left {
        display: table-cell;
        vertical-align: middle;
        font-size: 11pt;
        font-weight: bold;
        letter-spacing: .3px;
    }
    .section-header-right {
        display: table-cell;
        text-align: right;
        vertical-align: middle;
        font-size: 8pt;
        color: #94a3b8;
    }

    /* ── Tableaux ── */
    table.data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 9pt;
        border: 1px solid #e2e8f0;
        border-top: none;
    }
    table.data-table thead th {
        background: #f1f5f9;
        color: #475569;
        padding: 6px 10px;
        text-align: left;
        font-size: 8pt;
        text-transform: uppercase;
        letter-spacing: .3px;
        border-bottom: 1px solid #cbd5e1;
    }
    table.data-table thead th.text-center { text-align: center; }
    table.data-table thead th.text-right  { text-align: right;  }
    table.data-table tbody td {
        padding: 7px 10px;
        border-bottom: 1px solid #f1f5f9;
        color: #1e293b;
        line-height: 1.4;
    }
    table.data-table tbody td.text-center { text-align: center; }
    table.data-table tbody td.text-right  { text-align: right;  }
    table.data-table tbody tr:last-child td { border-bottom: none; }
    table.data-table tbody tr.row-danger td { background: #fef2f2; }
    table.data-table tbody tr.row-success td { background: #f0fdf4; }

    /* Badges état */
    .badge {
        display: inline-block;
        padding: 1px 7px;
        border-radius: 10px;
        font-size: 7.5pt;
        font-weight: bold;
    }
    .badge-success { background: #dcfce7; color: #166534; }
    .badge-danger  { background: #fee2e2; color: #991b1b; }
    .badge-warning { background: #fef9c3; color: #854d0e; }
    .badge-info    { background: #e0f2fe; color: #075985; }
    .badge-grey    { background: #f1f5f9; color: #475569; }

    /* Barres progress */
    .progress-wrap {
        display: inline-block;
        width: 60px;
        height: 6px;
        background: #e2e8f0;
        border-radius: 3px;
        vertical-align: middle;
        margin-right: 5px;
        overflow: hidden;
    }
    .progress-bar {
        height: 100%;
        border-radius: 3px;
    }
    .bar-primary { background: #3b82f6; }
    .bar-success { background: #22c55e; }
    .bar-danger  { background: #ef4444; }

    /* Valeurs numériques */
    .val-over    { color: #dc2626; font-weight: bold; }
    .val-full    { color: #16a34a; font-weight: bold; }
    .val-normal  { color: #475569; }

    /* Message vide */
    .empty-msg {
        border: 1px solid #e2e8f0;
        border-top: none;
        padding: 14px;
        text-align: center;
        color: #94a3b8;
        font-size: 9pt;
        font-style: italic;
    }

    /* ── Pied de page ── */
    .doc-footer {
        margin-top: 24px;
        padding-top: 10px;
        border-top: 1px solid #e2e8f0;
        color: #94a3b8;
        font-size: 8pt;
        text-align: center;
    }
</style>
</head>
<body>

<?php
$now      = new \DateTime();
$dateStr  = $now->format('d/m/Y à H:i');
?>

<!-- ══ En-tête ══ -->
<div class="doc-header">
    <div class="doc-header-inner">
        <div class="doc-header-left">
            <div class="doc-title">Rapport de Licences</div>
            <div class="doc-subtitle">MSP Consolidator — Synthèse par client</div>
        </div>
        <div class="doc-header-right">
            <div class="doc-date">
                Généré le
                <strong><?= htmlspecialchars($dateStr) ?></strong>
            </div>
        </div>
    </div>
</div>

<!-- ══ Bloc client ══ -->
<div class="client-block">
    <div class="client-block-title">Informations client</div>
    <div class="client-name"><?= htmlspecialchars($client['name']) ?></div>

    <?php if ($client['client_number']): ?>
    <span class="client-number"><?= htmlspecialchars($client['client_number']) ?></span>
    <?php endif; ?>

    <?php if (!empty($tags)): ?>
    <div style="margin-bottom:8px">
        <?php foreach ($tags as $tag): ?>
        <span class="tag-pill" style="background-color:<?= htmlspecialchars($tag['color']) ?>">
            <?= htmlspecialchars($tag['name']) ?>
        </span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="client-meta">
        <div class="client-meta-col">
            <?php if ($client['email']): ?>
            <div><strong>E-mail :</strong> <?= htmlspecialchars($client['email']) ?></div>
            <?php endif; ?>
            <?php if ($client['phone']): ?>
            <div><strong>Téléphone :</strong> <?= htmlspecialchars($client['phone']) ?></div>
            <?php endif; ?>
        </div>
        <div class="client-meta-col">
            <?php if ($client['address']): ?>
            <div><strong>Adresse :</strong> <?= nl2br(htmlspecialchars($client['address'])) ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ══ ESET ══ -->
<div class="section">
    <div class="section-header">
        <div class="section-header-left">Protection des postes — ESET</div>
        <div class="section-header-right">
            <?php if (!empty($esetDetail)): ?>
            <?php
                $totalEsetLic   = array_sum(array_column($esetDetail, 'lic_count'));
                $totalEsetSeats = array_sum(array_column($esetDetail, 'seats_total'));
                $totalEsetUsed  = array_sum(array_column($esetDetail, 'seats_used'));
            ?>
            <?= $totalEsetLic ?> licence(s) &nbsp;·&nbsp; <?= $totalEsetUsed ?>/<?= $totalEsetSeats ?> postes
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($esetDetail)): ?>
    <div class="empty-msg">Aucune licence ESET synchronisée pour ce client.</div>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Produit</th>
                <th class="text-center">Commandés</th>
                <th class="text-center">Utilisés</th>
                <th class="text-center">Libres</th>
                <th class="text-center">État</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($esetDetail as $row):
            $total  = (int)$row['seats_total'];
            $used   = (int)$row['seats_used'];
            $free   = $total - $used;
            $over   = $used > $total;
            $stateRaw = $row['state'] ?? '';
            // LicenseStates ESET : 0=Error, 1=Normal, 2=Obsolete, 3=Suspended, 4=Warning
            $stateLabel = match((string)$stateRaw) {
                '1', 'VALID'     => ['Active',    'badge-success'],
                '4'              => ['Complet',    'badge-success'],
                '3', 'SUSPENDED' => ['Suspendue',  'badge-danger'],
                '2'              => ['Obsolète',   'badge-danger'],
                '0'              => ['Attention requise', 'badge-warning'],
                default          => [htmlspecialchars($stateRaw), 'badge-grey'],
            };
        ?>
        <tr>
            <td>
                <strong><?= htmlspecialchars($row['product_name']) ?></strong>
                <?php if (!empty($row['license_keys'])): ?>
                <div style="font-size:7.5pt;color:#94a3b8;margin-top:2px"><?= htmlspecialchars($row['license_keys']) ?></div>
                <?php endif; ?>
            </td>
            <td class="text-center"><?= $total ?></td>
            <td class="text-center <?= $over ? 'val-over' : '' ?>"><?= $used ?></td>
            <td class="text-center <?= $over ? 'val-over' : 'val-normal' ?>"><?= $free ?></td>
            <td class="text-center">
                <span class="badge <?= $stateLabel[1] ?>"><?= $stateLabel[0] ?></span>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- ══ Be-Cloud ══ -->
<div class="section">
    <div class="section-header">
        <div class="section-header-left">Cloud & Microsoft 365 — Be-Cloud</div>
        <div class="section-header-right">
            <?php
            $bcLicCount = count($bcLicDetail ?? []);
            $bcSubCount = count($bcDetail ?? []);
            $parts = [];
            if ($bcLicCount) $parts[] = $bcLicCount . ' licence(s) M365';
            if ($bcSubCount) $parts[] = $bcSubCount . ' abonnement(s)';
            echo implode(' &nbsp;·&nbsp; ', $parts);
            ?>
        </div>
    </div>

    <?php if (empty($bcLicDetail) && empty($bcDetail)): ?>
    <div class="empty-msg">Aucune donnée Be-Cloud synchronisée pour ce client.</div>
    <?php else: ?>

    <?php if (!empty($bcLicDetail)): ?>
    <!-- Licences M365 -->
    <div style="background:#f1f5f9;padding:5px 10px;font-size:8pt;font-weight:bold;text-transform:uppercase;letter-spacing:.3px;color:#475569;border:1px solid #e2e8f0;border-top:none">
        Licences M365
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Licence</th>
                <th class="text-center">Total</th>
                <th class="text-center">Consommées</th>
                <th class="text-center">Disponibles</th>
                <th class="text-center">Suspendues</th>
                <th class="text-center">Usage</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($bcLicDetail as $row):
            $total_l   = (int)$row['total_licenses'];
            $consumed  = (int)$row['consumed_licenses'];
            $available = (int)$row['available_licenses'];
            $suspended = (int)$row['suspended_licenses'];
            $pct       = $total_l > 0 ? min(100, (int)round($consumed / $total_l * 100)) : 0;
            $barColor  = $pct >= 90 ? '#ef4444' : ($pct >= 70 ? '#f59e0b' : '#22c55e');
        ?>
        <tr <?= $pct >= 90 ? 'class="row-danger"' : '' ?>>
            <td>
                <strong><?= htmlspecialchars($row['name'] ?: $row['sku_id']) ?></strong>
                <div style="font-size:7.5pt;color:#94a3b8"><?= htmlspecialchars($row['sku_id']) ?></div>
            </td>
            <td class="text-center"><?= $total_l ?></td>
            <td class="text-center <?= $pct >= 90 ? 'val-over' : '' ?>"><?= $consumed ?></td>
            <td class="text-center <?= $available === 0 && $total_l > 0 ? 'val-over' : '' ?>"><?= $available ?></td>
            <td class="text-center <?= $suspended > 0 ? 'val-over' : '' ?>"><?= $suspended > 0 ? $suspended : '—' ?></td>
            <td class="text-center">
                <?php if ($total_l > 0): ?>
                <div class="progress-wrap">
                    <div class="progress-bar" style="background:<?= $barColor ?>;width:<?= $pct ?>%"></div>
                </div>
                <?= $pct ?>%
                <?php else: ?>—<?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if (!empty($bcDetail)): ?>
    <!-- Abonnements -->
    <div style="background:#f1f5f9;padding:5px 10px;font-size:8pt;font-weight:bold;text-transform:uppercase;letter-spacing:.3px;color:#475569;border:1px solid #e2e8f0;border-top:none;margin-top:10px">
        Abonnements
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Offre</th>
                <th class="text-center">Statut</th>
                <th class="text-center">Qté</th>
                <th>Début</th>
                <th>Fin</th>
                <th>Fréquence</th>
                <th class="text-right">Prix unit.</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $today    = new \DateTime();
        $in30Days = new \DateTime('+30 days');
        foreach ($bcDetail as $row):
            $endDate      = !empty($row['end_date']) ? new \DateTime($row['end_date']) : null;
            $expiringSoon = $endDate && $endDate >= $today && $endDate <= $in30Days;
            $statusVal    = $row['status'] ?? '';

            if ($expiringSoon) {
                $sBadge = ['Expire bientôt', 'badge-warning'];
            } elseif ($statusVal === 'Active') {
                $sBadge = ['Actif', 'badge-success'];
            } elseif ($statusVal === 'Suspended') {
                $sBadge = ['Suspendu', 'badge-warning'];
            } elseif (in_array($statusVal, ['Deleted', 'Expired']) || ($endDate && $endDate < $today)) {
                $sBadge = [$statusVal ?: 'Expiré', 'badge-danger'];
            } else {
                $sBadge = [$statusVal ?: '?', 'badge-grey'];
            }

            $price    = $row['list_price'] ?? null;
            $currency = $row['currency'] ?? '';
            $priceStr = ($price !== null && $price !== '') ? number_format((float)$price, 2) . ' ' . htmlspecialchars($currency) : '—';
        ?>
        <tr>
            <td>
                <strong><?= htmlspecialchars($row['offer_name'] ?? '—') ?></strong>
                <?php if (!empty($row['is_trial'])): ?>
                    <span class="badge badge-info" style="margin-left:4px">Trial</span>
                <?php endif; ?>
                <?php if (!empty($row['auto_renewal'])): ?>
                    <span class="badge badge-grey" style="margin-left:2px">Auto</span>
                <?php endif; ?>
            </td>
            <td class="text-center"><span class="badge <?= $sBadge[1] ?>"><?= htmlspecialchars($sBadge[0]) ?></span></td>
            <td class="text-center"><?= (int)($row['quantity'] ?? 0) ?></td>
            <td><?= !empty($row['start_date']) ? date('d/m/Y', strtotime($row['start_date'])) : '—' ?></td>
            <td class="<?= $expiringSoon ? 'val-over' : ($endDate && $endDate < $today ? 'val-over' : '') ?>">
                <?= $endDate ? date('d/m/Y', $endDate->getTimestamp()) : '—' ?>
            </td>
            <td style="color:#64748b">
                <?= htmlspecialchars(implode(' / ', array_filter([$row['billing_frequency'] ?? '', $row['term_duration'] ?? ''])) ?: '—') ?>
            </td>
            <td class="text-right"><?= $priceStr ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php endif; ?>
</div>

<!-- ══ NinjaOne ══ -->
<div class="section">
    <div class="section-header">
        <div class="section-header-left">Supervision & RMM — NinjaOne</div>
        <div class="section-header-right">
            <?php if (!empty($ninjaDetail)): ?>
            <?php
                $totalRmm = array_sum(array_column($ninjaDetail, 'rmm_count'));
                $totalNms = array_sum(array_column($ninjaDetail, 'nms_count'));
                $totalMdm = array_sum(array_column($ninjaDetail, 'mdm_count'));
            ?>
            RMM : <?= $totalRmm ?> &nbsp;·&nbsp; NMS : <?= $totalNms ?> &nbsp;·&nbsp; MDM : <?= $totalMdm ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($ninjaDetail)): ?>
    <div class="empty-msg">Aucune organisation NinjaOne synchronisée pour ce client.</div>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Organisation</th>
                <th class="text-center">RMM</th>
                <th class="text-center">NMS</th>
                <th class="text-center">MDM</th>
                <th class="text-center">VMM*</th>
                <th class="text-center">Cloud*</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($ninjaDetail as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td class="text-center"><?= (int)$row['rmm_count'] ?: '—' ?></td>
            <td class="text-center"><?= (int)$row['nms_count'] ?: '—' ?></td>
            <td class="text-center"><?= (int)$row['mdm_count'] ?: '—' ?></td>
            <td class="text-center" style="color:#94a3b8"><?= (int)$row['vmm_count']   ?: '—' ?></td>
            <td class="text-center" style="color:#94a3b8"><?= (int)$row['cloud_count'] ?: '—' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div style="font-size:7.5pt;color:#94a3b8;padding:6px 10px;border:1px solid #e2e8f0;border-top:none">
        * VMM et Cloud Monitoring ne font pas l'objet d'une licence NinjaOne.
    </div>
    <?php endif; ?>
</div>

<!-- ══ Infomaniak ══ -->
<div class="section">
    <div class="section-header">
        <div class="section-header-left">Hébergement & Services — Infomaniak</div>
        <div class="section-header-right">
            <?php if (!empty($infomaniakDetail)): ?>
            <?= count($infomaniakDetail) ?> produit(s)
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($infomaniakDetail)): ?>
    <div class="empty-msg">Aucun produit Infomaniak synchronisé pour ce client.</div>
    <?php else: ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Compte</th>
                <th>Service</th>
                <th>Produit / Nom interne</th>
                <th>Nom client produit</th>
                <th class="text-center">Expiration</th>
                <th class="text-center">Statut</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($infomaniakDetail as $row):
            $expiredAt  = $row['expired_at'] ? (int)$row['expired_at'] : null;
            $isExpired  = $expiredAt && $expiredAt < time();
            $isSoon     = $expiredAt && !$isExpired && $expiredAt < (time() + 30 * 86400);
        ?>
        <tr <?= $isExpired ? 'class="row-danger"' : '' ?>>
            <td><?= htmlspecialchars($row['account_name']) ?></td>
            <td><?= htmlspecialchars($row['service_name'] ?? '—') ?></td>
            <td><?= htmlspecialchars($row['internal_name'] ?? '—') ?></td>
            <td style="color:#64748b"><?= htmlspecialchars($row['customer_name'] ?? '—') ?></td>
            <td class="text-center">
                <?php if ($expiredAt): ?>
                    <span class="badge <?= $isExpired ? 'badge-danger' : ($isSoon ? 'badge-warning' : 'badge-grey') ?>">
                        <?= date('d/m/Y', $expiredAt) ?>
                    </span>
                <?php else: ?>
                    —
                <?php endif; ?>
            </td>
            <td class="text-center">
                <?php if ($isExpired): ?>
                    <span class="badge badge-danger">Expiré</span>
                <?php elseif ($isSoon): ?>
                    <span class="badge badge-warning">Bientôt</span>
                <?php else: ?>
                    <span class="badge badge-success">Actif</span>
                <?php endif; ?>
                <?php if ($row['is_trial']): ?>
                    <span class="badge badge-info">Essai</span>
                <?php endif; ?>
                <?php if ($row['is_free']): ?>
                    <span class="badge badge-grey">Gratuit</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- ══ Pied de page ══ -->
<div class="doc-footer">
    Document généré le <?= htmlspecialchars($dateStr) ?> via MSP Consolidator &nbsp;·&nbsp;
    <?= htmlspecialchars($client['name']) ?>
    <?php if ($client['client_number']): ?>(<?= htmlspecialchars($client['client_number']) ?>)<?php endif; ?>
</div>

</body>
</html>
