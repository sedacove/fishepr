<?php

namespace App\Repositories;

use PDO;

/**
 * Repository operating on counterparty_documents table.
 */
class CounterpartyDocumentRepository extends Repository
{
    /**
     * Returns all documents for a counterparty with uploader details.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getByCounterparty(int $counterpartyId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.*, u.full_name AS uploaded_by_name, u.login AS uploaded_by_login ' .
            'FROM counterparty_documents d ' .
            'LEFT JOIN users u ON d.uploaded_by = u.id ' .
            'WHERE d.counterparty_id = ? ORDER BY d.uploaded_at DESC'
        );
        $stmt->execute([$counterpartyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Persists new document metadata and returns its identifier.
     *
     * @param array<string,mixed> $data
     */
    public function insert(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO counterparty_documents (counterparty_id, original_name, file_name, file_path, file_size, mime_type, uploaded_by) ' .
            'VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['counterparty_id'],
            $data['original_name'],
            $data['file_name'],
            $data['file_path'],
            $data['file_size'],
            $data['mime_type'],
            $data['uploaded_by'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /** Retrieves a single document row or null when not found. */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM counterparty_documents WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /** Removes a single document record. */
    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM counterparty_documents WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Removes all documents of the counterparty and returns relative file paths.
     *
     * @return array<int,string>
     */
    public function deleteByCounterparty(int $counterpartyId): array
    {
        $stmt = $this->pdo->prepare('SELECT file_path FROM counterparty_documents WHERE counterparty_id = ?');
        $stmt->execute([$counterpartyId]);
        $paths = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $stmtDelete = $this->pdo->prepare('DELETE FROM counterparty_documents WHERE counterparty_id = ?');
        $stmtDelete->execute([$counterpartyId]);

        return $paths;
    }
}
