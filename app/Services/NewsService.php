<?php

namespace App\Services;

use App\Repositories\NewsRepository;
use DomainException;
use PDO;
use RuntimeException;

class NewsService
{
    private NewsRepository $news;
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->news = new NewsRepository($pdo);
    }

    public function listNews(): array
    {
        return $this->news->getAll();
    }

    public function getNews(int $id): array
    {
        $news = $this->news->find($id);
        if (!$news) {
            throw new RuntimeException('Новость не найдена');
        }
        return $news;
    }

    public function getLatestNews(): ?array
    {
        return $this->news->getLatest();
    }

    public function createNews(array $payload, int $authorId): int
    {
        $title = trim($payload['title'] ?? '');
        $content = trim($payload['content'] ?? '');
        $publishedAt = $this->normalizePublishedAt($payload['published_at'] ?? null);

        if ($title === '') {
            throw new DomainException('Заголовок обязателен');
        }
        if ($content === '') {
            throw new DomainException('Текст новости обязателен');
        }

        $newsId = $this->news->insert($title, $content, $publishedAt, $authorId);

        if (\function_exists('logActivity')) {
            \logActivity('create', 'news', $newsId, 'Добавлена новость', [
                'title' => $title,
                'published_at' => $publishedAt,
            ]);
        }

        return $newsId;
    }

    public function updateNews(array $payload): void
    {
        $id = (int)($payload['id'] ?? 0);
        if ($id <= 0) {
            throw new DomainException('ID новости не указан');
        }

        $existing = $this->news->find($id);
        if (!$existing) {
            throw new RuntimeException('Новость не найдена');
        }

        $title = trim($payload['title'] ?? '');
        $content = trim($payload['content'] ?? '');
        $publishedAt = $payload['published_at'] ?? $existing['published_at'];
        $publishedAt = $this->normalizePublishedAt($publishedAt, $existing['published_at']);

        if ($title === '') {
            throw new DomainException('Заголовок обязателен');
        }
        if ($content === '') {
            throw new DomainException('Текст новости обязателен');
        }

        $this->news->update($id, $title, $content, $publishedAt);

        if (\function_exists('logActivity')) {
            \logActivity('update', 'news', $id, 'Обновлена новость', [
                'title' => ['old' => $existing['title'], 'new' => $title],
                'published_at' => ['old' => $existing['published_at'], 'new' => $publishedAt],
            ]);
        }
    }

    public function deleteNews(int $id): void
    {
        $existing = $this->news->find($id);
        if (!$existing) {
            throw new RuntimeException('Новость не найдена');
        }
        $this->news->delete($id);

        if (\function_exists('logActivity')) {
            \logActivity('delete', 'news', $id, 'Удалена новость', [
                'title' => $existing['title'],
            ]);
        }
    }

    private function normalizePublishedAt($value, ?string $fallback = null): string
    {
        if (empty($value)) {
            return $fallback ? \date('Y-m-d H:i:s', \strtotime($fallback)) : \date('Y-m-d H:i:s');
        }

        $timestamp = \strtotime((string)$value);
        if ($timestamp === false) {
            throw new DomainException('Некорректная дата публикации');
        }
        return \date('Y-m-d H:i:s', $timestamp);
    }
}
