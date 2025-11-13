<?php

namespace App\Services;

use App\Models\Counterparty\Counterparty;
use App\Models\Counterparty\CounterpartyDocument;
use App\Repositories\CounterpartyDocumentRepository;
use App\Repositories\CounterpartyRepository;
use App\Support\Exceptions\ValidationException;
use DomainException;
use PDO;
use RuntimeException;

/**
 * Сервис для работы с контрагентами
 * 
 * Содержит бизнес-логику для работы с контрагентами:
 * - валидация данных
 * - нормализация входных данных
 * - координация работы репозиториев
 * - подготовка DTO для ответов
 * - работа с файлами документов контрагентов
 */
class CounterpartyService
{
    /**
     * @var CounterpartyRepository Репозиторий для работы с контрагентами
     */
    private CounterpartyRepository $counterparties;
    
    /**
     * @var CounterpartyDocumentRepository Репозиторий для работы с документами контрагентов
     */
    private CounterpartyDocumentRepository $documents;
    
    /**
     * @var PDO Подключение к базе данных
     */
    private PDO $pdo;

    /**
     * Конструктор сервиса
     * 
     * @param PDO $pdo Подключение к базе данных
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->counterparties = new CounterpartyRepository($pdo);
        $this->documents = new CounterpartyDocumentRepository($pdo);
    }

    /**
     * Возвращает доступные цвета, которые можно назначить контрагентам
     * 
     * @return array Массив цветов в формате [['value' => hex, 'label' => название], ...]
     */
    public function palette(): array
    {
        $result = [];
        foreach ($this->getColorPalette() as $hex => $label) {
            $result[] = ['value' => $hex, 'label' => $label];
        }
        return $result;
    }

    /**
     * Возвращает список контрагентов с количеством документов
     * 
     * @return array Массив контрагентов с полем documents_count
     */
    public function list(): array
    {
        $items = $this->counterparties->listAll();
        if (empty($items)) {
            return [];
        }
        $ids = array_column($items, 'id');
        $docCounts = $this->counterparties->countDocumentsFor($ids);
        $result = [];
        foreach ($items as $item) {
            $counterparty = new Counterparty($item);
            $counterparty->documents_count = (int)($docCounts[$counterparty->id] ?? 0);
            $counterparty->created_at = $this->formatDateTime($counterparty->created_at);
            $counterparty->updated_at = $this->formatDateTime($counterparty->updated_at);
            $result[] = $counterparty->toArray();
        }
        return $result;
    }

    /**
     * Возвращает полную информацию о контрагенте, включая его документы
     * 
     * @param int $id ID контрагента
     * @return array Данные контрагента с массивом документов
     * @throws RuntimeException Если контрагент не найден
     */
    public function get(int $id): array
    {
        $data = $this->counterparties->findById($id);
        if (!$data) {
            throw new RuntimeException('Контрагент не найден');
        }
        $counterparty = new Counterparty($data);
        $counterparty->created_at = $this->formatDateTime($counterparty->created_at);
        $counterparty->updated_at = $this->formatDateTime($counterparty->updated_at);

        $documents = $this->documents->getByCounterparty($id);
        $docModels = [];
        foreach ($documents as $doc) {
            $document = new CounterpartyDocument($doc);
            $document->uploaded_at = $this->formatDateTime($document->uploaded_at);
            $docModels[] = $document->toArray();
        }
        $counterparty->documents = $docModels;
        $counterparty->documents_count = count($docModels);

        return $counterparty->toArray();
    }

    /** Creates a new counterparty and returns its identifier. */
    public function create(array $payload, int $userId): int
    {
        $data = $this->validatePayload($payload);
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;

        $id = $this->counterparties->insert($data);

        if (function_exists('logActivity')) {
            \logActivity('create', 'counterparty', $id, 'Создан контрагент: ' . $data['name'], $data);
        }

        return $id;
    }

    /** Updates mutable fields for a counterparty. */
    public function update(int $id, array $payload, int $userId): void
    {
        $existing = $this->counterparties->findById($id);
        if (!$existing) {
            throw new RuntimeException('Контрагент не найден');
        }

        $data = $this->validatePayload($payload);
        $changes = [];
        $updates = [];

        foreach ($data as $column => $value) {
            $oldValue = $existing[$column] ?? null;
            if ($column === 'description') {
                $oldValue = $oldValue ?? '';
            }
            if ($column === 'email') {
                $oldValue = $oldValue ?? '';
            }
            if ($value !== ($oldValue ?? null)) {
                $updates[$column] = $value;
                $changes[$column] = ['old' => $oldValue, 'new' => $value];
            }
        }

        if (empty($updates)) {
            return;
        }

        $updates['updated_by'] = $userId;
        $this->counterparties->update($id, $updates);

        if (function_exists('logActivity')) {
            \logActivity('update', 'counterparty', $id, 'Обновлён контрагент: ' . $existing['name'], $changes);
        }
    }

    /** Deletes counterparty and its documents from storage. */
    public function delete(int $id): void
    {
        $existing = $this->counterparties->findById($id);
        if (!$existing) {
            throw new RuntimeException('Контрагент не найден');
        }

        $paths = $this->documents->deleteByCounterparty($id);
        foreach ($paths as $relativePath) {
            $filePath = $this->resolveStoragePath($relativePath);
            if ($filePath && is_file($filePath)) {
                @unlink($filePath);
            }
        }

        $this->counterparties->delete($id);

        if (function_exists('logActivity')) {
            \logActivity('delete', 'counterparty', $id, 'Удалён контрагент: ' . $existing['name'], [
                'name' => $existing['name'],
                'inn' => $existing['inn'] ?? null,
            ]);
        }
    }

