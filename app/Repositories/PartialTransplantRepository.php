<?php

namespace App\Repositories;

use PDO;

/**
 * Репозиторий для работы с таблицей partial_transplants
 * 
 * Предоставляет методы для работы с записями частичных пересадок:
 * - получение списка пересадок
 * - создание новой пересадки
 * - получение пересадки по ID
 * - обновление статуса отката
 */
class PartialTransplantRepository
{
    /**
     * @var PDO Подключение к базе данных
     */
    private PDO $pdo;

    /**
     * Конструктор репозитория
     * 
     * @param PDO $pdo Подключение к базе данных
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Получает список всех пересадок с информацией о сессиях и пользователях
     * 
     * @return array Массив записей пересадок
     */
    public function findAll(): array
    {
        $stmt = $this->pdo->query(<<<SQL
            SELECT 
                pt.*,
                ss.name AS source_session_name,
                rs.name AS recipient_session_name,
                creator.login AS created_by_login,
                creator.full_name AS created_by_name,
                reverter.login AS reverted_by_login,
                reverter.full_name AS reverted_by_name
            FROM partial_transplants pt
            LEFT JOIN sessions ss ON pt.source_session_id = ss.id
            LEFT JOIN sessions rs ON pt.recipient_session_id = rs.id
            LEFT JOIN users creator ON pt.created_by = creator.id
            LEFT JOIN users reverter ON pt.reverted_by = reverter.id
            ORDER BY pt.transplant_date DESC, pt.created_at DESC
        SQL);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Получает пересадку по ID
     * 
     * @param int $id ID пересадки
     * @return array|null Данные пересадки или null, если не найдена
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT 
                pt.*,
                ss.name AS source_session_name,
                rs.name AS recipient_session_name,
                creator.login AS created_by_login,
                creator.full_name AS created_by_name,
                reverter.login AS reverted_by_login,
                reverter.full_name AS reverted_by_name
            FROM partial_transplants pt
            LEFT JOIN sessions ss ON pt.source_session_id = ss.id
            LEFT JOIN sessions rs ON pt.recipient_session_id = rs.id
            LEFT JOIN users creator ON pt.created_by = creator.id
            LEFT JOIN users reverter ON pt.reverted_by = reverter.id
            WHERE pt.id = ?
        SQL);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Создает новую запись пересадки
     * 
     * @param array $data Данные пересадки (transplant_date, source_session_id, recipient_session_id, weight, fish_count, created_by)
     * @return int ID созданной пересадки
     */
    public function insert(array $data): int
    {
        $stmt = $this->pdo->prepare(<<<SQL
            INSERT INTO partial_transplants 
                (transplant_date, source_session_id, recipient_session_id, weight, fish_count, created_by)
            VALUES (?, ?, ?, ?, ?, ?)
        SQL);
        $stmt->execute([
            $data['transplant_date'],
            $data['source_session_id'],
            $data['recipient_session_id'],
            $data['weight'],
            $data['fish_count'],
            $data['created_by'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Отмечает пересадку как откатанную
     * 
     * @param int $id ID пересадки
     * @param int $revertedBy ID пользователя, выполнившего откат
     * @return void
     */
    public function markAsReverted(int $id, int $revertedBy): void
    {
        $stmt = $this->pdo->prepare(<<<SQL
            UPDATE partial_transplants
            SET is_reverted = 1,
                reverted_by = ?,
                reverted_at = NOW()
            WHERE id = ?
        SQL);
        $stmt->execute([$revertedBy, $id]);
    }

    /**
     * Проверяет, существует ли пересадка с указанным ID
     * 
     * @param int $id ID пересадки
     * @return bool true, если пересадка существует
     */
    public function exists(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM partial_transplants WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return (bool)$stmt->fetch();
    }
}

