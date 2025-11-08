<?php
/**
 * Утилиты для отправки уведомлений в Telegram
 */

require_once __DIR__ . '/settings.php';

/**
 * Отправка текстового сообщения в Telegram.
 *
 * @param string $message
 * @return bool true, если хотя бы одно сообщение отправлено
 */
function sendTelegramMessage(string $message): bool
{
    $token = trim((string)getSetting('telegram_bot_token', ''));
    if ($token === '') {
        return false;
    }

    $chatIdsRaw = trim((string)getSetting('telegram_chat_ids', ''));
    if ($chatIdsRaw === '') {
        return false;
    }

    $chatIds = array_filter(array_map(
        static fn($item) => trim($item),
        preg_split('/[\s,]+/', $chatIdsRaw)
    ));

    if (empty($chatIds)) {
        return false;
    }

    $apiUrl = "https://api.telegram.org/bot{$token}/sendMessage";
    $sent = false;

    foreach ($chatIds as $chatId) {
        $payload = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        if (function_exists('curl_init')) {
            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_TIMEOUT => 10,
            ]);
            $response = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (curl_errno($ch)) {
                error_log('Telegram cURL error: ' . curl_error($ch));
            } elseif ($httpCode >= 200 && $httpCode < 300) {
                $sent = true;
            } else {
                error_log('Telegram API HTTP ' . $httpCode . ' response: ' . $response);
            }
            curl_close($ch);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                    'content' => http_build_query($payload),
                    'timeout' => 10,
                ],
            ]);
            $result = @file_get_contents($apiUrl, false, $context);
            if ($result !== false) {
                $sent = true;
            } else {
                error_log('Telegram file_get_contents error while sending message.');
            }
        }
    }

    return $sent;
}

/**
 * Проверяет необходимость и отправляет уведомление о падеже.
 *
 * @param array $payload [
 *     'pool_name' => string,
 *     'session_name' => string|null,
 *     'fish_count' => int,
 *     'weight' => float|null,
 *     'recorded_at' => string, // Y-m-d H:i:s
 *     'created_by' => string|null, // ФИО или логин
 * ]
 * @return bool
 */
function maybeSendMortalityAlert(array $payload): bool
{
    $threshold = getSettingInt('mortality_alert_threshold', 0);
    $fishCount = isset($payload['fish_count']) ? (int)$payload['fish_count'] : 0;

    if ($threshold <= 0 || $fishCount <= $threshold) {
        return false;
    }

    $poolName = $payload['pool_name'] ?? 'Неизвестный бассейн';
    $sessionName = $payload['session_name'] ?? null;
    $weight = isset($payload['weight']) ? (float)$payload['weight'] : null;
    $recordedAt = isset($payload['recorded_at']) ? $payload['recorded_at'] : date('Y-m-d H:i:s');
    $createdBy = $payload['created_by'] ?? null;

    $formattedDate = date('d.m.Y H:i', strtotime($recordedAt));

    $lines = [
        '⚠️ <b>Пороговый падеж</b>',
        'Бассейн: ' . htmlspecialchars($poolName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    ];

    if ($sessionName) {
        $lines[] = 'Сессия: ' . htmlspecialchars($sessionName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    $lines[] = 'Количество: ' . number_format($fishCount, 0, '.', ' ') . ' шт (порог: ' . $threshold . ')';

    if ($weight !== null) {
        $lines[] = 'Вес: ' . number_format($weight, 2, '.', ' ') . ' кг';
    }

    $lines[] = 'Дата: ' . $formattedDate;

    if ($createdBy) {
        $lines[] = 'Добавил: ' . htmlspecialchars($createdBy, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    $message = implode("\n", $lines);

    return sendTelegramMessage($message);
}


