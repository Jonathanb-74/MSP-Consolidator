<?php
/** @var array $logs */
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0">Historique sync ESET</h1>
    <a href="/eset/licenses" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Retour aux licences
    </a>
</div>

<?php if (empty($logs)): ?>
<div class="text-center text-body-secondary py-5">
    <i class="bi bi-clock-history fs-1 d-block mb-2 opacity-25"></i>
    Aucune synchronisation enregistrée.
</div>
<?php else: ?>
<div class="table-responsive">
    <table class="table table-hover table-sm align-middle">
        <thead class="table-dark">
            <tr>
                <th>Démarrage</th>
                <th>Fin</th>
                <th>Durée</th>
                <th class="text-center">Déclencheur</th>
                <th class="text-center">État</th>
                <th class="text-center">Récupérés</th>
                <th class="text-center">Créés</th>
                <th class="text-center">Mis à jour</th>
                <th>Message d'erreur</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log):
                $started  = new DateTime($log['started_at']);
                $finished = $log['finished_at'] ? new DateTime($log['finished_at']) : null;
                $duration = $finished ? $started->diff($finished) : null;
                $durationStr = $duration
                    ? ($duration->h > 0 ? $duration->h . 'h ' : '') . $duration->i . 'min ' . $duration->s . 's'
                    : '—';

                $statusClasses = [
                    'success' => 'bg-success',
                    'partial' => 'bg-warning text-dark',
                    'error'   => 'bg-danger',
                    'running' => 'bg-info',
                ];
                $statusClass = $statusClasses[$log['status']] ?? 'bg-secondary';
            ?>
            <tr>
                <td class="small"><?= $started->format('d/m/Y H:i:s') ?></td>
                <td class="small"><?= $finished ? $finished->format('d/m/Y H:i:s') : '<em class="text-body-secondary">en cours</em>' ?></td>
                <td class="small"><?= $durationStr ?></td>
                <td class="text-center">
                    <span class="badge bg-secondary"><?= htmlspecialchars($log['triggered_by']) ?></span>
                </td>
                <td class="text-center">
                    <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($log['status']) ?></span>
                </td>
                <td class="text-center"><?= number_format((int)$log['records_fetched']) ?></td>
                <td class="text-center text-success"><?= number_format((int)$log['records_created']) ?></td>
                <td class="text-center text-info"><?= number_format((int)$log['records_updated']) ?></td>
                <td class="small text-danger">
                    <?php if ($log['error_message']): ?>
                    <span title="<?= htmlspecialchars($log['error_message']) ?>">
                        <?= htmlspecialchars(mb_strimwidth($log['error_message'], 0, 100, '…')) ?>
                    </span>
                    <?php else: ?>—<?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
