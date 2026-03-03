<?php

namespace App\Models;

use App\Core\Database;

class SyncLog
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getLatestByProvider(string $providerCode, int $limit = 50): array
    {
        return $this->db->fetchAll(
            "SELECT sl.*, p.name AS provider_name
             FROM sync_logs sl
             JOIN providers p ON p.id = sl.provider_id
             WHERE p.code = ?
             ORDER BY sl.started_at DESC
             LIMIT ?",
            [$providerCode, $limit]
        );
    }

    public function getLastSuccess(string $providerCode): array|false
    {
        return $this->db->fetchOne(
            "SELECT sl.*
             FROM sync_logs sl
             JOIN providers p ON p.id = sl.provider_id
             WHERE p.code = ? AND sl.status = 'success'
             ORDER BY sl.finished_at DESC
             LIMIT 1",
            [$providerCode]
        );
    }
}
