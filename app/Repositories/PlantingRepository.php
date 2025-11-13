<?php

namespace App\Repositories;

use App\Models\Planting\Planting;
use App\Models\Planting\PlantingFile;
use PDO;

/**
 * Репозиторий для работы с посадками
 * 
 * Выполняет SQL запросы к таблицам plantings и planting_files:
 * - получение списка посадок (активных или архивных)
 * - поиск посадки по ID
 * - создание, обновление, удаление посадок
 * - управление файлами посадок
 */
class PlantingRepository extends Repository
{
    /**
     * Получает список посадок по статусу архивирования
     * 
     * @param bool $archived true для архивных посадок, false для активных
     * @return Planting[] Массив посадок, отсортированных по дате посадки и дате создания
     */
    public function listByArchived(bool $archived): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, u.login AS created_by_login, u.full_name AS created_by_name,
                    (SELECT COUNT(*) FROM planting_files pf WHERE pf.planting_id = p.id) AS files_count
             FROM plantings p
             LEFT JOIN users u ON u.id = p.created_by
             WHERE p.is_archived = ?
             ORDER BY p.planting_date DESC, p.created_at DESC'
        );
        $stmt->execute([$archived ? 1 : 0]);
        return array_map(
            fn ($row) => new Planting($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        );
    }

    /**
     * Находит посадку по ID
     * 
     * @param int $id ID посадки
     * @return Planting|null Модель посадки или null, если не найдена
     */
    public function find(int $id): ?Planting
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, u.login AS created_by_login, u.full_name AS created_by_name
             FROM plantings p
             LEFT JOIN users u ON u.id = p.created_by
             WHERE p.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? new Planting($row) : null;
    }

    /**
     * Создает новую посадку
     * 
     * @param array $data Данные посадки (name, fish_breed, hatch_date, planting_date, fish_count, biomass_weight, supplier, price, delivery_cost, created_by)
     * @return int ID созданной посадки
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO plantings (
                name, fish_breed, hatch_date, planting_date, fish_count,
                biomass_weight, supplier, price, delivery_cost, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'],
            $data['fish_breed'],
            $data['hatch_date'],
            $data['planting_date'],
            $data['fish_count'],
            $data['biomass_weight'],
            $data['supplier'],
            $data['price'],
            $data['delivery_cost'],
            $data['created_by'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Обновляет данные посадки
     * 
     * @param int $id ID посадки
     * @param array $data Данные для обновления (name, fish_breed, hatch_date, planting_date, fish_count, biomass_weight, supplier, price, delivery_cost)
     * @return void
     */
    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE plantings SET
                name = ?,
                fish_breed = ?,
                hatch_date = ?,
                planting_date = ?,
                fish_count = ?,
                biomass_weight = ?,
                supplier = ?,
                price = ?,
                delivery_cost = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['name'],
            $data['fish_breed'],
            $data['hatch_date'],
            $data['planting_date'],
            $data['fish_count'],
            $data['biomass_weight'],
            $data['supplier'],
            $data['price'],
            $data['delivery_cost'],
            $id,
        ]);
    }

    /**
     * Удаляет посадку
     * 
     * @param int $id ID посадки для удаления
     * @return void
     */
    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM plantings WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Устанавливает статус архивирования посадки
     * 
     * @param int $id ID посадки
     * @param bool $archived true для архивирования, false для разархивирования
     * @return void
     */
    public function setArchived(int $id, bool $archived): void
    {
        $stmt = $this->pdo->prepare('UPDATE plantings SET is_archived = ? WHERE id = ?');
        $stmt->execute([$archived ? 1 : 0, $id]);
    }

    /**
     * Получает список файлов для посадки
     * 
     * @param int $plantingId ID посадки
     * @return PlantingFile[] Массив файлов, отсортированных по дате создания
     */
    public function getFiles(int $plantingId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM planting_files WHERE planting_id = ? ORDER BY created_at ASC'
        );
        $stmt->execute([$plantingId]);

        return array_map(
            fn ($row) => new PlantingFile($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        );
    }

    /**
     * Находит файл посадки по ID
     * 
     * @param int $id ID файла
     * @return PlantingFile|null Модель файла или null, если не найден
     */
    public function findFile(int $id): ?PlantingFile
    {
        $stmt = $this->pdo->prepare('SELECT * FROM planting_files WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? new PlantingFile($row) : null;
    }

    /**
     * Создает запись о файле посадки
     * 
     * @param array $data Данные файла (planting_id, original_name, file_name, file_path, file_size, mime_type, uploaded_by)
     * @return int ID созданной записи файла
     */
    public function createFile(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO planting_files (planting_id, original_name, file_name, file_path, file_size, mime_type, uploaded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['planting_id'],
            $data['original_name'],
            $data['file_name'],
            $data['file_path'],
            $data['file_size'],
            $data['mime_type'],
            $data['uploaded_by'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Удаляет файл посадки
     * 
     * @param int $id ID файла для удаления
     * @return void
     */
    public function deleteFile(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM planting_files WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Удаляет все файлы для посадки
     * 
     * @param int $plantingId ID посадки
     * @return void
     */
    public function deleteFilesByPlanting(int $plantingId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM planting_files WHERE planting_id = ?');
        $stmt->execute([$plantingId]);
    }
}


