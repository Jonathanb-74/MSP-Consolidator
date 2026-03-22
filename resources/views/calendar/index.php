<?php
/** @var array  $events */
/** @var array  $byDate */
/** @var array  $grid */
/** @var array  $clientsMap */
/** @var int    $month */
/** @var int    $year */
/** @var int    $prevMonth */
/** @var int    $prevYear */
/** @var int    $nextMonth */
/** @var int    $nextYear */
/** @var string $mode */
/** @var array  $filters   ['provider','client_id','horizon','mode'] */
/** @var array  $monthNames */
/** @var string $todayStr */

$providerLabel = ['eset' => 'ESET', 'becloud' => 'Be-Cloud', 'infomaniak' => 'Infomaniak'];
$providerBadge = ['eset' => 'bg-primary', 'becloud' => 'bg-success', 'infomaniak' => 'bg-warning text-dark'];

// Group events by YYYY-MM for list view
$byMonth = [];
foreach ($events as $ev) {
    $byMonth[substr($ev['expiry_date'], 0, 7)][] = $ev;
}

// URL builder — preserves all current filters, allows overrides
$buildUrl = function (array $override = []) use ($filters, $month, $year): string {
    $p = array_merge([
        'provider'  => $filters['provider'],
        'client_id' => $filters['client_id'],
        'horizon'   => $filters['horizon'],
        'mode'      => $filters['mode'],
        'month'     => $month,
        'year'      => $year,
    ], $override);
    if ($p['provider']  === 'all') unset($p['provider']);
    if ((int)($p['client_id'] ?? 0) === 0) unset($p['client_id']);
    if (($p['mode'] ?? '') === 'calendar') unset($p['mode']);
    return '/calendar?' . http_build_query($p);
};
?>

<?php
// Pre-compute events JSON for modal (with days remaining + link)
$todayDt    = new DateTime($todayStr);
$eventsJson = [];
foreach ($byDate as $date => $evs) {
    $evDate   = new DateTime($date);
    $daysLeft = (int)$todayDt->diff($evDate)->days;
    $expired  = ($evDate < $todayDt);
    foreach ($evs as $ev) {
        $link = match($ev['provider']) {
            'becloud'    => '/becloud/client/' . (int)$ev['item_id'],
            'infomaniak' => '/infomaniak/client/' . (int)$ev['item_id'],
            default      => '/eset/licenses',
        };
        $eventsJson[$date][] = [
            'provider'    => $ev['provider'],
            'pLabel'      => $providerLabel[$ev['provider']],
            'pBadge'      => $providerBadge[$ev['provider']],
            'client'      => $ev['client_name'],
            'provClient'  => $ev['provider_client_name'],
            'item'        => $ev['item_name'] ?? '—',
            'daysLeft'    => $daysLeft,
            'expired'     => $expired,
            'link'        => $link,
        ];
    }
}
?>
<style>
/* ── Calendar grid ─────────────────────────────────────── */
.cal-grid { table-layout: fixed; }
.cal-grid th {
    text-align: center;
    font-size: .75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
    padding: 6px 4px;
    background: var(--bs-light);
}
.cal-cell {
    vertical-align: top;
    height: 90px;
    padding: 4px 5px;
    overflow: hidden;
}
.cal-outside { background: var(--bs-light); opacity: .45; }
.cal-today   { background: rgba(var(--bs-primary-rgb), .05); }
.cal-day-num {
    font-size: .75rem;
    font-weight: 600;
    color: var(--bs-secondary);
    margin-bottom: 3px;
    line-height: 1;
}
.cal-today-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px; height: 20px;
    border-radius: 50%;
    background: var(--bs-primary);
    color: #fff;
    font-weight: 700;
}
.cal-badge {
    font-size: .62rem;
    margin: 1px 0;
    display: block;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
    cursor: pointer;
    transition: filter .1s;
}
.cal-badge:hover { filter: brightness(.88); }
/* ── Responsive calendar on small screens ──────────────── */
@media (max-width: 767px) {
    .cal-cell { height: 60px; }
    .cal-badge { display: none; }
    .cal-cell-dot {
        display: inline-block;
        width: 6px; height: 6px;
        border-radius: 50%;
        margin: 1px;
        cursor: pointer;
    }
}
@media (min-width: 768px) {
    .cal-cell-dot { display: none; }
}
</style>

