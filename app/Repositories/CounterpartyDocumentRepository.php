<?php

namespace App\Repositories;

use PDO;

/**
 * Репозиторий для работы с документами контрагентов
 * 
 * Выполняет SQL запросы к таблице counterparty_documents:
 * - получение списка документов для контрагента
 * - создание записи о документе
 * - поиск документа по ID
 * - удаление документа
 * - удаление всех документов контрагента
 */
class CounterpartyDocumentRepository extends Repository
{
    /**
     * Получает список всех документов для контрагента
     * 
     * @param int $counterpartyId ID контрагента
     * @return array<int,array<string,mixed>> Массив документов с информацией о загрузившем пользователе, отсортированных по дате загрузки (от новых к старым)
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
     * Создает запись о документе контрагента
     * 
     * @param array<string,mixed> $data Данные документа (counterparty_id, original_name, file_name, file_path, file_size, mime_type, uploaded_by)
     * @return int ID созданной записи документа
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

    /**
     * Находит документ по ID
     * 
     * @param int $id ID документа
     * @return array|null Данные документа или null, если не найден
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM counterparty_documents WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Удаляет документ
     * 
     * @param int $id ID документа для удаления
     * @return void
     */
    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM counterparty_documents WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Удаляет все документы контрагента и возвращает пути к файлам
     * 
     * Используется при удалении контрагента для последующего удаления файлов с диска.
     * 
     * @param int $counterpartyId ID контрагента
     * @return array<int,string> Массив путей к файлам документов
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
