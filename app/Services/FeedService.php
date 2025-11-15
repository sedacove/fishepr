<?php

namespace App\Services;

use App\Models\Feed\Feed;
use App\Models\Feed\FeedNormImage;
use App\Repositories\FeedNormImageRepository;
use App\Repositories\FeedRepository;
use App\Support\Exceptions\ValidationException;
use App\Support\FeedingFormula;
use DomainException;
use InvalidArgumentException;
use PDO;
use RuntimeException;

class FeedService
{
    private const UPLOAD_DIR = 'uploads/feeds';
    private const ALLOWED_MIME = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/svg+xml',
    ];

    private FeedRepository $feeds;
    private FeedNormImageRepository $images;

    public function __construct(PDO $pdo)
    {
        $this->feeds = new FeedRepository($pdo);
        $this->images = new FeedNormImageRepository($pdo);
    }

    public function list(): array
    {
        $items = $this->feeds->listAll();
        if (empty($items)) {
            return [];
        }

        return array_map(static function (array $row) {
            $feed = new Feed($row);
            $data = $feed->toArray();
            $data['images_count'] = (int)($row['images_count'] ?? 0);
            return $data;
        }, $items);
    }

    public function get(int $id): array
    {
        $feedData = $this->feeds->findById($id);
        if (!$feedData) {
            throw new RuntimeException('Корм не найден', 404);
        }

        $images = $this->images->getByFeed($id);
        $imageModels = [];
        foreach ($images as $image) {
            $model = new FeedNormImage($image);
            $imageModels[] = array_merge($model->toArray(), [
                'url' => $this->buildPublicUrl($model->file_path),
            ]);
        }

        $feed = new Feed($feedData);
        $feed->norm_images = $imageModels;

        return $feed->toArray();
    }

    public function create(array $payload, int $userId): int
    {
        $data = $this->validatePayload($payload);
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;

        $id = $this->feeds->insert($data);

        if (function_exists('logActivity')) {
            \logActivity('create', 'feed', $id, 'Создан новый корм: ' . $data['name'], $data);
        }

        return $id;
    }

    public function update(int $id, array $payload, int $userId): void
    {
        $existing = $this->feeds->findById($id);
        if (!$existing) {
            throw new RuntimeException('Корм не найден', 404);
        }

        $data = $this->validatePayload($payload);
        $data['updated_by'] = $userId;
        $this->feeds->update($id, $data);

        if (function_exists('logActivity')) {
            \logActivity('update', 'feed', $id, 'Обновлен корм: ' . $existing['name'], $data);
        }
    }

    public function delete(int $id): void
    {
        $existing = $this->feeds->findById($id);
        if (!$existing) {
            throw new RuntimeException('Корм не найден', 404);
        }

        $paths = $this->images->deleteByFeed($id);
        foreach ($paths as $relativePath) {
            $this->removePhysicalFile($relativePath);
        }

        $this->feeds->delete($id);

        if (function_exists('logActivity')) {
            \logActivity('delete', 'feed', $id, 'Удален корм: ' . ($existing['name'] ?? ''), $existing);
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function uploadNormImages(int $feedId, array $files, int $userId): array
    {
        $feed = $this->feeds->findById($feedId);
        if (!$feed) {
            throw new RuntimeException('Корм не найден', 404);
        }

        $normalizedFiles = $this->normalizeFilesArray($files);
        if (empty($normalizedFiles)) {
            throw new DomainException('Файлы не переданы');
        }

        $uploadDir = $this->ensureUploadDirectory();
        $saved = [];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        foreach ($normalizedFiles as $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }

            $tmpPath = $file['tmp_name'] ?? null;
            if (!$tmpPath || !is_uploaded_file($tmpPath)) {
                continue;
            }

            $mime = $finfo ? finfo_file($finfo, $tmpPath) : ($file['type'] ?? null);
            if ($mime && !in_array($mime, self::ALLOWED_MIME, true)) {
                continue;
            }

            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = uniqid('feed_', true) . ($extension ? '.' . $extension : '');
            $relativePath = self::UPLOAD_DIR . '/' . $fileName;
            $absolutePath = $uploadDir . $fileName;

            if (!move_uploaded_file($tmpPath, $absolutePath)) {
                continue;
            }

            $imageId = $this->images->insert([
                'feed_id' => $feedId,
                'original_name' => $file['name'],
                'file_name' => $fileName,
                'file_path' => $relativePath,
                'file_size' => (int)($file['size'] ?? 0),
                'mime_type' => $mime,
                'uploaded_by' => $userId,
            ]);

            $saved[] = [
                'id' => $imageId,
                'original_name' => $file['name'],
                'url' => $this->buildPublicUrl($relativePath),
                'file_size' => (int)($file['size'] ?? 0),
            ];
        }

        if ($finfo) {
            finfo_close($finfo);
        }

        if (empty($saved)) {
            throw new RuntimeException('Не удалось загрузить изображения');
        }

        return $saved;
    }

    public function deleteImage(int $imageId): void
    {
        $image = $this->images->delete($imageId);
        if (!$image) {
            throw new RuntimeException('Изображение не найдено', 404);
        }

        $this->removePhysicalFile($image['file_path'] ?? '');
    }

    public function options(): array
    {
        return $this->feeds->options();
    }

    private function validatePayload(array $payload): array
    {
        $name = trim((string)($payload['name'] ?? ''));
        if ($name === '') {
            throw new ValidationException('name', 'Название обязательно для заполнения');
        }

        $granule = trim((string)($payload['granule'] ?? ''));
        $manufacturer = trim((string)($payload['manufacturer'] ?? ''));
        $description = trim((string)($payload['description'] ?? ''));

        return [
            'name' => $name,
            'granule' => $granule !== '' ? $granule : null,
            'description' => $description !== '' ? $description : null,
            'formula_econom' => $this->validateFormula($payload['formula_econom'] ?? null, 'formula_econom'),
            'formula_normal' => $this->validateFormula($payload['formula_normal'] ?? null, 'formula_normal'),
            'formula_growth' => $this->validateFormula($payload['formula_growth'] ?? null, 'formula_growth'),
            'manufacturer' => $manufacturer !== '' ? $manufacturer : null,
        ];
    }

    private function normalizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim($value);
        return $text === '' ? null : $text;
    }

    private function validateFormula(?string $value, string $field): ?string
    {
        $normalized = FeedingFormula::normalize($value);
        if ($normalized === null) {
            return null;
        }

        try {
            FeedingFormula::validate($normalized);
        } catch (InvalidArgumentException $e) {
            throw new ValidationException($field, $e->getMessage());
        }

        return $normalized;
    }

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
            if (($filesInput['name'][$i] ?? '') === '') {
                continue;
            }
            $files[] = [
                'name' => $filesInput['name'][$i],
                'type' => $filesInput['type'][$i] ?? null,
                'tmp_name' => $filesInput['tmp_name'][$i] ?? null,
                'error' => $filesInput['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $filesInput['size'][$i] ?? 0,
            ];
        }

        return $files;
    }

    private function ensureUploadDirectory(): string
    {
        $basePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
        $uploadDir = $basePath . str_replace('/', DIRECTORY_SEPARATOR, self::UPLOAD_DIR);
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        return rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    private function removePhysicalFile(?string $relativePath): void
    {
        if (!$relativePath) {
            return;
        }

        $basePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
        $absolutePath = $basePath . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relativePath, '/\\'));
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    private function buildPublicUrl(string $relativePath): string
    {
        $base = rtrim(BASE_URL, '/');
        return $base . '/' . ltrim($relativePath, '/');
    }
}