<!-- ── Page header ─────────────────────────────────────── -->
<div class="d-flex align-items-start justify-content-between mb-3">
    <div>
        <h4 class="mb-0"><i class="bi bi-calendar3 me-2"></i><?= htmlspecialchars($pageTitle) ?></h4>
        <small class="text-muted">
            <?= count($events) ?> expiration<?= count($events) > 1 ? 's' : '' ?>
            <?php if ($filters['horizon'] !== 'all'): ?>
                dans les <?= $filters['horizon'] ?> prochains jours
            <?php endif; ?>
        </small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $buildUrl(['mode' => 'calendar']) ?>"
           class="btn btn-sm <?= $mode === 'calendar' ? 'btn-primary' : 'btn-outline-secondary' ?>">
            <i class="bi bi-calendar3 me-1"></i>Calendrier
        </a>
        <a href="<?= $buildUrl(['mode' => 'list']) ?>"
           class="btn btn-sm <?= $mode === 'list' ? 'btn-primary' : 'btn-outline-secondary' ?>">
            <i class="bi bi-list-ul me-1"></i>Liste
        </a>
    </div>
</div>

<!-- ── Filter bar ──────────────────────────────────────── -->
<form method="get" action="/calendar" class="card card-body py-2 mb-3">
    <input type="hidden" name="month" value="<?= $month ?>">
    <input type="hidden" name="year"  value="<?= $year ?>">
    <input type="hidden" name="mode"  value="<?= htmlspecialchars($filters['mode']) ?>">
    <div class="row g-2 align-items-end">
        <div class="col-auto">
            <label class="form-label form-label-sm mb-1">Fournisseur</label>
            <select name="provider" class="form-select form-select-sm">
                <option value="all"        <?= $filters['provider'] === 'all'        ? 'selected' : '' ?>>Tous les fournisseurs</option>
                <option value="eset"       <?= $filters['provider'] === 'eset'       ? 'selected' : '' ?>>ESET</option>
                <option value="becloud"    <?= $filters['provider'] === 'becloud'    ? 'selected' : '' ?>>Be-Cloud</option>
                <option value="infomaniak" <?= $filters['provider'] === 'infomaniak' ? 'selected' : '' ?>>Infomaniak</option>
            </select>
        </div>
        <?php if (!empty($clientsMap)): ?>
        <div class="col-auto">
            <label class="form-label form-label-sm mb-1">Client</label>
            <select name="client_id" class="form-select form-select-sm" style="max-width:200px">
                <option value="0">Tous les clients</option>
                <?php foreach ($clientsMap as $cId => $cName): ?>
                    <option value="<?= $cId ?>" <?= (int)$filters['client_id'] === (int)$cId ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cName) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="col-auto">
            <label class="form-label form-label-sm mb-1">Horizon</label>
            <select name="horizon" class="form-select form-select-sm">
                <option value="30"  <?= $filters['horizon'] === '30'  ? 'selected' : '' ?>>30 jours</option>
                <option value="60"  <?= $filters['horizon'] === '60'  ? 'selected' : '' ?>>60 jours</option>
                <option value="90"  <?= $filters['horizon'] === '90'  ? 'selected' : '' ?>>90 jours</option>
                <option value="all" <?= $filters['horizon'] === 'all' ? 'selected' : '' ?>>Tout</option>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-funnel me-1"></i>Appliquer
            </button>
        </div>
        <?php if ($filters['provider'] !== 'all' || (int)$filters['client_id'] > 0 || $filters['horizon'] !== '90'): ?>
        <div class="col-auto">
            <a href="/calendar?mode=<?= htmlspecialchars($filters['mode']) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-x me-1"></i>Réinitialiser
            </a>
        </div>
        <?php endif; ?>
    </div>
</form>

<?php if (empty($events)): ?>
    <div class="text-center text-muted py-5">
        <i class="bi bi-calendar-x fs-1 d-block mb-3 opacity-25"></i>
        <p class="mb-0">Aucune expiration trouvée pour les filtres sélectionnés.</p>
    </div>

