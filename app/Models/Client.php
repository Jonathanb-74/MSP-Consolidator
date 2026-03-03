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
            "SELECT c.*, s.code AS structure_code
             FROM clients c JOIN structures s ON s.id = c.structure_id
             WHERE c.client_number = ? LIMIT 1",
            [$clientNumber]
        );
    }

    public function findById(int $id): array|false
    {
        return $this->db->fetchOne(
            "SELECT c.*, s.code AS structure_code
             FROM clients c JOIN structures s ON s.id = c.structure_id
             WHERE c.id = ? LIMIT 1",
            [$id]
        );
    }

    public function countByStructure(): array
    {
        return $this->db->fetchAll(
            "SELECT s.code, COUNT(c.id) AS total
             FROM structures s
             LEFT JOIN clients c ON c.structure_id = s.id AND c.is_active = 1
             GROUP BY s.id, s.code
             ORDER BY s.code"
        );
    }
}
