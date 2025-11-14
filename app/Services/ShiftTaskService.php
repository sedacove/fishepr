<?php

namespace App\Services;

use App\Repositories\ShiftTaskTemplateRepository;
use App\Repositories\ShiftTaskInstanceRepository;
use DomainException;
use PDO;
use RuntimeException;

/**
 * Сервис для управления заданиями смены
 */
class ShiftTaskService
{
    private ShiftTaskTemplateRepository $templates;
    private ShiftTaskInstanceRepository $instances;

    public function __construct(private readonly PDO $pdo)
    {
        $this->templates = new ShiftTaskTemplateRepository($pdo);
        $this->instances = new ShiftTaskInstanceRepository($pdo);
    }

    /**
     * Возвращает список заданий для указанной смены
     *
     * @param string $shiftDate Дата смены (Y-m-d)
     * @param bool $autoGenerate Генерировать ли новые задания при запросе
     * @return array
     */
    public function listForShift(string $shiftDate, bool $autoGenerate = true): array
    {
        if ($autoGenerate) {
            $this->syncForShift($shiftDate);
        }

        $tasks = $this->instances->listForShiftDate($shiftDate);
        $now = new \DateTimeImmutable();

        foreach ($tasks as &$task) {
            $dueAt = new \DateTimeImmutable($task['due_at']);
            $diff = $now->getTimestamp() - $dueAt->getTimestamp();
            $task['is_overdue'] = $diff > 0 && $task['status'] === 'pending';
            $task['time_diff_label'] = $this->formatDiffLabel($dueAt, $now);
        }

        return $tasks;
    }

    /**
     * Генерирует задания на смену на основе шаблонов
     */
    public function syncForShift(string $shiftDate): void
    {
        $this->instances->markMissedBefore($shiftDate);
        $templates = $this->templates->listActive();

        foreach ($templates as $template) {
            if (!$this->shouldGenerateForDate($template, $shiftDate)) {
                continue;
            }

            $dueAt = $this->buildDueAt($shiftDate, $template['due_time']);
            $existing = $this->instances->findByTemplateAndDate((int)$template['id'], $shiftDate);
            if ($existing) {
                $this->instances->touchDueDate((int)$existing['id'], $dueAt);
                continue;
            }
            $this->instances->create((int)$template['id'], $shiftDate, $dueAt);
        }
    }

    /**
     * Переключает статус задания
     */
    public function toggleCompletion(int $taskId, bool $completed, int $userId, bool $isAdmin, ?string $note = null): array
    {
        $task = $this->instances->find($taskId);
        if (!$task) {
            throw new RuntimeException('Задание не найдено');
        }

        $this->instances->setCompletion($taskId, $completed, $userId, $note);
        $updated = $this->instances->find($taskId);

        $now = new \DateTimeImmutable();
        $dueAt = new \DateTimeImmutable($updated['due_at']);
        $updated['is_overdue'] = $dueAt < $now && $updated['status'] === 'pending';
        $updated['time_diff_label'] = $this->formatDiffLabel($dueAt, $now);

        return $updated;
    }

    /**
     * Создает или обновляет шаблон
     */
    public function saveTemplate(?int $id, array $payload, int $userId): int
    {
        $data = $this->validateTemplatePayload($payload);
        if ($id === null) {
            $data['created_by'] = $userId;
            $data['updated_by'] = $userId;
            $data['sort_order'] = $this->templates->getNextSortOrder();
            return $this->templates->create($data);
        }

        $template = $this->templates->find($id);
        if (!$template) {
            throw new RuntimeException('Шаблон не найден');
        }

        $data['updated_by'] = $userId;
        $data['created_by'] = (int)$template['created_by'];
        $this->templates->update($id, $data);

        return $id;
    }

    public function deleteTemplate(int $id): void
    {
        $this->templates->delete($id);
    }

    public function listTemplates(): array
    {
        return $this->templates->listAll();
    }

    public function findTemplate(int $id): ?array
    {
        return $this->templates->find($id);
    }

