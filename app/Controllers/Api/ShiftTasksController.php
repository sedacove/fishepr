<?php

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Services\ShiftTaskService;
use App\Support\JsonResponse;
use App\Support\Request;
use DomainException;
use RuntimeException;

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/duty_helpers.php';

class ShiftTasksController extends Controller
{
    private ShiftTaskService $service;

    public function __construct()
    {
        requireAuth();
        $this->service = new ShiftTaskService(getDBConnection());
    }

    public function handle(Request $request): void
    {
        $action = $request->getQuery('action', 'list');

        try {
            switch ($action) {
                case 'list':
                    $this->handleList($request);
                    return;
                case 'toggle':
                    $this->handleToggle($request);
                    return;
                case 'templates':
                    $this->ensureAdmin();
                    $this->handleTemplates();
                    return;
                case 'save_template':
                    $this->ensureAdmin();
                    $this->handleSaveTemplate($request);
                    return;
                case 'delete_template':
                    $this->ensureAdmin();
                    $this->handleDeleteTemplate($request);
                    return;
                case 'reorder_templates':
                    $this->ensureAdmin();
                    $this->handleReorderTemplates($request);
                    return;
                default:
                    JsonResponse::error('Неизвестное действие', 400);
            }
        } catch (DomainException $exception) {
            JsonResponse::error($exception->getMessage(), 403);
        } catch (RuntimeException $exception) {
            JsonResponse::error($exception->getMessage(), 400);
        } catch (\Throwable $exception) {
            JsonResponse::error('Внутренняя ошибка: ' . $exception->getMessage(), 500);
        }
    }

    private function handleList(Request $request): void
    {
        $shiftDate = $request->getQuery('date') ?: getTodayDutyDate();
        $tasks = $this->service->listForShift($shiftDate);
        JsonResponse::success([
            'date' => $shiftDate,
            'tasks' => $tasks,
            'is_admin' => isAdmin(),
        ]);
    }

    private function handleToggle(Request $request): void
    {
        if (!$request->isMethod('POST')) {
            throw new DomainException('Метод не поддерживается');
        }
        $payload = $request->getJsonBody();

        $taskId = (int)($payload['task_id'] ?? 0);
        $completed = !empty($payload['completed']);
        $note = isset($payload['note']) ? trim((string)$payload['note']) : null;

        if ($taskId <= 0) {
            throw new DomainException('Некорректный идентификатор задания');
        }

        $result = $this->service->toggleCompletion(
            $taskId,
            $completed,
            getCurrentUserId(),
            isAdmin(),
            $note ?: null
        );

        JsonResponse::success(['task' => $result]);
    }

    private function handleTemplates(): void
    {
        $templates = $this->service->listTemplates();
        JsonResponse::success(['templates' => $templates]);
    }

    private function handleSaveTemplate(Request $request): void
    {
        if (!$request->isMethod('POST')) {
            throw new DomainException('Метод не поддерживается');
        }
        $payload = $request->getJsonBody();
        $templateId = isset($payload['id']) ? (int)$payload['id'] : null;
        if ($templateId !== null && $templateId <= 0) {
            $templateId = null;
        }

        $id = $this->service->saveTemplate($templateId, $payload, getCurrentUserId());
        $template = $this->service->findTemplate($id);

        JsonResponse::success([
            'id' => $id,
            'template' => $template,
        ]);
    }

    private function handleDeleteTemplate(Request $request): void
    {
        if (!$request->isMethod('POST')) {
            throw new DomainException('Метод не поддерживается');
        }
        $payload = $request->getJsonBody();
        $templateId = (int)($payload['id'] ?? 0);

        if ($templateId <= 0) {
            throw new DomainException('Некорректный идентификатор шаблона');
        }

        $this->service->deleteTemplate($templateId);
        JsonResponse::success(['deleted' => true]);
    }

    private function handleReorderTemplates(Request $request): void
    {
        if (!$request->isMethod('POST')) {
            throw new DomainException('Метод не поддерживается');
        }
        $payload = $request->getJsonBody();
        $order = $payload['order'] ?? [];
        $this->service->reorderTemplates($order);
        JsonResponse::success(['sorted' => true]);
    }

    private function ensureAdmin(): void
    {
        if (!isAdmin()) {
            throw new DomainException('Доступ запрещен');
        }
    }
}


