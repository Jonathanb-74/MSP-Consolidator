<?php

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

class Database
{
    private static ?self $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $config = require dirname(__DIR__, 2) . '/config/database.php';

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['dbname'],
            $config['charset']
        );

        try {
            $this->pdo = new PDO($dsn, $config['user'], $config['password'], $config['options']);
        } catch (PDOException $e) {
            throw new RuntimeException('Connexion base de données impossible : ' . $e->getMessage());
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Exécute une requête SELECT et retourne tous les résultats.
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Exécute une requête SELECT et retourne la première ligne.
     */
    public function fetchOne(string $sql, array $params = []): array|false
    {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetch();
    }

    /**
     * Exécute une requête (INSERT, UPDATE, DELETE) et retourne le nb de lignes affectées.
     */
    public function execute(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Retourne le dernier ID inséré.
     */
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Compte le nombre de lignes d'une requête.
     */
    public function count(string $sql, array $params = []): int
    {
        $stmt = $this->execute($sql, $params);
        return (int)$stmt->fetchColumn();
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollback(): void
    {
        $this->pdo->rollBack();
    }
}
