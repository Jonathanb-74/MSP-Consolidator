<?php

namespace App\Models;

use App\Core\Database;

class Client
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findByClientNumber(string $clientNumber): array|false
    {
        return $this->db->fetchOne(
            "SELECT * FROM clients WHERE client_number = ? LIMIT 1",
            [$clientNumber]
        );
    }

    public function findById(int $id): array|false
    {
        return $this->db->fetchOne(
            "SELECT * FROM clients WHERE id = ? LIMIT 1",
            [$id]
        );
    }

    public function countByTag(): array
    {
        return $this->db->fetchAll(
            "SELECT t.name, t.color, COUNT(ct.client_id) AS total
             FROM tags t
             LEFT JOIN client_tags ct ON ct.tag_id = t.id
             GROUP BY t.id
             ORDER BY t.display_order ASC, t.name ASC"
        );
    }
}