<?php elseif ($mode === 'calendar'): ?>

    <!-- ── Calendar view ───────────────────────────────── -->
    <?php
    $currentMonthInt = (int)date('n');
    $currentYearInt  = (int)date('Y');
    $isCurrentMonth  = ($month === $currentMonthInt && $year === $currentYearInt);
    ?>
    <div class="d-flex align-items-center justify-content-between mb-2">
        <a href="<?= $buildUrl(['month' => $prevMonth, 'year' => $prevYear]) ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-chevron-left"></i> <?= $monthNames[$prevMonth] ?>
        </a>
        <div class="d-flex align-items-center gap-2">
            <h5 class="mb-0 fw-semibold"><?= $monthNames[$month] ?> <?= $year ?></h5>
            <?php if (!$isCurrentMonth): ?>
                <a href="<?= $buildUrl(['month' => $currentMonthInt, 'year' => $currentYearInt]) ?>"
                   class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-calendar-check me-1"></i>Aujourd'hui
                </a>
            <?php endif; ?>
        </div>
        <a href="<?= $buildUrl(['month' => $nextMonth, 'year' => $nextYear]) ?>"
           class="btn btn-outline-secondary btn-sm">
            <?= $monthNames[$nextMonth] ?> <i class="bi bi-chevron-right"></i>
        </a>
    </div>

    <div class="table-responsive">
    <table class="table table-bordered cal-grid mb-0">
        <thead>
            <tr>
                <?php foreach (['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'] as $d): ?>
                    <th><?= $d ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($grid as $gridRow): ?>
            <tr>
            <?php foreach ($gridRow as $cell): ?>
                <?php if ($cell === null): ?>
                    <td class="cal-outside"></td>
                <?php else: ?>
                    <?php
                    $isToday  = ($cell['date'] === $todayStr);
                    $cellEvs  = $cell['events'];
                    $visible  = array_slice($cellEvs, 0, 3);
                    $extraCnt = count($cellEvs) - 3;
                    ?>
                    <td class="cal-cell <?= $isToday ? 'cal-today' : '' ?>">
                        <div class="cal-day-num <?= $isToday ? 'cal-today-num' : '' ?>"><?= $cell['day'] ?></div>
                        <?php foreach ($visible as $ev): ?>
                            <?php $label = $ev['client_name'] ?: $ev['provider_client_name']; ?>
                            <span class="badge <?= $providerBadge[$ev['provider']] ?> cal-badge"
                                  data-cal-date="<?= $cell['date'] ?>">
                                <?= htmlspecialchars(mb_strimwidth($label, 0, 22, '…')) ?>
                            </span>
                            <span class="cal-cell-dot badge <?= $providerBadge[$ev['provider']] ?>"
                                  data-cal-date="<?= $cell['date'] ?>"></span>
                        <?php endforeach; ?>
                        <?php if ($extraCnt > 0): ?>
                            <span class="badge bg-secondary cal-badge"
                                  data-cal-date="<?= $cell['date'] ?>">
                                +<?= $extraCnt ?> autres
                            </span>
                        <?php endif; ?>
                    </td>
                <?php endif; ?>
            <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <!-- ── Legend ──────────────────────────────────────── -->
    <div class="d-flex gap-3 mt-2 flex-wrap">
        <?php foreach ($providerLabel as $key => $label): ?>
            <span class="d-flex align-items-center gap-1 small text-muted">
                <span class="badge <?= $providerBadge[$key] ?>">&nbsp;</span> <?= $label ?>
            </span>
        <?php endforeach; ?>
    </div>

<?php else: ?>

    <!-- ── List view ───────────────────────────────────── -->
    <?php $today = new DateTime($todayStr); ?>
    <?php foreach ($byMonth as $monthKey => $monthEvs): ?>
        <?php [$my, $mm] = explode('-', $monthKey); ?>
        <h6 class="mt-4 mb-2 fw-bold text-secondary border-bottom pb-1 d-flex align-items-center gap-2">
            <i class="bi bi-calendar2-month"></i>
            <?= $monthNames[(int)$mm] ?> <?= $my ?>
            <span class="badge bg-secondary fw-normal"><?= count($monthEvs) ?></span>
        </h6>
        <div class="table-responsive">
        <table class="table table-hover table-sm align-middle">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>J−X</th>
                    <th>Fournisseur</th>
                    <th>Client</th>
                    <th>Produit / Abonnement</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($monthEvs as $ev): ?>
                <?php
                $evDate   = new DateTime($ev['expiry_date']);
                $daysLeft = (int)$today->diff($evDate)->days;
                $expired  = ($evDate < $today);
                $urgClass = $expired      ? 'bg-danger'  :
                           ($daysLeft <= 7  ? 'bg-danger'  :
                           ($daysLeft <= 30 ? 'bg-warning text-dark' : 'bg-success'));
                $rowClass = $expired ? 'table-danger opacity-75' : '';
                $link     = match($ev['provider']) {
                    'becloud'    => '/becloud/client/' . (int)$ev['item_id'],
                    'infomaniak' => '/infomaniak/client/' . (int)$ev['item_id'],
                    default      => '/eset/licenses',
                };
                $clientDisplay = $ev['client_name']
                    ? htmlspecialchars($ev['client_name'])
                    : '<span class="text-muted fst-italic">' . htmlspecialchars($ev['provider_client_name']) . '</span>';
                ?>
                <tr class="<?= $rowClass ?>">
                    <td class="text-nowrap"><?= $evDate->format('d/m/Y') ?></td>
                    <td>
                        <?php if ($expired): ?>
                            <span class="badge bg-danger">Expiré</span>
                        <?php else: ?>
                            <span class="badge <?= $urgClass ?>">J−<?= $daysLeft ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= $providerBadge[$ev['provider']] ?>">
                            <?= $providerLabel[$ev['provider']] ?>
                        </span>
                    </td>
                    <td><?= $clientDisplay ?></td>
                    <td><?= htmlspecialchars($ev['item_name'] ?? '—') ?></td>
                    <td>
                        <a href="<?= $link ?>" class="btn btn-outline-secondary btn-sm py-0 px-2"
                           title="Voir le détail">
                            <i class="bi bi-arrow-right-short"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endforeach; ?>

