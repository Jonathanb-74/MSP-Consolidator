<?php

namespace App\Models;

use App\Core\Database;

class Provider
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findByCode(string $code): array|false
    {
        return $this->db->fetchOne(
            "SELECT * FROM providers WHERE code = ? LIMIT 1",
            [$code]
        );
    }

    public function getAll(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM providers ORDER BY code"
        );
    }

    public function updateLastSync(string $code): void
    {
        $this->db->execute(
            "UPDATE providers SET last_sync_at = NOW() WHERE code = ?",
            [$code]
        );
    }
}
