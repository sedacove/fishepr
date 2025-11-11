<?php

namespace App\Controllers\Api;

use App\Services\CounterpartyService;
use App\Support\Exceptions\ValidationException;
use App\Support\JsonResponse;
use App\Support\Request;
use DomainException;
use Exception;
use RuntimeException;

/**
 * Thin API controller that proxies requests to CounterpartyService.
 */
class CounterpartiesController
{
    private CounterpartyService $service;

    public function __construct()
    {
        requireAdmin();
        $this->service = new CounterpartyService(\getDBConnection());
    }

    /**
     * Dispatches action based on `action` query parameter.
     */
    public function handle(Request $request): void
    {
        $action = $request->getQuery('action', 'list');

        try {
            switch ($action) {
                case 'palette':
                    JsonResponse::success($this->service->palette());
                    break;
                case 'list':
                    JsonResponse::success($this->service->list());
                    break;
                case 'get':
                    $id = (int)$request->getQuery('id', 0);
                    if ($id <= 0) {
                        throw new DomainException('ID контрагента не указан');
                    }
                    JsonResponse::success($this->service->get($id));
                    break;
                case 'create':
                    $this->requirePost($request);
                    $payload = $request->getJsonBody();
                    $id = $this->service->create($payload, \getCurrentUserId());
                    JsonResponse::success(['id' => $id], 'Контрагент успешно создан');
                    break;
                case 'update':
                    $this->requirePost($request);
                    $payload = $request->getJsonBody();
                    $id = (int)($payload['id'] ?? 0);
                    if ($id <= 0) {
                        throw new DomainException('ID контрагента не указан');
                    }
                    $this->service->update($id, $payload, \getCurrentUserId());
                    JsonResponse::success([], 'Контрагент успешно обновлён');
                    break;
                case 'delete':
                    $this->requirePost($request);
                    $payload = $request->getJsonBody();
                    $id = (int)($payload['id'] ?? 0);
                    if ($id <= 0) {
                        throw new DomainException('ID контрагента не указан');
                    }
                    $this->service->delete($id);
                    JsonResponse::success([], 'Контрагент удалён');
                    break;
                case 'upload_document':
                    $this->handleUpload();
                    break;
                case 'delete_document':
                    $this->requirePost($request);
                    $payload = $request->getJsonBody();
                    $documentId = (int)($payload['id'] ?? 0);
                    if ($documentId <= 0) {
                        throw new DomainException('ID документа не указан');
                    }
                    $this->service->deleteDocument($documentId);
                    JsonResponse::success([], 'Документ удалён');
                    break;
                default:
                    throw new DomainException('Неизвестное действие');
            }
        } catch (ValidationException $e) {
            JsonResponse::send([
                'success' => false,
                'message' => $e->getMessage(),
                'field' => $e->getField(),
            ], $e->getCode() ?: 422);
        } catch (DomainException | RuntimeException $e) {
            JsonResponse::error($e->getMessage(), $e->getCode() ?: 400);
        } catch (Exception $e) {
            JsonResponse::error($e->getMessage(), 500);
        }
    }

    /** Handles file upload via multipart form submission. */
    private function handleUpload(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            throw new DomainException('Метод не поддерживается');
        }

        $counterpartyId = isset($_POST['counterparty_id']) ? (int)$_POST['counterparty_id'] : 0;
        if ($counterpartyId <= 0) {
            throw new DomainException('ID контрагента не указан');
        }

        if (!isset($_FILES['files'])) {
            throw new DomainException('Файлы не переданы');
        }

        $saved = $this->service->uploadDocuments($counterpartyId, $_FILES['files'], \getCurrentUserId());
        JsonResponse::success(['files' => $saved], 'Файлы успешно загружены');
    }

    /** Ensures request method is POST, otherwise throws exception. */
    private function requirePost(Request $request): void
    {
        if (!$request->isMethod('POST')) {
            throw new DomainException('Метод не поддерживается');
        }
    }
}