    public function reorderTemplates(array $orderedIds): void
    {
        $orderedIds = array_values(array_filter(array_map('intval', $orderedIds), fn($id) => $id > 0));
        if (empty($orderedIds)) {
            throw new DomainException('Нет данных для сортировки');
        }
        $this->templates->updateSortOrder($orderedIds);
    }

    private function validateTemplatePayload(array $payload): array
    {
        $title = trim((string)($payload['title'] ?? ''));
        if ($title === '') {
            throw new DomainException('Название обязательно');
        }

        $frequency = $payload['frequency'] ?? 'daily';
        if (!in_array($frequency, ['daily', 'weekly', 'biweekly', 'monthly'], true)) {
            throw new DomainException('Некорректная частота');
        }

        $startDate = $payload['start_date'] ?? date('Y-m-d');
        $dueTime = $payload['due_time'] ?? '12:00';
        $weekDay = isset($payload['week_day']) ? (int)$payload['week_day'] : null;
        $dayOfMonth = isset($payload['day_of_month']) ? (int)$payload['day_of_month'] : null;

        if (in_array($frequency, ['weekly', 'biweekly'], true)) {
            if ($weekDay === null) {
                throw new DomainException('Укажите день недели');
            }
            if ($weekDay < 0 || $weekDay > 6) {
                throw new DomainException('Некорректный день недели');
            }
        }

        if ($frequency === 'monthly' && ($dayOfMonth === null || $dayOfMonth < 1 || $dayOfMonth > 31)) {
            throw new DomainException('Некорректный день месяца');
        }

        if (!in_array($frequency, ['weekly', 'biweekly'], true)) {
            $weekDay = null;
        }

        if ($frequency !== 'monthly') {
            $dayOfMonth = null;
        }

        return [
            'title' => $title,
            'description' => $payload['description'] ?? null,
            'frequency' => $frequency,
            'start_date' => $startDate,
            'week_day' => $weekDay,
            'day_of_month' => $dayOfMonth,
            'due_time' => $this->normalizeTime($dueTime),
            'is_active' => !empty($payload['is_active']),
        ];
    }

    private function normalizeTime(string $time): string
    {
        $time = trim($time);
        if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            $time .= ':00';
        }
        if (!preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $time)) {
            throw new DomainException('Некорректное время');
        }
        return $time;
    }

    private function shouldGenerateForDate(array $template, string $shiftDate): bool
    {
        $start = new \DateTimeImmutable($template['start_date']);
        $shift = new \DateTimeImmutable($shiftDate);

        if ($shift < $start) {
            return false;
        }

        switch ($template['frequency']) {
            case 'daily':
                return true;
            case 'weekly':
            case 'biweekly':
                $weekDay = $template['week_day'] ?? (int)$start->format('w');
                if ((int)$shift->format('w') !== (int)$weekDay) {
                    return false;
                }

                if ($template['frequency'] === 'biweekly') {
                    $diffWeeks = intdiv((int)$start->diff($shift)->days, 7);
                    return $diffWeeks % 2 === 0;
                }
                return true;
            case 'monthly':
                $day = $template['day_of_month'] ?? (int)$start->format('j');
                $shiftDay = (int)$shift->format('j');
                $lastDay = cal_days_in_month(CAL_GREGORIAN, (int)$shift->format('n'), (int)$shift->format('Y'));
                return $shiftDay === min($day, $lastDay);
            default:
                return false;
        }
    }

    private function buildDueAt(string $shiftDate, string $dueTime): string
    {
        return date('Y-m-d H:i:s', strtotime("{$shiftDate} {$dueTime}"));
    }

    private function formatDiffLabel(\DateTimeImmutable $dueAt, \DateTimeImmutable $now): string
    {
        $diff = $now->diff($dueAt);
        $hours = $diff->h + ($diff->days * 24);
        $minutes = $diff->i;

        $label = '';
        if ($hours > 0) {
            $label .= $hours . 'ч ';
        }
        $label .= $minutes . 'м';

        return $dueAt > $now ? 'Через ' . trim($label) : 'Просрочено на ' . trim($label);
    }
}


