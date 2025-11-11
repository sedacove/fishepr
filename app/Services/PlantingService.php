<?php

namespace App\Services;

use App\Models\Planting\Planting;
use App\Models\Planting\PlantingFile;
use App\Repositories\PlantingRepository;
use App\Support\Exceptions\ValidationException;
use PDO;
use RuntimeException;

require_once __DIR__ . '/../../includes/activity_log.php';

class PlantingService
{
    private const UPLOAD_DIR = 'uploads/plantings/';
    private const MAX_FILE_SIZE = 10485760; // 10 MB

    private PlantingRepository $plantings;
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->plantings = new PlantingRepository($pdo);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function list(bool $archived): array
    {
        $items = $this->plantings->listByArchived($archived);
        return array_map(function (Planting $planting) {
            $data = $planting->toArray();
            $data['hatch_date'] = $data['hatch_date'] ? $this->formatDisplayDate($data['hatch_date']) : null;
            $data['planting_date'] = $this->formatDisplayDate($data['planting_date']);
            $data['created_at'] = $this->formatDateTime($data['created_at']);
            $data['updated_at'] = $this->formatDateTime($data['updated_at']);
            $data['is_archived'] = (bool) $data['is_archived'];
            return $data;
        }, $items);
    }

    public function get(int $id): array
    {
        $planting = $this->plantings->find($id);
        if (!$planting) {
            throw new RuntimeException('Посадка не найдена', 404);
        }

        $data = $planting->toArray();
        $data['hatch_date'] = $data['hatch_date'] ? $this->formatFormDate($data['hatch_date']) : null;
        $data['planting_date'] = $this->formatFormDate($data['planting_date']);
        $data['is_archived'] = (bool) $data['is_archived'];
        $data['files'] = array_map(fn (PlantingFile $file) => $file->toArray(), $this->plantings->getFiles($id));

        return $data;
    }

    public function create(array $payload, int $userId): int
    {
        $data = $this->validatePayload($payload);
        $data['created_by'] = $userId;

        $plantingId = $this->plantings->create($data);

        \logActivity('create', 'planting', $plantingId, "Создана посадка: {$data['name']}", [
            'name' => $data['name'],
            'fish_breed' => $data['fish_breed'],
            'planting_date' => $data['planting_date'],
            'fish_count' => $data['fish_count'],
        ]);

        return $plantingId;
    }

    public function update(int $id, array $payload): void
    {
        $existing = $this->plantings->find($id);
        if (!$existing) {
            throw new RuntimeException('Посадка не найдена', 404);
        }

        $data = $this->validatePayload($payload);
        $this->plantings->update($id, $data);

        $changes = $this->detectChanges($existing->toArray(), $data);

        if (!empty($changes)) {
            \logActivity('update', 'planting', $id, "Обновлена посадка: {$existing->name}", $changes);
        }
    }

    public function delete(int $id): void
    {
        $planting = $this->plantings->find($id);
        if (!$planting) {
            throw new RuntimeException('Посадка не найдена', 404);
        }

        $files = $this->plantings->getFiles($id);
        foreach ($files as $file) {
            $this->removePhysicalFile($file->file_path);
        }
        $this->plantings->deleteFilesByPlanting($id);
        $this->plantings->delete($id);

        \logActivity('delete', 'planting', $id, "Удалена посадка: {$planting->name}", [
            'name' => $planting->name,
            'fish_breed' => $planting->fish_breed,
            'planting_date' => $planting->planting_date,
            'fish_count' => $planting->fish_count,
            'files_count' => count($files),
        ]);
    }

    public function setArchived(int $id, bool $archived): void
    {
        $planting = $this->plantings->find($id);
        if (!$planting) {
            throw new RuntimeException('Посадка не найдена', 404);
        }

        $this->plantings->setArchived($id, $archived);

        $action = $archived ? 'архивирована' : 'разархивирована';
        \logActivity('update', 'planting', $id, "Посадка {$action}: {$planting->name}", [
            'is_archived' => [
                'old' => (bool) $planting->is_archived,
                'new' => $archived,
            ],
        ]);
    }

    public function deleteFile(int $fileId): void
    {
        $file = $this->plantings->findFile($fileId);
        if (!$file) {
            throw new RuntimeException('Файл не найден', 404);
        }

        $this->removePhysicalFile($file->file_path);
        $this->plantings->deleteFile($fileId);

        \logActivity('delete', 'planting_file', $fileId, "Удален файл: {$file->original_name}", [
            'planting_id' => $file->planting_id,
            'original_name' => $file->original_name,
            'file_size' => $file->file_size,
        ]);
    }

    /**
     * @return array{uploaded: array<int,array<string,mixed>>, errors: string[]}
     */
    public function uploadFiles(int $plantingId, array $filesInput, int $userId): array
    {
        $planting = $this->plantings->find($plantingId);
        if (!$planting) {
            throw new RuntimeException('Посадка не найдена', 404);
        }

        $files = $this->normalizeFilesArray($filesInput);
        if (empty($files)) {
            throw new ValidationException('files', 'Файлы не загружены');
        }

        $uploadDir = $this->ensureUploadDirectory();

        $uploaded = [];
        $errors = [];

        foreach ($files as $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $errors[] = 'Ошибка загрузки файла: ' . ($file['name'] ?? 'unknown');
                continue;
            }

            $originalName = $file['name'] ?? 'file';
            $size = (int) ($file['size'] ?? 0);
            if ($size > self::MAX_FILE_SIZE) {
                $errors[] = "Файл слишком большой: {$originalName} (максимум 10 МБ)";
                continue;
            }

            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
            $generatedName = uniqid('planting_' . $plantingId . '_', true) . ($extension ? '.' . $extension : '');
            $relativePath = self::UPLOAD_DIR . $generatedName;
            $absolutePath = $uploadDir . $generatedName;

            if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
                $errors[] = "Не удалось сохранить файл: {$originalName}";
                continue;
            }

