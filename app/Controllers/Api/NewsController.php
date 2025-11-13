<?php

namespace App\Controllers\Api;

use App\Services\NewsService;
use App\Support\JsonResponse;
use App\Support\Request;
use DomainException;
use Exception;
use RuntimeException;

/**
 * API контроллер для работы с новостями
 * 
 * Обрабатывает HTTP запросы к API endpoints для новостей:
 * - list: получение списка всех новостей (только для админов)
 * - get: получение одной новости (только для админов)
 * - create: создание новой новости (только для админов)
 * - update: обновление новости (только для админов)
 * - delete: удаление новости (только для админов)
 * - latest: получение последней опубликованной новости (доступно всем авторизованным)
 * 
 * Авторизация проверяется в api/news.php
 */
class NewsController
{
    /**
     * @var NewsService Сервис для работы с новостями
     */
    private NewsService $service;
    
    /**
     * @var int ID текущего пользователя
     */
    private int $userId;
    
    /**
     * @var bool Является ли пользователь администратором
     */
    private bool $isAdmin;

    /**
     * Конструктор контроллера
     * 
     * Инициализирует сервис и получает информацию о текущем пользователе.
     * Авторизация проверяется в api/news.php.
     */
    public function __construct()
    {
        // Авторизация проверяется в api/news.php
        $pdo = \getDBConnection();
        $this->service = new NewsService($pdo);
        $this->userId = \getCurrentUserId();
        $this->isAdmin = \isAdmin();
    }

    /**
     * Обрабатывает входящий запрос и направляет его к соответствующему обработчику
     * 
     * @param Request $request Объект запроса
     * @return void
     */
    public function handle(Request $request): void
    {
        $action = $request->getQuery('action', 'list');

        try {
            switch ($action) {
                case 'list':
                    $this->ensureAdmin();
                    $news = $this->service->listNews();
                    JsonResponse::success($news);
                    break;
                case 'get':
                    $this->ensureAdmin();
                    $this->handleGet($request);
                    break;
                case 'create':
                    $this->ensureAdmin();
                    $this->requirePost($request);
                    $this->handleCreate($request);
                    break;
                case 'update':
                    $this->ensureAdmin();
                    $this->requirePost($request);
                    $this->handleUpdate($request);
                    break;
                case 'delete':
                    $this->ensureAdmin();
                    $this->requirePost($request);
                    $this->handleDelete($request);
                    break;
                case 'latest':
                    $this->handleLatest();
                    break;
                default:
                    throw new Exception('Неизвестное действие');
            }
        } catch (DomainException|RuntimeException $e) {
            JsonResponse::error($e->getMessage(), 400);
        } catch (Exception $e) {
            JsonResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * Обрабатывает запрос на получение одной новости
     * 
     * @param Request $request Объект запроса
     * @return void
     * @throws DomainException Если ID новости не указан
     */
    private function handleGet(Request $request): void
    {
        $id = (int)$request->getQuery('id', 0);
        if ($id <= 0) {
            throw new DomainException('ID новости не указан');
        }
        $news = $this->service->getNews($id);
        JsonResponse::success($news);
    }

    /**
     * Обрабатывает запрос на создание новой новости
     * 
     * @param Request $request Объект запроса
     * @return void
     */
    private function handleCreate(Request $request): void
    {
        $payload = $request->getJsonBody();
        $newsId = $this->service->createNews($payload, $this->userId);
        JsonResponse::success(['id' => $newsId], 'Новость добавлена');
    }

    /**
     * Обрабатывает запрос на обновление новости
     * 
     * @param Request $request Объект запроса
     * @return void
     */
    private function handleUpdate(Request $request): void
    {
        $payload = $request->getJsonBody();
        $this->service->updateNews($payload);
        JsonResponse::success([], 'Новость обновлена');
    }

    /**
     * Обрабатывает запрос на удаление новости
     * 
     * @param Request $request Объект запроса
     * @return void
     * @throws DomainException Если ID новости не указан
     */
    private function handleDelete(Request $request): void
    {
        $payload = $request->getJsonBody();
        $id = (int)($payload['id'] ?? 0);
        if ($id <= 0) {
            throw new DomainException('ID новости не указан');
        }
        $this->service->deleteNews($id);
        JsonResponse::success([], 'Новость удалена');
    }

    /**
     * Обрабатывает запрос на получение последней новости
     * 
     * Доступно всем авторизованным пользователям (не только админам).
     * 
     * @return void
     */
    private function handleLatest(): void
    {
        $news = $this->service->getLatestNews();
        JsonResponse::success($news ?: null);
    }

    /**
     * Проверяет, что текущий пользователь является администратором
     * 
     * @return void
     * @throws DomainException Если пользователь не является администратором
     */
    private function ensureAdmin(): void
    {
        if (!$this->isAdmin) {
            throw new DomainException('Доступ запрещен');
        }
    }

    /**
     * Проверяет, что запрос использует метод POST
     * 
     * @param Request $request Объект запроса
     * @return void
     * @throws DomainException Если метод не POST
     */
    private function requirePost(Request $request): void
    {
        if (!$request->isMethod('POST')) {
            throw new DomainException('Метод не поддерживается');
        }
    }
}
