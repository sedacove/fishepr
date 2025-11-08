<?php
/**
 * Шаблон блока бассейна для рабочей страницы
 * 
 * @param array $pool Данные бассейна
 * @param array|null $session Данные активной сессии (если есть)
 */
?>
<div class="pool-block" data-pool-id="<?php echo htmlspecialchars($pool['id']); ?>">
    <div class="pool-block-header">
        <div class="pool-block-title">
            <?php 
            // Проверяем, нужно ли показывать предупреждение о просроченном замере
            $showMeasurementWarning = false;
            $measurementWarningTooltip = null;
            $currentLoadWeight = $session['current_load']['weight'] ?? null;
            $currentLoadFishCount = $session['current_load']['fish_count'] ?? null;
            $weightIsApproximate = $session['current_load']['weight_is_approximate'] ?? false;
            if ($session) {
                require_once __DIR__ . '/../includes/settings.php';
                $warningTimeout = getSettingInt('measurement_warning_timeout_minutes', 60);
                
                if (array_key_exists('last_measurement_diff_minutes', $session)) {
                    $diffMinutes = $session['last_measurement_diff_minutes'];
                    $diffLabel = $session['last_measurement_diff_label'] ?? '';
                    
                    if ($diffMinutes === null) {
                        // Замеры ещё не проводились
                        $showMeasurementWarning = true;
                        $measurementWarningTooltip = 'Замер ещё не проводился';
                    } elseif ($diffMinutes > $warningTimeout) {
                        $showMeasurementWarning = true;
                        $timeLabel = $diffLabel ?: 'достаточно давно';
                        $measurementWarningTooltip = 'Замер не проводился ' . $timeLabel;
                        if ($diffLabel) {
                            $measurementWarningTooltip .= ' назад';
                        }
                    }
                } elseif (isset($session['last_measurement_at']) && $session['last_measurement_at']) {
                    // Fallback на случай отсутствия новых полей
                    $lastMeasurement = new DateTime($session['last_measurement_at']);
                    $now = new DateTime();
                    $minutesSinceLastMeasurement = ($now->getTimestamp() - $lastMeasurement->getTimestamp()) / 60;
                    
                    if ($minutesSinceLastMeasurement > $warningTimeout) {
                        $showMeasurementWarning = true;
                        $measurementWarningTooltip = 'Замер не проводился с ' . $lastMeasurement->format('H:i');
                    }
                } else {
                    // Если замеров вообще не было, тоже показываем предупреждение
                    $showMeasurementWarning = true;
                    $measurementWarningTooltip = 'Замер ещё не проводился';
                }
            }
            
            if ($showMeasurementWarning):
                $tooltipText = $measurementWarningTooltip ?? 'Замер не проводился';
            ?>
                <i class="bi bi-exclamation-triangle-fill text-danger me-2" 
                   title="<?php echo htmlspecialchars($tooltipText); ?>"
                   style="font-size: 1.2rem;"></i>
            <?php endif; ?>
            <?php if ($session && isset($session['id'])): ?>
                <a href="<?php echo BASE_URL; ?>pages/session_details.php?id=<?php echo $session['id']; ?>" class="text-decoration-none">
                    <?php echo htmlspecialchars($session['name']); ?>
                </a>
            <?php else: ?>
                <?php echo htmlspecialchars('ПУСТО'); ?>
            <?php endif; ?>
            <?php if ($session && isset($session['avg_fish_weight'])): ?>
                <span class="badge bg-primary pool-block-avg-weight-badge">
                    <?php echo number_format($session['avg_fish_weight'], 3, '.', ' '); ?> кг
                </span>
            <?php endif; ?>
            <?php if ($session && isset($session['start_mass']) && isset($session['start_fish_count'])): ?>
                <div class="pool-block-session-info">
                    <div><?php echo number_format($session['start_mass'], 2, '.', ' '); ?> кг</div>
                    <div><?php echo number_format($session['start_fish_count'], 0, '.', ' '); ?> шт</div>
                    <?php if (isset($session['start_date'])): ?>
                        <?php
                        $startDate = new DateTime($session['start_date']);
                        $now = new DateTime();
                        $daysDiff = $startDate->diff($now)->days;
                        // Правильное склонение слова "день"
                        $daysText = 'дней';
                        $lastDigit = $daysDiff % 10;
                        $lastTwoDigits = $daysDiff % 100;
                        if ($lastTwoDigits >= 11 && $lastTwoDigits <= 14) {
                            $daysText = 'дней';
                        } elseif ($lastDigit == 1) {
                            $daysText = 'день';
                        } elseif ($lastDigit >= 2 && $lastDigit <= 4) {
                            $daysText = 'дня';
                        }
                        ?>
                        <div class="pool-block-session-duration">Сессия длится <?php echo $daysDiff; ?> <?php echo $daysText; ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="pool-block-name">
            <?php echo htmlspecialchars($pool['name']); ?>
            
        </div>
    </div>
    <div class="pool-block-content">
        <?php if ($session && isset($session['last_measurement']) && $session['last_measurement']): 
            $measurement = $session['last_measurement'];
            $temp = $measurement['temperature'] ?? null;
            $oxygen = $measurement['oxygen'] ?? null;
            $tempStratum = $measurement['temperature_stratum'] ?? 'bad';
            $oxygenStratum = $measurement['oxygen_stratum'] ?? 'bad';
            $tempTrend = $measurement['temperature_trend'] ?? null;
            $tempTrendDirection = $measurement['temperature_trend_direction'] ?? null;
            $oxygenTrend = $measurement['oxygen_trend'] ?? null;
            $oxygenTrendDirection = $measurement['oxygen_trend_direction'] ?? null;
            
            // Определяем цвета для страт
            $tempColorClass = $tempStratum === 'good' ? 'text-success' : ($tempStratum === 'acceptable' ? 'text-warning' : 'text-danger');
            $oxygenColorClass = $oxygenStratum === 'good' ? 'text-success' : ($oxygenStratum === 'acceptable' ? 'text-warning' : 'text-danger');
            
            // Определяем стрелочку и её цвет для температуры
            $tempArrowHtml = '';
            if ($tempTrend && $tempTrend !== 'same') {
                $arrowIcon = $tempTrend === 'up' ? 'bi-triangle-fill' : 'bi-triangle-fill';
                $arrowStyle = $tempTrend === 'up' ? '' : 'transform: rotate(180deg);';
                $arrowColorClass = $tempTrendDirection === 'improving' ? 'text-success' : ($tempTrendDirection === 'worsening' ? 'text-danger' : 'text-muted');
                $tempArrowHtml = '<i class="bi ' . $arrowIcon . ' ' . $arrowColorClass . ' ms-2" style="font-size: 1.2rem; ' . $arrowStyle . '"></i>';
            }
            
            // Определяем стрелочку и её цвет для кислорода
            $oxygenArrowHtml = '';
            if ($oxygenTrend && $oxygenTrend !== 'same') {
                $arrowIcon = $oxygenTrend === 'up' ? 'bi-triangle-fill' : 'bi-triangle-fill';
                $arrowStyle = $oxygenTrend === 'up' ? '' : 'transform: rotate(180deg);';
                $arrowColorClass = $oxygenTrendDirection === 'improving' ? 'text-success' : ($oxygenTrendDirection === 'worsening' ? 'text-danger' : 'text-muted');
                $oxygenArrowHtml = '<i class="bi ' . $arrowIcon . ' ' . $arrowColorClass . ' ms-2" style="font-size: 1.2rem; ' . $arrowStyle . '"></i>';
            }
        ?>
            <div class="pool-measurements">
                <?php if ($temp !== null): ?>
                    <div class="pool-measurement-item">
                        <div class="pool-measurement-value <?php echo $tempColorClass; ?>">
                            <?php echo number_format($temp, 1, '.', ' '); ?>°C
                            <?php echo $tempArrowHtml; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($oxygen !== null): ?>
                    <div class="pool-measurement-item">
                        <div class="pool-measurement-value <?php echo $oxygenColorClass; ?>">
                            O<sub>2</sub> <?php echo number_format($oxygen, 1, '.', ' '); ?>
                            <?php echo $oxygenArrowHtml; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="pool-stats-right">
                <?php if ($currentLoadWeight !== null || $currentLoadFishCount !== null): ?>
                    <div class="pool-current-load text-end fw-semibold text-white mb-2" style="font-size: 1.1rem;">
                        <?php if ($currentLoadWeight !== null): ?>
                            <div><?php echo $weightIsApproximate ? '&asymp; ' : ''; ?><?php echo number_format($currentLoadWeight, 2, '.', ' '); ?>&nbsp;кг</div>
                        <?php endif; ?>
                        <?php if ($currentLoadFishCount !== null): ?>
                            <div><?php echo number_format($currentLoadFishCount, 0, '.', ' '); ?>&nbsp;шт</div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($session['mortality_last_hours'])): 
                    $mortality = $session['mortality_last_hours'];
                    $mortalityCount = $mortality['total_count'] ?? 0;
                    $mortalityHours = $mortality['hours'] ?? 24;
                    $mortalityColorClass = $mortality['color_class'] ?? 'text-danger';
                    $mortalityBadgeClass = str_replace('text-', 'bg-', $mortalityColorClass);
                    if ($mortalityBadgeClass === $mortalityColorClass) {
                        $mortalityBadgeClass = $mortalityColorClass;
                    }
                    $mortalityBadgeTextClass = in_array($mortalityBadgeClass, ['bg-warning', 'bg-light', 'bg-info']) ? 'text-dark' : 'text-white';
                ?>
                    <div class="pool-stat-item">
                        <div class="pool-stat-value">
                            <span class="badge <?php echo htmlspecialchars($mortalityBadgeClass); ?> <?php echo $mortalityBadgeTextClass; ?>">
                                <?php echo number_format($mortalityCount, 0, '.', ' '); ?> шт
                            </span>
                        </div>
                        <div class="pool-stat-label">Падеж за <?php echo $mortalityHours; ?> ч</div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($session): ?>
    <div class="pool-block-divider"></div>
    <div class="pool-block-actions">
        <button type="button" class="pool-action-btn" onclick="openMeasurementModal(<?php echo $pool['id']; ?>)" title="Выполнить замер">
            <i class="bi bi-thermometer-half"></i>
        </button>
        <button type="button" class="pool-action-btn" onclick="openMortalityModal(<?php echo $pool['id']; ?>)" title="Зарегистрировать падеж">
            <i class="bi bi-exclamation-triangle"></i>
        </button>
        <button type="button" class="pool-action-btn" onclick="openHarvestModal(<?php echo $pool['id']; ?>)" title="Добавить отбор">
            <i class="bi bi-box-arrow-up"></i>
        </button>
    </div>
    <?php endif; ?>
</div>