            $fileId = $this->plantings->createFile([
                'planting_id' => $plantingId,
                'original_name' => $originalName,
                'file_name' => $generatedName,
                'file_path' => $relativePath,
                'file_size' => $size,
                'mime_type' => $file['type'] ?? null,
                'uploaded_by' => $userId,
            ]);

            $uploaded[] = [
                'id' => $fileId,
                'original_name' => $originalName,
                'file_name' => $generatedName,
                'file_size' => $size,
                'mime_type' => $file['type'] ?? null,
            ];

            \logActivity('create', 'planting_file', $fileId, "Загружен файл для посадки #{$plantingId}: {$originalName}", [
                'planting_id' => $plantingId,
                'original_name' => $originalName,
                'file_size' => $size,
            ]);
        }

        return ['uploaded' => $uploaded, 'errors' => $errors];
    }

    /**
     * @return array{name:string, fish_breed:string, hatch_date:?string, planting_date:string, fish_count:int, biomass_weight:?float, supplier:?string, price:?float, delivery_cost:?float}
     */
    private function validatePayload(array $payload): array
    {
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            throw new ValidationException('name', 'Название обязательно для заполнения');
        }

        $fishBreed = trim((string) ($payload['fish_breed'] ?? ''));
        if ($fishBreed === '') {
            throw new ValidationException('fish_breed', 'Порода рыбы обязательна для заполнения');
        }

        $plantingDate = $this->parseDate($payload['planting_date'] ?? null, 'planting_date', 'Дата посадки обязательна для заполнения');

        $fishCount = (int) ($payload['fish_count'] ?? 0);
        if ($fishCount <= 0) {
            throw new ValidationException('fish_count', 'Количество рыб должно быть больше 0');
        }

        $hatchDate = null;
        if (!empty($payload['hatch_date'])) {
            $hatchDate = $this->parseDate($payload['hatch_date'], 'hatch_date', 'Некорректная дата вылупа');
        }

        $biomassWeight = $this->parseNullableFloat($payload['biomass_weight'] ?? null, 'biomass_weight');
        $price = $this->parseNullableFloat($payload['price'] ?? null, 'price');
        $deliveryCost = $this->parseNullableFloat($payload['delivery_cost'] ?? null, 'delivery_cost');
        $supplier = $this->normalizeNullableString($payload['supplier'] ?? null);

        return [
            'name' => $name,
            'fish_breed' => $fishBreed,
            'hatch_date' => $hatchDate,
            'planting_date' => $plantingDate,
            'fish_count' => $fishCount,
            'biomass_weight' => $biomassWeight,
            'supplier' => $supplier,
            'price' => $price,
            'delivery_cost' => $deliveryCost,
        ];
    }

    private function parseDate(?string $value, string $field, string $errorMessage): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            throw new ValidationException($field, $errorMessage);
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new ValidationException($field, 'Некорректный формат даты');
        }
        return date('Y-m-d', $timestamp);
    }

    private function parseNullableFloat($value, string $field): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            throw new ValidationException($field, 'Значение должно быть числом');
        }
        $float = round((float) $value, 2);
        if ($float < 0) {
            throw new ValidationException($field, 'Значение не может быть отрицательным');
        }
        return $float;
    }

    private function normalizeNullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    /**
     * @param array $existing
     * @param array $updated
     * @return array<string,mixed>
     */
    private function detectChanges(array $existing, array $updated): array
    {
        $changes = [];
        foreach ($updated as $key => $value) {
            $old = $existing[$key] ?? null;
            if ($key === 'biomass_weight' || $key === 'price' || $key === 'delivery_cost') {
                $old = $old !== null ? (float) $old : null;
                $value = $value !== null ? (float) $value : null;
            }
            if ($old !== $value) {
                $changes[$key] = ['old' => $old, 'new' => $value];
            }
        }
        return $changes;
    }

    private function formatDisplayDate(string $value): string
    {
        return date('d.m.Y', strtotime($value));
    }

    private function formatFormDate(string $value): string
    {
        return date('Y-m-d', strtotime($value));
    }

    private function formatDateTime(string $value): string
    {
        return date('d.m.Y H:i', strtotime($value));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function normalizeFilesArray(array $filesInput): array
    {
        if (empty($filesInput)) {
            return [];
        }

        if (!isset($filesInput['name'])) {
            return [$filesInput];
        }

        if (!is_array($filesInput['name'])) {
            return [$filesInput];
        }

        $files = [];
        $count = count($filesInput['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($filesInput['name'][$i] === '') {
                continue;
            }
            $files[] = [
                'name' => $filesInput['name'][$i],
                'type' => $filesInput['type'][$i] ?? null,
                'tmp_name' => $filesInput['tmp_name'][$i],
                'error' => $filesInput['error'][$i],
                'size' => $filesInput['size'][$i],
            ];
        }

        return $files;
    }

    private function ensureUploadDirectory(): string
    {
        $basePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
        $uploadDir = $basePath . self::UPLOAD_DIR;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        return rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    private function removePhysicalFile(string $relativePath): void
    {
        if ($relativePath === '') {
            return;
        }
        $basePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
        $absolutePath = $basePath . ltrim($relativePath, DIRECTORY_SEPARATOR);
        if (file_exists($absolutePath)) {
            @unlink($absolutePath);
        }
    }
}


