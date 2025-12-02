<?php

namespace App\Services;

use App\Models\PartialTransplant\PartialTransplant;
use App\Repositories\MixedPlantingRepository;
use App\Repositories\PartialTransplantRepository;
use App\Repositories\PlantingRepository;
use App\Repositories\SessionRepository;
use DomainException;
use PDO;
use RuntimeException;

require_once __DIR__ . '/../../includes/activity_log.php';

/**
 * Сервис для работы с частичными пересадками биомассы между сессиями
 * 
 * Содержит бизнес-логику для работы с пересадками:
 * - валидация данных пересадки
 * - вычитание биомассы из сессии-отбора
 * - добавление биомассы в сессию-реципиент
 * - откат пересадки (возврат биомассы)
 */
class PartialTransplantService
{
    /**
     * @var PartialTransplantRepository Репозиторий для работы с пересадками
     */
    private PartialTransplantRepository $transplants;

    /**
     * @var SessionRepository Репозиторий для работы с сессиями
     */
    private SessionRepository $sessions;

    /**
     * @var MixedPlantingRepository Репозиторий для работы с микстовыми посадками
     */
    private MixedPlantingRepository $mixedPlantings;

    /**
     * @var PlantingRepository Репозиторий для работы с посадками
     */
    private PlantingRepository $plantings;

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
        $this->transplants = new PartialTransplantRepository($pdo);
        $this->sessions = new SessionRepository($pdo);
        $this->mixedPlantings = new MixedPlantingRepository($pdo);
        $this->plantings = new PlantingRepository($pdo);
    }

    /**
     * Получает список всех пересадок
     * 
     * @return array Массив моделей пересадок
     */
    public function list(): array
    {
        $rows = $this->transplants->findAll();
        return array_map(function ($row) {
            return new PartialTransplant($row);
        }, $rows);
    }

    /**
     * Получает пересадку по ID
     * 
     * @param int $id ID пересадки
     * @return PartialTransplant Модель пересадки
     * @throws RuntimeException Если пересадка не найдена
     */
    public function get(int $id): PartialTransplant
    {
        $row = $this->transplants->find($id);
        if (!$row) {
            throw new RuntimeException('Пересадка не найдена');
        }
        return new PartialTransplant($row);
    }

    /**
     * Создает новую пересадку и обновляет биомассу в сессиях
     * 
     * Валидация:
     * - сессии должны быть указаны и существовать
     * - сессии должны быть разными
     * - вес и количество должны быть положительными
     * - в сессии-отборе должно быть достаточно биомассы
     * 
     * @param array $payload Данные пересадки
     * @param int $userId ID пользователя, создающего пересадку
     * @return int ID созданной пересадки
     * @throws DomainException Если данные некорректны или валидация не пройдена
     */
    public function createTransplant(array $payload, int $userId): int
    {
        // Валидация данных
        $this->validatePayload($payload);

        $sourceSessionId = (int)$payload['source_session_id'];
        $recipientSessionId = (int)$payload['recipient_session_id'];
        $weight = (float)$payload['weight'];
        $fishCount = (int)$payload['fish_count'];
        $transplantDate = $payload['transplant_date'];

        // Проверка, что сессии разные
        if ($sourceSessionId === $recipientSessionId) {
            throw new DomainException('Сессия отбора и сессия реципиент должны быть разными');
        }

        // Получаем сессии
        $sourceSession = $this->sessions->find($sourceSessionId);
        if (!$sourceSession) {
            throw new DomainException('Сессия отбора не найдена');
        }

        $recipientSession = $this->sessions->find($recipientSessionId);
        if (!$recipientSession) {
            throw new DomainException('Сессия реципиент не найдена');
        }

        // Проверка, что сессии не завершены
        if ($sourceSession->is_completed) {
            throw new DomainException('Нельзя выполнить пересадку из завершенной сессии');
        }
        if ($recipientSession->is_completed) {
            throw new DomainException('Нельзя выполнить пересадку в завершенную сессию');
        }

        // Проверка достаточности биомассы в сессии-отборе
        // Вычисляем текущую биомассу: начальная масса - отборы + пересадки в - пересадки из
        $currentSourceMass = $this->calculateCurrentMass($sourceSessionId);
        $currentSourceCount = $this->calculateCurrentFishCount($sourceSessionId);

        if ($weight > $currentSourceMass) {
            throw new DomainException(
                sprintf(
                    'Недостаточно биомассы в сессии отбора. Доступно: %.2f кг, требуется: %.2f кг',
                    $currentSourceMass,
                    $weight
                )
            );
        }

        if ($fishCount > $currentSourceCount) {
            throw new DomainException(
                sprintf(
                    'Недостаточно особей в сессии отбора. Доступно: %d, требуется: %d',
                    $currentSourceCount,
                    $fishCount
                )
            );
        }

        // Проверяем, нужно ли создавать микстовую посадку
        $mixedPlantingId = $this->handleMixedPlanting($sourceSession, $recipientSession, $weight, $fishCount, $userId);

        // Создаем запись пересадки
        $transplantId = $this->transplants->insert([
            'transplant_date' => $transplantDate,
            'source_session_id' => $sourceSessionId,
            'recipient_session_id' => $recipientSessionId,
            'weight' => $weight,
            'fish_count' => $fishCount,
            'created_by' => $userId,
        ]);

        // Вычитаем из сессии-отбора
        $this->subtractFromSourceSession($sourceSessionId, $weight, $fishCount);

        // Добавляем в сессию-реципиент
        $this->addToRecipientSession($recipientSessionId, $weight, $fishCount);

        // Если создана микстовая посадка, обновляем сессию-реципиент
        if ($mixedPlantingId !== null) {
            $this->updateSessionMixedPlanting($recipientSessionId, $mixedPlantingId);
        }

        // Логирование
        if (\function_exists('logActivity')) {
            \logActivity('create', 'partial_transplant', $transplantId, 'Создана частичная пересадка', [
                'source_session_id' => $sourceSessionId,
                'recipient_session_id' => $recipientSessionId,
                'weight' => $weight,
                'fish_count' => $fishCount,
            ]);
        }

        return $transplantId;
    }

    /**
     * Откатывает пересадку (возвращает биомассу)
     * 
     * @param int $id ID пересадки
     * @param int $userId ID пользователя, выполняющего откат
     * @return void
     * @throws DomainException Если пересадка уже откатана или сессии завершены
     */
    public function revertTransplant(int $id, int $userId): void
    {
        $transplant = $this->get($id);

        if ($transplant->is_reverted) {
            throw new DomainException('Пересадка уже откатана');
        }

        // Получаем сессии
        $sourceSession = $this->sessions->find($transplant->source_session_id);
        $recipientSession = $this->sessions->find($transplant->recipient_session_id);

        if (!$sourceSession || !$recipientSession) {
            throw new DomainException('Одна из сессий не найдена');
        }

        // Проверка, что сессии не завершены
        if ($sourceSession->is_completed) {
            throw new DomainException('Нельзя откатить пересадку: сессия отбора завершена');
        }
        if ($recipientSession->is_completed) {
            throw new DomainException('Нельзя откатить пересадку: сессия реципиент завершена');
        }

        // Проверка достаточности биомассы в сессии-реципиенте для отката
        $currentRecipientMass = $this->calculateCurrentMass($transplant->recipient_session_id);
        $currentRecipientCount = $this->calculateCurrentFishCount($transplant->recipient_session_id);

        if ($transplant->weight > $currentRecipientMass) {
            throw new DomainException(
                sprintf(
                    'Недостаточно биомассы в сессии реципиент для отката. Доступно: %.2f кг, требуется: %.2f кг',
                    $currentRecipientMass,
                    $transplant->weight
                )
            );
        }

        if ($transplant->fish_count > $currentRecipientCount) {
            throw new DomainException(
                sprintf(
                    'Недостаточно особей в сессии реципиент для отката. Доступно: %d, требуется: %d',
                    $currentRecipientCount,
                    $transplant->fish_count
                )
            );
        }

        // Возвращаем в сессию-отбор
        $this->addToRecipientSession($transplant->source_session_id, $transplant->weight, $transplant->fish_count);

        // Вычитаем из сессии-реципиент
        $this->subtractFromSourceSession($transplant->recipient_session_id, $transplant->weight, $transplant->fish_count);

        // Отмечаем пересадку как откатанную
        $this->transplants->markAsReverted($id, $userId);

        // Логирование
        if (\function_exists('logActivity')) {
            \logActivity('update', 'partial_transplant', $id, 'Откат частичной пересадки', [
                'source_session_id' => $transplant->source_session_id,
                'recipient_session_id' => $transplant->recipient_session_id,
                'weight' => $transplant->weight,
                'fish_count' => $transplant->fish_count,
            ]);
        }
    }

    /**
     * Валидирует данные пересадки
     * 
     * @param array $payload Данные для валидации
     * @return void
     * @throws DomainException Если данные некорректны
     */
    private function validatePayload(array $payload): void
    {
        if (empty($payload['source_session_id'])) {
            throw new DomainException('Выберите сессию отбора');
        }

        if (empty($payload['recipient_session_id'])) {
            throw new DomainException('Выберите сессию реципиент');
        }

        if (empty($payload['transplant_date'])) {
            throw new DomainException('Укажите дату пересадки');
        }

        $weight = isset($payload['weight']) ? (float)$payload['weight'] : null;
        if ($weight === null || $weight <= 0) {
            throw new DomainException('Вес должен быть положительным числом');
        }

        $fishCount = isset($payload['fish_count']) ? (int)$payload['fish_count'] : null;
        if ($fishCount === null || $fishCount <= 0) {
            throw new DomainException('Количество особей должно быть положительным числом');
        }

        // Валидация даты
        $date = $payload['transplant_date'];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new DomainException('Некорректный формат даты');
        }
    }

    /**
     * Вычисляет текущую биомассу сессии с учетом отборов и пересадок
     * 
     * @param int $sessionId ID сессии
     * @return float Текущая биомасса в кг
     */
    private function calculateCurrentMass(int $sessionId): float
    {
        $session = $this->sessions->find($sessionId);
        if (!$session) {
            return 0.0;
        }

        // Начальная масса
        $mass = (float)$session->start_mass;

        // Вычитаем отборы для этой сессии
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT COALESCE(SUM(weight), 0) AS total_harvests
            FROM harvests
            WHERE session_id = ?
        SQL);
        $stmt->execute([$sessionId]);
        $harvests = $stmt->fetch(PDO::FETCH_ASSOC);
        $mass -= (float)($harvests['total_harvests'] ?? 0);

        // Вычитаем пересадки из (где эта сессия - источник)
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT COALESCE(SUM(weight), 0) AS total_out
            FROM partial_transplants
            WHERE source_session_id = ? AND is_reverted = 0
        SQL);
        $stmt->execute([$sessionId]);
        $transplantsOut = $stmt->fetch(PDO::FETCH_ASSOC);
        $mass -= (float)($transplantsOut['total_out'] ?? 0);

        // Добавляем пересадки в (где эта сессия - реципиент)
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT COALESCE(SUM(weight), 0) AS total_in
            FROM partial_transplants
            WHERE recipient_session_id = ? AND is_reverted = 0
        SQL);
        $stmt->execute([$sessionId]);
        $transplantsIn = $stmt->fetch(PDO::FETCH_ASSOC);
        $mass += (float)($transplantsIn['total_in'] ?? 0);

        return max(0.0, $mass);
    }

    /**
     * Вычисляет текущее количество особей в сессии с учетом отборов и пересадок
     * 
     * @param int $sessionId ID сессии
     * @return int Текущее количество особей
     */
    private function calculateCurrentFishCount(int $sessionId): int
    {
        $session = $this->sessions->find($sessionId);
        if (!$session) {
            return 0;
        }

        // Начальное количество
        $count = (int)$session->start_fish_count;

        // Вычитаем отборы для этой сессии
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT COALESCE(SUM(fish_count), 0) AS total_harvests
            FROM harvests
            WHERE session_id = ?
        SQL);
        $stmt->execute([$sessionId]);
        $harvests = $stmt->fetch(PDO::FETCH_ASSOC);
        $count -= (int)($harvests['total_harvests'] ?? 0);

        // Вычитаем пересадки из (где эта сессия - источник)
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT COALESCE(SUM(fish_count), 0) AS total_out
            FROM partial_transplants
            WHERE source_session_id = ? AND is_reverted = 0
        SQL);
        $stmt->execute([$sessionId]);
        $transplantsOut = $stmt->fetch(PDO::FETCH_ASSOC);
        $count -= (int)($transplantsOut['total_out'] ?? 0);

        // Добавляем пересадки в (где эта сессия - реципиент)
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT COALESCE(SUM(fish_count), 0) AS total_in
            FROM partial_transplants
            WHERE recipient_session_id = ? AND is_reverted = 0
        SQL);
        $stmt->execute([$sessionId]);
        $transplantsIn = $stmt->fetch(PDO::FETCH_ASSOC);
        $count += (int)($transplantsIn['total_in'] ?? 0);

        return max(0, $count);
    }

    /**
     * Вычитает биомассу из сессии-отбора (уменьшает start_mass и start_fish_count)
     * 
     * @param int $sessionId ID сессии
     * @param float $weight Вес для вычитания (кг)
     * @param int $fishCount Количество особей для вычитания
     * @return void
     */
    private function subtractFromSourceSession(int $sessionId, float $weight, int $fishCount): void
    {
        $stmt = $this->pdo->prepare(<<<SQL
            UPDATE sessions
            SET start_mass = GREATEST(0, start_mass - ?),
                start_fish_count = GREATEST(0, start_fish_count - ?)
            WHERE id = ?
        SQL);
        $stmt->execute([$weight, $fishCount, $sessionId]);
    }

    /**
     * Добавляет биомассу в сессию-реципиент (увеличивает start_mass и start_fish_count)
     * 
     * @param int $sessionId ID сессии
     * @param float $weight Вес для добавления (кг)
     * @param int $fishCount Количество особей для добавления
     * @return void
     */
    private function addToRecipientSession(int $sessionId, float $weight, int $fishCount): void
    {
        $stmt = $this->pdo->prepare(<<<SQL
            UPDATE sessions
            SET start_mass = start_mass + ?,
                start_fish_count = start_fish_count + ?
            WHERE id = ?
        SQL);
        $stmt->execute([$weight, $fishCount, $sessionId]);
    }

    /**
     * Получает информацию о пересадке для предпросмотра (для показа пользователю перед подтверждением)
     * 
     * @param array $payload Данные пересадки
     * @return array Информация о пересадке с описанием действий
     */
    public function getTransplantPreview(array $payload): array
    {
        $sourceSessionId = (int)$payload['source_session_id'];
        $recipientSessionId = (int)$payload['recipient_session_id'];
        $weight = (float)$payload['weight'];
        $fishCount = (int)$payload['fish_count'];

        $sourceSession = $this->sessions->find($sourceSessionId);
        $recipientSession = $this->sessions->find($recipientSessionId);

        if (!$sourceSession || !$recipientSession) {
            throw new DomainException('Одна из сессий не найдена');
        }

        $sourcePlanting = $this->getSessionPlanting($sourceSession);
        $recipientPlanting = $this->getSessionPlanting($recipientSession);
        
        // Для расчета процентов используем start_mass сессии реципиента, 
        // так как это базовая биомасса, которая будет в бассейне после пересадки
        // (calculateCurrentMass может вернуть 0, если биомасса была отобрана)
        $recipientBaseMass = (float)$recipientSession->start_mass;

        $preview = [
            'source_session' => [
                'id' => $sourceSession->id,
                'name' => $sourceSession->name,
                'planting' => $sourcePlanting,
            ],
            'recipient_session' => [
                'id' => $recipientSession->id,
                'name' => $recipientSession->name,
                'planting' => $recipientPlanting,
            ],
            'weight' => $weight,
            'fish_count' => $fishCount,
            'will_create_mixed_planting' => false,
            'mixed_planting_name' => null,
            'mixed_planting_components' => [],
        ];

        // Проверяем, нужно ли создавать микстовую посадку
        if ($this->needMixedPlanting($sourceSession, $recipientSession)) {
            $preview['will_create_mixed_planting'] = true;
            
            // Вычисляем процентное соотношение на основе нового веса биомассы после пересадки
            // Используем start_mass сессии реципиента как базовую биомассу, которая будет в бассейне
            // (это учитывает уже существующие пересадки, которые увеличили start_mass)
            $recipientBaseMass = (float)$recipientSession->start_mass;
            
            // Новый общий вес биомассы в бассейне реципиенте ПОСЛЕ пересадки
            $totalMassAfter = $recipientBaseMass + $weight;
            
            // Проверяем, что оба значения положительные
            if ($totalMassAfter <= 0) {
                throw new DomainException('Невозможно рассчитать проценты: общий вес биомассы после пересадки равен нулю');
            }
            
            if ($weight <= 0) {
                throw new DomainException('Вес пересадки должен быть больше нуля');
            }
            
            // Считаем проценты от общего веса биомассы после пересадки
            // Процент реципиента = (базовая биомасса реципиента / общий вес после) * 100
            // Процент источника = (вес пересадки / общий вес после) * 100
            $recipientPercentage = round(($recipientBaseMass / $totalMassAfter) * 100, 2);
            $sourcePercentage = round(($weight / $totalMassAfter) * 100, 2);
            
            // Убеждаемся, что сумма равна 100% (может быть небольшая погрешность из-за округления)
            $total = $recipientPercentage + $sourcePercentage;
            if (abs($total - 100.0) > 0.01 && $total > 0) {
                // Нормализуем, чтобы сумма была ровно 100%
                $recipientPercentage = round(($recipientPercentage / $total) * 100, 2);
                $sourcePercentage = round(($sourcePercentage / $total) * 100, 2);
            }
            
            // Если получилось 0% / 100%, это означает, что реципиент был пустой
            // В этом случае проценты правильные, но нужно убедиться, что они не отрицательные
            $recipientPercentage = max(0.0, $recipientPercentage);
            $sourcePercentage = max(0.0, $sourcePercentage);

            $preview['mixed_planting_name'] = sprintf(
                '%s / %s (%s%% / %s%%)',
                $recipientPlanting['name'],
                $sourcePlanting['name'],
                number_format($recipientPercentage, 2),
                number_format($sourcePercentage, 2)
            );

            // Формируем список компонентов для предпросмотра
            $previewComponents = [];
            
            // Компоненты от реципиента
            if ($recipientPlanting['type'] === 'mixed') {
                $existingMixed = $this->mixedPlantings->findWithComponents($recipientSession->mixed_planting_id);
                if ($existingMixed && !empty($existingMixed['components'])) {
                    foreach ($existingMixed['components'] as $comp) {
                        $componentPercentage = ($comp['percentage'] / 100) * $recipientPercentage;
                        $previewComponents[] = [
                            'planting_id' => (int)$comp['planting_id'],
                            'planting_name' => $comp['planting_name'] ?? 'Посадка #' . $comp['planting_id'],
                            'percentage' => round($componentPercentage, 2),
                        ];
                    }
                }
            } else {
                $previewComponents[] = [
                    'planting_id' => $recipientPlanting['id'],
                    'planting_name' => $recipientPlanting['name'],
                    'percentage' => $recipientPercentage,
                ];
            }
            
            // Компоненты от источника
            if ($sourcePlanting['type'] === 'mixed') {
                $existingSourceMixed = $this->mixedPlantings->findWithComponents($sourceSession->mixed_planting_id);
                if ($existingSourceMixed && !empty($existingSourceMixed['components'])) {
                    foreach ($existingSourceMixed['components'] as $comp) {
                        $componentPercentage = ($comp['percentage'] / 100) * $sourcePercentage;
                        $previewComponents[] = [
                            'planting_id' => (int)$comp['planting_id'],
                            'planting_name' => $comp['planting_name'] ?? 'Посадка #' . $comp['planting_id'],
                            'percentage' => round($componentPercentage, 2),
                        ];
                    }
                }
            } else {
                $previewComponents[] = [
                    'planting_id' => $sourcePlanting['id'],
                    'planting_name' => $sourcePlanting['name'],
                    'percentage' => $sourcePercentage,
                ];
            }
            
            // Нормализуем проценты
            $totalPercentage = array_sum(array_column($previewComponents, 'percentage'));
            if ($totalPercentage > 0) {
                foreach ($previewComponents as &$comp) {
                    $comp['percentage'] = round(($comp['percentage'] / $totalPercentage) * 100, 2);
                }
            }
            
            $preview['mixed_planting_components'] = $previewComponents;
        }

        return $preview;
    }

    /**
     * Обрабатывает создание микстовой посадки при необходимости
     * 
     * @param object $sourceSession Сессия отбора
     * @param object $recipientSession Сессия реципиент
     * @param float $weight Вес пересаживаемой биомассы
     * @param int $fishCount Количество пересаживаемых особей
     * @param int $userId ID пользователя
     * @return int|null ID созданной микстовой посадки или null
     */
    private function handleMixedPlanting($sourceSession, $recipientSession, float $weight, int $fishCount, int $userId): ?int
    {
        if (!$this->needMixedPlanting($sourceSession, $recipientSession)) {
            return null;
        }

        // Получаем посадки
        $sourcePlanting = $this->getSessionPlanting($sourceSession);
        $recipientPlanting = $this->getSessionPlanting($recipientSession);

        // Вычисляем процентное соотношение на основе нового веса биомассы после пересадки
        // Используем start_mass сессии реципиента как базовую биомассу, которая будет в бассейне
        // (это учитывает уже существующие пересадки, которые увеличили start_mass)
        $recipientBaseMass = (float)$recipientSession->start_mass;
        
        // Новый общий вес биомассы в бассейне реципиенте ПОСЛЕ пересадки
        $totalMassAfter = $recipientBaseMass + $weight;
        
        // Проверяем, что оба значения положительные
        if ($totalMassAfter <= 0) {
            throw new DomainException('Невозможно рассчитать проценты: общий вес биомассы после пересадки равен нулю');
        }
        
        if ($weight <= 0) {
            throw new DomainException('Вес пересадки должен быть больше нуля');
        }
        
        // Считаем проценты от общего веса биомассы после пересадки
        // Процент реципиента = (базовая биомасса реципиента / общий вес после) * 100
        // Процент источника = (вес пересадки / общий вес после) * 100
        $recipientPercentage = round(($recipientBaseMass / $totalMassAfter) * 100, 2);
        $sourcePercentage = round(($weight / $totalMassAfter) * 100, 2);
        
        // Убеждаемся, что сумма равна 100% (может быть небольшая погрешность из-за округления)
        $total = $recipientPercentage + $sourcePercentage;
        if (abs($total - 100.0) > 0.01 && $total > 0) {
            // Нормализуем, чтобы сумма была ровно 100%
            $recipientPercentage = round(($recipientPercentage / $total) * 100, 2);
            $sourcePercentage = round(($sourcePercentage / $total) * 100, 2);
        }
        
        // Если получилось 0% / 100%, это означает, что реципиент был пустой
        // В этом случае проценты правильные, но нужно убедиться, что они не отрицательные
        $recipientPercentage = max(0.0, $recipientPercentage);
        $sourcePercentage = max(0.0, $sourcePercentage);

        // Собираем все ID компонентов для проверки существующей микстовой посадки
        $componentIds = [];
        
        if ($recipientPlanting['type'] === 'mixed') {
            // Если у реципиента микстовая посадка, берем все её компоненты
            $componentIds = array_merge($componentIds, $recipientPlanting['component_ids'] ?? []);
        } else {
            // Обычная посадка
            $componentIds[] = $recipientPlanting['id'];
        }
        
        if ($sourcePlanting['type'] === 'mixed') {
            // Если у источника микстовая посадка, добавляем все её компоненты
            $componentIds = array_merge($componentIds, $sourcePlanting['component_ids'] ?? []);
        } else {
            // Обычная посадка
            $componentIds[] = $sourcePlanting['id'];
        }
        
        // Убираем дубликаты и сортируем
        $componentIds = array_unique($componentIds);
        sort($componentIds);
        
        // Проверяем, существует ли уже микстовая посадка с такими компонентами
        $existingMixedPlantingId = $this->mixedPlantings->findByComponents($componentIds);

        if ($existingMixedPlantingId !== null) {
            return $existingMixedPlantingId;
        }

        // Создаем новую микстовую посадку
        $mixedPlantingName = sprintf(
            '%s / %s (%s%% / %s%%)',
            $recipientPlanting['name'],
            $sourcePlanting['name'],
            number_format($recipientPercentage, 2),
            number_format($sourcePercentage, 2)
        );

        // Формируем компоненты для новой микстовой посадки
        // Если у реципиента микстовая посадка, нужно разбить её процент на компоненты
        $components = [];
        
        if ($recipientPlanting['type'] === 'mixed') {
            // Получаем существующую микстовую посадку для получения процентов компонентов
            $existingMixed = $this->mixedPlantings->findWithComponents($recipientSession->mixed_planting_id);
            if ($existingMixed && !empty($existingMixed['components'])) {
                // Распределяем процент реципиента пропорционально компонентам
                foreach ($existingMixed['components'] as $comp) {
                    $componentPercentage = ($comp['percentage'] / 100) * $recipientPercentage;
                    $components[] = [
                        'planting_id' => (int)$comp['planting_id'],
                        'percentage' => round($componentPercentage, 2),
                    ];
                }
            }
        } else {
            // Обычная посадка реципиента
            $components[] = [
                'planting_id' => $recipientPlanting['id'],
                'percentage' => $recipientPercentage,
            ];
        }
        
        // Добавляем компонент от источника
        if ($sourcePlanting['type'] === 'mixed') {
            // Если у источника микстовая посадка, распределяем процент
            $existingSourceMixed = $this->mixedPlantings->findWithComponents($sourceSession->mixed_planting_id);
            if ($existingSourceMixed && !empty($existingSourceMixed['components'])) {
                foreach ($existingSourceMixed['components'] as $comp) {
                    $componentPercentage = ($comp['percentage'] / 100) * $sourcePercentage;
                    $components[] = [
                        'planting_id' => (int)$comp['planting_id'],
                        'percentage' => round($componentPercentage, 2),
                    ];
                }
            }
        } else {
            // Обычная посадка источника
            $components[] = [
                'planting_id' => $sourcePlanting['id'],
                'percentage' => $sourcePercentage,
            ];
        }
        
        // Нормализуем проценты (сумма должна быть 100%)
        $totalPercentage = array_sum(array_column($components, 'percentage'));
        if ($totalPercentage > 0) {
            foreach ($components as &$comp) {
                $comp['percentage'] = round(($comp['percentage'] / $totalPercentage) * 100, 2);
            }
        }

        $mixedPlantingId = $this->mixedPlantings->insert([
            'name' => $mixedPlantingName,
            'fish_breed' => $recipientPlanting['fish_breed'] ?? null,
            'created_by' => $userId,
            'components' => $components,
        ]);

        return $mixedPlantingId;
    }

    /**
     * Проверяет, нужно ли создавать микстовую посадку
     * 
     * @param object $sourceSession Сессия отбора
     * @param object $recipientSession Сессия реципиент
     * @return bool true, если нужно создать микстовую посадку
     */
    private function needMixedPlanting($sourceSession, $recipientSession): bool
    {
        $sourcePlanting = $this->getSessionPlanting($sourceSession);
        $recipientPlanting = $this->getSessionPlanting($recipientSession);

        // Если у сессий разные посадки (по ID), нужно создать микстовую посадку
        return $sourcePlanting['id'] !== $recipientPlanting['id'];
    }

    /**
     * Получает информацию о посадке сессии (чистая или микстовая)
     * 
     * @param object $session Сессия
     * @return array Информация о посадке (id, name, fish_breed, type, component_ids для микстовых)
     */
    private function getSessionPlanting($session): array
    {
        // Если у сессии есть микстовая посадка
        if (!empty($session->mixed_planting_id)) {
            $mixedPlanting = $this->mixedPlantings->findWithComponents($session->mixed_planting_id);
            if ($mixedPlanting) {
                // Для микстовой посадки используем ID компонентов для сравнения
                $componentIds = array_map(function($comp) {
                    return (int)$comp['planting_id'];
                }, $mixedPlanting['components'] ?? []);
                sort($componentIds);
                
                return [
                    'id' => 'mixed_' . implode('_', $componentIds),
                    'mixed_planting_id' => (int)$mixedPlanting['id'],
                    'name' => $mixedPlanting['name'],
                    'fish_breed' => $mixedPlanting['fish_breed'],
                    'type' => 'mixed',
                    'component_ids' => $componentIds,
                ];
            }
        }

        // Обычная посадка
        $planting = $this->plantings->find($session->planting_id);
        if ($planting) {
            return [
                'id' => $planting->id,
                'name' => $planting->name,
                'fish_breed' => $planting->fish_breed,
                'type' => 'regular',
            ];
        }

        throw new DomainException('Посадка сессии не найдена');
    }

    /**
     * Обновляет сессию для использования микстовой посадки
     * 
     * @param int $sessionId ID сессии
     * @param int $mixedPlantingId ID микстовой посадки
     * @return void
     */
    private function updateSessionMixedPlanting(int $sessionId, int $mixedPlantingId): void
    {
        $stmt = $this->pdo->prepare(<<<SQL
            UPDATE sessions
            SET mixed_planting_id = ?
            WHERE id = ?
        SQL);
        $stmt->execute([$mixedPlantingId, $sessionId]);
    }
}

