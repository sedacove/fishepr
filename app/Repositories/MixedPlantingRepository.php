<?php

namespace App\Repositories;

use PDO;

/**
 * Репозиторий для работы с таблицей mixed_plantings
 * 
 * Предоставляет методы для работы с микстовыми посадками:
 * - получение списка микстовых посадок
 * - создание новой микстовой посадки
 * - получение микстовой посадки по ID с компонентами
 * - проверка существования микстовой посадки
 */
class MixedPlantingRepository
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
     * Получает список всех микстовых посадок с информацией о создателе
     * 
     * @return array Массив записей микстовых посадок
     */
    public function findAll(): array
    {
        $stmt = $this->pdo->query(<<<SQL
            SELECT 
                mp.*,
                u.login AS created_by_login,
                u.full_name AS created_by_name
            FROM mixed_plantings mp
            LEFT JOIN users u ON mp.created_by = u.id
            ORDER BY mp.created_at DESC
        SQL);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Получает микстовую посадку по ID с компонентами
     * 
     * @param int $id ID микстовой посадки
     * @return array|null Данные микстовой посадки с компонентами или null, если не найдена
     */
    public function findWithComponents(int $id): ?array
    {
        // Получаем основную информацию
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT 
                mp.*,
                u.login AS created_by_login,
                u.full_name AS created_by_name
            FROM mixed_plantings mp
            LEFT JOIN users u ON mp.created_by = u.id
            WHERE mp.id = ?
        SQL);
        $stmt->execute([$id]);
        $mixedPlanting = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$mixedPlanting) {
            return null;
        }

        // Получаем компоненты
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT 
                mpc.*,
                p.name AS planting_name
            FROM mixed_planting_components mpc
            LEFT JOIN plantings p ON mpc.planting_id = p.id
            WHERE mpc.mixed_planting_id = ?
            ORDER BY mpc.percentage DESC
        SQL);
        $stmt->execute([$id]);
        $components = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $mixedPlanting['components'] = $components;
        return $mixedPlanting;
    }

    /**
     * Создает новую микстовую посадку с компонентами
     * 
     * @param array $data Данные микстовой посадки (name, fish_breed, created_by, components)
     * @return int ID созданной микстовой посадки
     */
    public function insert(array $data): int
    {
        $this->pdo->beginTransaction();
        try {
            // Создаем микстовую посадку
            $stmt = $this->pdo->prepare(<<<SQL
                INSERT INTO mixed_plantings (name, fish_breed, created_by)
                VALUES (?, ?, ?)
            SQL);
            $stmt->execute([
                $data['name'],
                $data['fish_breed'] ?? null,
                $data['created_by'] ?? null,
            ]);
            $mixedPlantingId = (int)$this->pdo->lastInsertId();

            // Добавляем компоненты
            if (!empty($data['components']) && is_array($data['components'])) {
                $stmt = $this->pdo->prepare(<<<SQL
                    INSERT INTO mixed_planting_components (mixed_planting_id, planting_id, percentage)
                    VALUES (?, ?, ?)
                SQL);
                foreach ($data['components'] as $component) {
                    $stmt->execute([
                        $mixedPlantingId,
                        $component['planting_id'],
                        $component['percentage'],
                    ]);
                }
            }

            $this->pdo->commit();
            return $mixedPlantingId;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Проверяет, существует ли микстовая посадка с указанным ID
     * 
     * @param int $id ID микстовой посадки
     * @return bool true, если микстовая посадка существует
     */
    public function exists(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM mixed_plantings WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return (bool)$stmt->fetch();
    }

    /**
     * Находит микстовую посадку по компонентам (проверяет, существует ли уже такая комбинация)
     * 
     * @param array $componentPlantingIds Массив ID посадок-компонентов
     * @return int|null ID найденной микстовой посадки или null
     */
    public function findByComponents(array $componentPlantingIds): ?int
    {
        if (empty($componentPlantingIds)) {
            return null;
        }

        sort($componentPlantingIds);
        $placeholders = implode(',', array_fill(0, count($componentPlantingIds), '?'));
        
        // Находим микстовые посадки, которые имеют точно такие же компоненты
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT mp.id, COUNT(mpc.planting_id) AS component_count
            FROM mixed_plantings mp
            INNER JOIN mixed_planting_components mpc ON mp.id = mpc.mixed_planting_id
            WHERE mpc.planting_id IN ($placeholders)
            GROUP BY mp.id
            HAVING component_count = ?
        SQL);
        
        $params = array_merge($componentPlantingIds, [count($componentPlantingIds)]);
        $stmt->execute($params);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Проверяем, что все компоненты совпадают (не только количество)
        foreach ($results as $result) {
            $stmt = $this->pdo->prepare(<<<SQL
                SELECT planting_id FROM mixed_planting_components
                WHERE mixed_planting_id = ?
                ORDER BY planting_id
            SQL);
            $stmt->execute([$result['id']]);
            $existingComponents = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if ($existingComponents === $componentPlantingIds) {
                return (int)$result['id'];
            }
        }
        
        return null;
    }
}

