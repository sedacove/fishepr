<?php
/**
 * Вспомогательные функции для работы с дежурствами
 * Логика: смена происходит в 8:00 утра
 * Дежурный в конкретную дату дежурит с 8:00 этой даты до 8:00 следующей даты
 */

/**
 * Получить дату для "сегодня" с учетом смены в 8:00
 * Если текущее время < 8:00, то "сегодня" = вчерашняя дата
 * Если текущее время >= 8:00, то "сегодня" = сегодняшняя дата
 * 
 * @return string Дата в формате Y-m-d
 */
function getTodayDutyDate() {
    $now = new DateTime();
    $hour = (int)$now->format('H');
    
    // Если время меньше 8:00, то "сегодня" - это вчерашняя дата
    if ($hour < 8) {
        $today = clone $now;
        $today->modify('-1 day');
        return $today->format('Y-m-d');
    }
    
    // Иначе "сегодня" - это сегодняшняя дата
    return $now->format('Y-m-d');
}

/**
 * Получить дату для "завтра" с учетом смены в 8:00
 * Если текущее время < 8:00, то "завтра" = сегодняшняя дата
 * Если текущее время >= 8:00, то "завтра" = завтрашняя дата
 * 
 * @return string Дата в формате Y-m-d
 */
function getTomorrowDutyDate() {
    $now = new DateTime();
    $hour = (int)$now->format('H');
    
    // Если время меньше 8:00, то "завтра" - это сегодняшняя дата
    if ($hour < 8) {
        return $now->format('Y-m-d');
    }
    
    // Иначе "завтра" - это завтрашняя дата
    $tomorrow = clone $now;
    $tomorrow->modify('+1 day');
    return $tomorrow->format('Y-m-d');
}

/**
 * Получить дату дежурства для конкретной даты с учетом смены в 8:00
 * Если время события < 8:00, то дежурный берется из предыдущей даты
 * Если время события >= 8:00, то дежурный берется из текущей даты
 * 
 * @param DateTime|string $dateTime Дата и время события
 * @return string Дата в формате Y-m-d
 */
function getDutyDateForDateTime($dateTime) {
    if (is_string($dateTime)) {
        $dateTime = new DateTime($dateTime);
    }
    
    $hour = (int)$dateTime->format('H');
    
    // Если время меньше 8:00, то дежурный из предыдущей даты
    if ($hour < 8) {
        $dutyDate = clone $dateTime;
        $dutyDate->modify('-1 day');
        return $dutyDate->format('Y-m-d');
    }
    
    // Иначе дежурный из текущей даты
    return $dateTime->format('Y-m-d');
}

