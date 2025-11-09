<?php
/**
 * API для управления новостями
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_log.php';

requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

try {
    $pdo = getDBConnection();
    $isAdmin = isAdmin();

    switch ($action) {
        case 'list':
            requireAdmin();

            $stmt = $pdo->query("
                SELECT n.id,
                       n.title,
                       n.published_at,
                       n.created_at,
                       n.updated_at,
                       u.full_name AS author_full_name,
                       u.login AS author_login
                FROM news n
                LEFT JOIN users u ON u.id = n.author_id
                ORDER BY n.published_at DESC, n.id DESC
            ");

            echo json_encode([
                'success' => true,
                'data' => $stmt->fetchAll()
            ]);
            break;

        case 'get':
            requireAdmin();
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id <= 0) {
                throw new Exception('ID новости не указан');
            }

            $stmt = $pdo->prepare("
                SELECT n.*, u.full_name AS author_full_name, u.login AS author_login
                FROM news n
                LEFT JOIN users u ON u.id = n.author_id
                WHERE n.id = ?
            ");
            $stmt->execute([$id]);
            $news = $stmt->fetch();

            if (!$news) {
                throw new Exception('Новость не найдена');
            }

            echo json_encode([
                'success' => true,
                'data' => $news
            ]);
            break;

        case 'create':
            requireAdmin();
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new Exception('Пустые данные');
            }

            $title = trim($data['title'] ?? '');
            $content = trim($data['content'] ?? '');
            $publishedAt = trim($data['published_at'] ?? '');

            if ($title === '') {
                throw new Exception('Заголовок обязателен');
            }
            if ($content === '') {
                throw new Exception('Текст новости обязателен');
            }
            if ($publishedAt === '') {
                $publishedAt = date('Y-m-d H:i:s');
            } else {
                $publishedAt = date('Y-m-d H:i:s', strtotime($publishedAt));
            }

            $authorId = getCurrentUserId();

            $stmt = $pdo->prepare("
                INSERT INTO news (title, content, published_at, author_id)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$title, $content, $publishedAt, $authorId]);

            $newsId = (int)$pdo->lastInsertId();

            logActivity('create', 'news', $newsId, 'Добавлена новость', [
                'title' => $title,
                'published_at' => $publishedAt
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Новость добавлена',
                'id' => $newsId
            ]);
            break;

        case 'update':
            requireAdmin();
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new Exception('Пустые данные');
            }

            $id = isset($data['id']) ? (int)$data['id'] : 0;
            if ($id <= 0) {
                throw new Exception('ID новости не указан');
            }

            $stmt = $pdo->prepare("SELECT * FROM news WHERE id = ?");
            $stmt->execute([$id]);
            $existing = $stmt->fetch();
            if (!$existing) {
                throw new Exception('Новость не найдена');
            }

            $title = trim($data['title'] ?? '');
            $content = trim($data['content'] ?? '');
            $publishedAt = trim($data['published_at'] ?? '');

            if ($title === '') {
                throw new Exception('Заголовок обязателен');
            }
            if ($content === '') {
                throw new Exception('Текст новости обязателен');
            }
            if ($publishedAt === '') {
                $publishedAt = $existing['published_at'];
            } else {
                $publishedAt = date('Y-m-d H:i:s', strtotime($publishedAt));
            }

            $stmt = $pdo->prepare("
                UPDATE news
                SET title = ?, content = ?, published_at = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$title, $content, $publishedAt, $id]);

            logActivity('update', 'news', $id, 'Обновлена новость', [
                'title' => ['old' => $existing['title'], 'new' => $title],
                'published_at' => ['old' => $existing['published_at'], 'new' => $publishedAt]
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Новость обновлена'
            ]);
            break;

        case 'delete':
            requireAdmin();
            if ($method !== 'POST') {
                throw new Exception('Метод не поддерживается');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            if ($id <= 0) {
                throw new Exception('ID новости не указан');
            }

            $stmt = $pdo->prepare("SELECT title FROM news WHERE id = ?");
            $stmt->execute([$id]);
            $news = $stmt->fetch();
            if (!$news) {
                throw new Exception('Новость не найдена');
            }

            $stmt = $pdo->prepare("DELETE FROM news WHERE id = ?");
            $stmt->execute([$id]);

            logActivity('delete', 'news', $id, 'Удалена новость', [
                'title' => $news['title']
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Новость удалена'
            ]);
            break;

        case 'latest':
            // доступен всем авторизованным
            $stmt = $pdo->query("
                SELECT n.id,
                       n.title,
                       n.content,
                       n.published_at,
                       u.full_name AS author_full_name,
                       u.login AS author_login
                FROM news n
                LEFT JOIN users u ON u.id = n.author_id
                ORDER BY n.published_at DESC, n.id DESC
                LIMIT 1
            ");
            $news = $stmt->fetch();

            echo json_encode([
                'success' => true,
                'data' => $news ?: null
            ]);
            break;

        default:
            throw new Exception('Неизвестное действие');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

