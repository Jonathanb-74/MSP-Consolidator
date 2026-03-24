<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

class CalendarController extends Controller
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function index(array $params = []): void
    {
        // --- Filters ---
        $mode     = in_array($_GET['mode'] ?? '', ['calendar', 'list']) ? $_GET['mode'] : 'calendar';
        $provider = $_GET['provider'] ?? 'all';
        if (!in_array($provider, ['becloud', 'infomaniak', 'all'])) {
            $provider = 'all';
        }
        $clientId = max(0, (int)($_GET['client_id'] ?? 0));
        $horizon  = $_GET['horizon'] ?? '90';
        if (!in_array($horizon, ['30', '60', '90', 'all'])) {
            $horizon = '90';
        }

        // --- Calendar month navigation ---
        $today = new \DateTime();
        $month = (int)($_GET['month'] ?? $today->format('n'));
        $year  = (int)($_GET['year']  ?? $today->format('Y'));
        if ($month < 1 || $month > 12)      { $month = (int)$today->format('n'); }
        if ($year  < 2020 || $year > 2040)  { $year  = (int)$today->format('Y'); }

        // --- Horizon SQL fragments (integer-cast, SQL-safe) ---
        $horizonDays = ($horizon !== 'all') ? (int)$horizon : 0;
        $hBc    = ($horizon !== 'all') ? "AND bs.end_date   <= DATE_ADD(CURDATE(), INTERVAL {$horizonDays} DAY)" : '';
        $hInfo  = ($horizon !== 'all') ? "AND ip.expired_at <= UNIX_TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL {$horizonDays} DAY))" : '';

        // --- Build UNION parts (Be-Cloud + Infomaniak only — ESET has no real expiry dates) ---
        $parts = [];

        if ($provider === 'all' || $provider === 'becloud') {
            $parts[] = "
                SELECT
                    'becloud'                       AS provider,
                    bs.end_date                     AS expiry_date,
                    bs.offer_name                   AS item_name,
                    bcc.name                        AS provider_client_name,
                    COALESCE(c.id, 0)               AS client_id,
                    COALESCE(c.name, '')            AS client_name,
                    bcc.id                          AS item_id
                FROM be_cloud_subscriptions bs
                JOIN be_cloud_customers bcc
                    ON bcc.be_cloud_customer_id = bs.be_cloud_customer_id
                LEFT JOIN client_provider_mappings cpm
                    ON cpm.provider_client_id = bcc.be_cloud_customer_id
                   AND cpm.connection_id      = bcc.connection_id
                   AND cpm.is_confirmed       = 1
                LEFT JOIN clients c ON c.id = cpm.client_id
                WHERE bs.end_date IS NOT NULL
                  AND bs.end_date >= CURDATE()
                  $hBc";
        }

        if ($provider === 'all' || $provider === 'infomaniak') {
            $parts[] = "
                SELECT
                    'infomaniak'                                        AS provider,
                    DATE(FROM_UNIXTIME(ip.expired_at))                  AS expiry_date,
                    COALESCE(ip.internal_name, ip.service_name)         AS item_name,
                    COALESCE(ip.customer_name, ia.name)                 AS provider_client_name,
                    COALESCE(c.id, 0)                                   AS client_id,
                    COALESCE(c.name, '')                                AS client_name,
                    ia.id                                               AS item_id
                FROM infomaniak_products ip
                JOIN infomaniak_accounts ia
                    ON ia.infomaniak_account_id = ip.infomaniak_account_id
                   AND ia.connection_id         = ip.connection_id
                LEFT JOIN client_provider_mappings cpm
                    ON cpm.provider_client_id = CAST(ia.infomaniak_account_id AS CHAR) COLLATE utf8mb4_general_ci
                   AND cpm.connection_id      = ia.connection_id
                   AND cpm.is_confirmed       = 1
                LEFT JOIN clients c ON c.id = cpm.client_id
                WHERE ip.expired_at IS NOT NULL
                  AND ip.expired_at >= UNIX_TIMESTAMP(CURDATE())
                  $hInfo";
        }

        // --- Execute and apply client filter ---
        $events = [];
        if (!empty($parts)) {
            $rows = $this->db->fetchAll(implode(' UNION ALL ', $parts) . ' ORDER BY expiry_date ASC');
            foreach ($rows as $row) {
                if ($clientId > 0 && (int)$row['client_id'] !== $clientId) {
                    continue;
                }
                $events[] = $row;
            }
        }

        // --- Group by date for calendar ---
        $byDate = [];
        foreach ($events as $event) {
            $byDate[$event['expiry_date']][] = $event;
        }

        // --- Build calendar grid (6-row max, Mon–Sun) ---
        $firstDay     = new \DateTime("$year-$month-01");
        $daysInMonth  = (int)$firstDay->format('t');
        $startWeekday = (int)$firstDay->format('N') - 1; // 0=Mon … 6=Sun

        $grid       = [];
        $dayCounter = 1 - $startWeekday;
        for ($r = 0; $r < 6; $r++) {
            if ($dayCounter > $daysInMonth) {
                break;
            }
            $gridRow = [];
            for ($col = 0; $col < 7; $col++) {
                if ($dayCounter < 1 || $dayCounter > $daysInMonth) {
                    $gridRow[] = null;
                } else {
                    $ds = sprintf('%04d-%02d-%02d', $year, $month, $dayCounter);
                    $gridRow[] = ['day' => $dayCounter, 'date' => $ds, 'events' => $byDate[$ds] ?? []];
                }
                $dayCounter++;
            }
            $grid[] = $gridRow;
        }

        // --- Client list for dropdown (derived from visible events) ---
        $clientsMap = [];
        foreach ($events as $event) {
            if ($event['client_id'] > 0 && !isset($clientsMap[$event['client_id']])) {
                $clientsMap[$event['client_id']] = $event['client_name'];
            }
        }
        asort($clientsMap);

        // --- Prev / Next month links ---
        $prevMonth = $month - 1; $prevYear = $year;
        if ($prevMonth < 1)  { $prevMonth = 12; $prevYear--; }
        $nextMonth = $month + 1; $nextYear = $year;
        if ($nextMonth > 12) { $nextMonth = 1;  $nextYear++; }

        $monthNames = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
                           'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

        $this->render('calendar/index', [
            'pageTitle'   => 'Calendrier des expirations',
            'breadcrumbs' => ['Dashboard' => '/', 'Calendrier' => null],
            'events'      => $events,
            'byDate'      => $byDate,
            'grid'        => $grid,
            'clientsMap'  => $clientsMap,
            'month'       => $month,
            'year'        => $year,
            'prevMonth'   => $prevMonth,
            'prevYear'    => $prevYear,
            'nextMonth'   => $nextMonth,
            'nextYear'    => $nextYear,
            'mode'        => $mode,
            'filters'     => [
                'provider'  => $provider,
                'client_id' => $clientId,
                'horizon'   => $horizon,
                'mode'      => $mode,
            ],
            'monthNames'  => $monthNames,
            'todayStr'    => $today->format('Y-m-d'),
        ]);
    }
}