    /** Handles batch document upload and persists metadata. */
    public function uploadDocuments(int $counterpartyId, array $files, int $userId): array
    {
        $counterparty = $this->counterparties->findById($counterpartyId);
        if (!$counterparty) {
            throw new RuntimeException('Контрагент не найден');
        }

        if (!isset($files['name']) || !is_array($files['name'])) {
            throw new DomainException('Файлы не переданы');
        }

        $uploadDir = $this->ensureUploadDirectory();
        $savedDocuments = [];
        $fileCount = count($files['name']);

        for ($i = 0; $i < $fileCount; $i++) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }

            $tmpPath = $files['tmp_name'][$i] ?? null;
            if (!$tmpPath || !is_uploaded_file($tmpPath)) {
                continue;
            }

            $originalName = $files['name'][$i];
            $fileSize = (int)($files['size'][$i] ?? 0);
            $mimeType = $files['type'][$i] ?? null;
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
            $fileName = uniqid('counterparty_', true) . ($extension ? '.' . $extension : '');
            $relativePath = 'uploads/counterparties/' . $fileName;
            $absolutePath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

            if (!move_uploaded_file($tmpPath, $absolutePath)) {
                continue;
            }

            $documentId = $this->documents->insert([
                'counterparty_id' => $counterpartyId,
                'original_name' => $originalName,
                'file_name' => $fileName,
                'file_path' => $relativePath,
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
                'uploaded_by' => $userId,
            ]);

            $savedDocuments[] = [
                'id' => $documentId,
                'original_name' => $originalName,
                'file_size' => $fileSize,
            ];
        }

        if (empty($savedDocuments)) {
            throw new RuntimeException('Не удалось загрузить файлы');
        }

        return $savedDocuments;
    }

    /** Removes single document metadata and deletes its file. */
    public function deleteDocument(int $documentId): void
    {
        $document = $this->documents->find($documentId);
        if (!$document) {
            throw new RuntimeException('Документ не найден');
        }

        $filePath = $this->resolveStoragePath($document['file_path']);
        if ($filePath && is_file($filePath)) {
            @unlink($filePath);
        }

        $this->documents->delete($documentId);
    }

    /** Validates payload used for create/update operations. */
    private function validatePayload(array $payload): array
    {
        $name = trim($payload['name'] ?? '');
        if ($name === '') {
            throw new ValidationException('name', 'Название обязательно для заполнения');
        }
        if (strlen($name) > 255) {
            throw new ValidationException('name', 'Название слишком длинное (максимум 255 символов)');
        }

        $description = trim($payload['description'] ?? '');
        $inn = $this->normalizeInn($payload['inn'] ?? null);
        $phone = $this->sanitizePhoneValue($payload['phone'] ?? null);
        $email = trim($payload['email'] ?? '');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('email', 'Укажите корректный email');
        }
        $color = $this->validateColor($payload['color'] ?? null);

        return [
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'inn' => $inn,
            'phone' => $phone,
            'email' => $email !== '' ? $email : null,
            'color' => $color,
        ];
    }

    /** Normalises phone number to +7XXXXXXXXXX format with validation. */
    private function sanitizePhoneValue(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }
        $input = trim($input);
        if ($input === '') {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $input);
        if (strlen($digits) === 10) {
            $digits = '7' . $digits;
        } elseif (strlen($digits) === 11 && $digits[0] === '8') {
            $digits = '7' . substr($digits, 1);
        }
        if (strlen($digits) !== 11 || $digits[0] !== '7') {
            throw new ValidationException('phone', 'Телефон должен быть в формате +7XXXXXXXXXX');
        }
        return '+' . $digits;
    }

    /** Validates INN to be either 10 or 12 digits. */
    private function normalizeInn(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $value);
        if (!in_array(strlen($digits), [10, 12], true)) {
            throw new ValidationException('inn', 'ИНН должен содержать 10 или 12 цифр');
        }
        return $digits;
    }

    /** Validates that selected colour belongs to the palette. */
    private function validateColor(?string $color): ?string
    {
        if ($color === null) {
            return null;
        }
        $color = trim($color);
        if ($color === '') {
            return null;
        }
        if (!array_key_exists($color, $this->getColorPalette())) {
            throw new ValidationException('color', 'Выберите цвет из предустановленной палитры');
        }
        return $color;
    }

    /** Hard-coded palette used on the front-end. */
    private function getColorPalette(): array
    {
        return [
            '#0d6efd' => 'Синий',
            '#198754' => 'Зелёный',
            '#fd7e14' => 'Оранжевый',
            '#0dcaf0' => 'Бирюзовый',
            '#6f42c1' => 'Фиолетовый',
            '#d63384' => 'Розовый',
            '#adb5bd' => 'Серый',
            '#343a40' => 'Графитовый',
        ];
    }

    /** Ensures directory for uploaded counterparty documents exists. */
    private function ensureUploadDirectory(): string
    {
        $path = __DIR__ . '/../../uploads/counterparties';
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
        return realpath($path) ?: $path;
    }

    /** Resolves relative storage path to absolute file system path. */
    private function resolveStoragePath(?string $relativePath): ?string
    {
        if (!$relativePath) {
            return null;
        }
        return __DIR__ . '/../../' . ltrim($relativePath, '/\\');
    }

    /** Formats timestamps for front-end consumption. */
    private function formatDateTime(string $value): string
    {
        return date('d.m.Y H:i', strtotime($value));
    }
}
