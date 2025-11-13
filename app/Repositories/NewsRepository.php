<?php

namespace App\Repositories;

use PDO;

/**
 * Репозиторий для работы с новостями
 * 
 * Выполняет SQL запросы к таблице news:
 * - получение списка всех новостей
 * - поиск новости по ID
 * - создание, обновление, удаление новостей
 * - получение последней опубликованной новости
 */
class NewsRepository extends Repository
{
    /**
     * Получает список всех новостей
     * 
     * @return array Массив новостей, отсортированных по дате публикации (от новых к старым)
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query(<<<SQL
            SELECT n.id,
                   n.title,
                   n.content,
                   n.published_at,
                   n.created_at,
                   n.updated_at,
                   n.author_id,
                   u.full_name AS author_full_name,
                   u.login AS author_login
            FROM news n
            LEFT JOIN users u ON u.id = n.author_id
            ORDER BY n.published_at DESC, n.id DESC
        SQL);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Находит новость по ID
     * 
     * @param int $id ID новости
     * @return array|null Данные новости с информацией об авторе или null, если не найдена
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT n.id,
                   n.title,
                   n.content,
                   n.published_at,
                   n.created_at,
                   n.updated_at,
                   n.author_id,
                   u.full_name AS author_full_name,
                   u.login AS author_login
            FROM news n
            LEFT JOIN users u ON u.id = n.author_id
            WHERE n.id = ?
        SQL);
        $stmt->execute([$id]);
        $news = $stmt->fetch(PDO::FETCH_ASSOC);
        return $news ?: null;
    }

    /**
     * Создает новую новость
     * 
     * @param string $title Заголовок новости
     * @param string $content Текст новости
     * @param string $publishedAt Дата публикации
     * @param int $authorId ID автора новости
     * @return int ID созданной новости
     */
    public function insert(string $title, string $content, string $publishedAt, int $authorId): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO news (title, content, published_at, author_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $content, $publishedAt, $authorId]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Обновляет новость
     * 
     * @param int $id ID новости
     * @param string $title Заголовок новости
     * @param string $content Текст новости
     * @param string $publishedAt Дата публикации
     * @return void
     */
    public function update(int $id, string $title, string $content, string $publishedAt): void
    {
        $stmt = $this->pdo->prepare("UPDATE news SET title = ?, content = ?, published_at = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$title, $content, $publishedAt, $id]);
    }

    /**
     * Удаляет новость
     * 
     * @param int $id ID новости для удаления
     * @return void
     */
    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM news WHERE id = ?");
        $stmt->execute([$id]);
    }

    /**
     * Получает последнюю опубликованную новость
     * 
     * @return array|null Данные последней новости с информацией об авторе или null, если новостей нет
     */
    public function getLatest(): ?array
    {
        $stmt = $this->pdo->query(<<<SQL
            SELECT n.id,
                   n.title,
                   n.content,
                   n.published_at,
                   n.author_id,
                   u.full_name AS author_full_name,
                   u.login AS author_login
            FROM news n
            LEFT JOIN users u ON u.id = n.author_id
            ORDER BY n.published_at DESC, n.id DESC
            LIMIT 1
        SQL);
        $news = $stmt->fetch(PDO::FETCH_ASSOC);
        return $news ?: null;
    }
}