<?php endif; ?>

<!-- ── Event detail modal ──────────────────────────────── -->
<div class="modal fade" id="calModal" tabindex="-1" aria-labelledby="calModalTitle">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="calModalTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" id="calModalBody"></div>
        </div>
    </div>
</div>

<script>
(function () {
    // Events data keyed by date
    const calEvents = <?= json_encode($eventsJson, JSON_HEX_TAG | JSON_HEX_APOS) ?>;

    const monthNames = <?= json_encode(array_values($monthNames)) ?>;

    function urgBadge(daysLeft, expired) {
        if (expired)        return '<span class="badge bg-danger">Expiré</span>';
        if (daysLeft <= 7)  return '<span class="badge bg-danger">J−' + daysLeft + '</span>';
        if (daysLeft <= 30) return '<span class="badge bg-warning text-dark">J−' + daysLeft + '</span>';
        return '<span class="badge bg-success">J−' + daysLeft + '</span>';
    }

    function openModal(date) {
        const evs = calEvents[date];
        if (!evs || !evs.length) return;

        // Format date as "Lundi 15 mars 2026"
        const d     = new Date(date + 'T00:00:00');
        const days  = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
        const mNames = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
        const title = days[d.getDay()] + ' ' + d.getDate() + ' ' + mNames[d.getMonth()] + ' ' + d.getFullYear();

        document.getElementById('calModalTitle').innerHTML =
            '<i class="bi bi-calendar3 me-2 text-secondary"></i>' + title +
            ' <span class="badge bg-secondary fw-normal ms-1">' + evs.length + '</span>';

        let html = '';
        evs.forEach(function (ev) {
            const clientLine = ev.client
                ? '<span class="fw-semibold">' + escHtml(ev.client) + '</span>'
                  + (ev.provClient && ev.provClient !== ev.client
                      ? ' <span class="text-muted small">(' + escHtml(ev.provClient) + ')</span>'
                      : '')
                : '<span class="fst-italic text-muted">' + escHtml(ev.provClient) + '</span>';

            html += '<div class="d-flex align-items-start gap-3 px-3 py-2 border-bottom">'
                  +   '<span class="badge ' + ev.pBadge + ' mt-1 text-nowrap">' + escHtml(ev.pLabel) + '</span>'
                  +   '<div class="flex-grow-1 min-w-0">'
                  +     '<div class="fw-semibold text-truncate" title="' + escAttr(ev.item) + '">' + escHtml(ev.item) + '</div>'
                  +     '<div class="small">' + clientLine + '</div>'
                  +   '</div>'
                  +   '<div class="d-flex flex-column align-items-end gap-1 flex-shrink-0">'
                  +     urgBadge(ev.daysLeft, ev.expired)
                  +     '<a href="' + escAttr(ev.link) + '" class="btn btn-outline-secondary btn-sm py-0 px-2" title="Voir le détail">'
                  +       '<i class="bi bi-arrow-right-short"></i>'
                  +     '</a>'
                  +   '</div>'
                  + '</div>';
        });

        document.getElementById('calModalBody').innerHTML = html;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('calModal')).show();
    }

    // Click delegation on badges
    document.addEventListener('click', function (e) {
        const badge = e.target.closest('[data-cal-date]');
        if (badge) openModal(badge.dataset.calDate);
    });

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function escAttr(s) {
        return String(s).replace(/"/g,'&quot;');
    }
})();
</script>
