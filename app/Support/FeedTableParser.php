<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Простейший парсер таблиц кормления в формате YAML.
 *
 * Поддерживает специализированный подмножество YAML, используемое для описания:
 *  - строки unit;
 *  - список диапазонов масс с ключами label/min/max (в граммах);
 *  - список контрольных температур;
 *  - матрицу значений, разбитую по температуре и диапазону массы.
 */
class FeedTableParser
{
    /**
     * Возвращает пример шаблона, который можно вставить в форму.
     */
    public static function getTemplate(): string
    {
        return <<<YAML
unit: "kg feed per day per 100 kg fish"

weight_ranges_g:
  - { label: "8-40", min: 8, max: 40 }
  - { label: "40-100", min: 40, max: 100 }
  - { label: "100-400", min: 100, max: 400 }

temperatures_c:
  - 6
  - 8
  - 10

values:
  6:
    "8-40": 1.2
    "40-100": 1.0
    "100-400": 0.8
YAML;
    }

    /**
     * Разбирает YAML в структуру данных.
     *
     * @return array{
     *   unit: string,
     *   weight_ranges: array<int,array{label:string,min:float|null,max:float|null}>,
     *   temperatures: array<int,float>,
     *   values: array<string,array<string,float>>
     * }
     */
    public static function parse(string $yaml): array
    {
        $lines = preg_split('/\R/', (string) $yaml);
        $unit = null;
        $weightRanges = [];
        $temperatures = [];
        $values = [];

        $section = null;
        $currentTemp = null;

        foreach ($lines as $rawLine) {
            if ($rawLine === null) {
                continue;
            }
            $line = rtrim($rawLine);
            if ($line === '') {
                continue;
            }
            $trimmed = ltrim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }
            $indent = strlen($line) - strlen($trimmed);

            if ($indent === 0) {
                if (!preg_match('/^([a-zA-Z0-9_]+):\s*(.*)$/', $trimmed, $m)) {
                    throw new InvalidArgumentException("Не удалось разобрать строку: {$trimmed}");
                }
                $key = $m[1];
                $rest = $m[2];
                $section = $key;
                switch ($key) {
                    case 'unit':
                        $unit = self::stripQuotes($rest);
                        $section = null;
                        break;
                    case 'weight_ranges_g':
                    case 'temperatures_c':
                    case 'values':
                        $currentTemp = null;
                        break;
                    default:
                        throw new InvalidArgumentException("Неизвестный ключ верхнего уровня: {$key}");
                }
                continue;
            }

            if ($section === 'weight_ranges_g') {
                $weightRanges[] = self::parseWeightRange($trimmed);
                continue;
            }

            if ($section === 'temperatures_c') {
                if (!preg_match('/^-\s*([0-9\.\-]+)/', $trimmed, $m)) {
                    throw new InvalidArgumentException("Некорректная запись температуры: {$trimmed}");
                }
                $temperatures[] = (float) $m[1];
                continue;
            }

            if ($section === 'values') {
                if ($indent === 2 && preg_match('/^([0-9\.\-]+):\s*$/', $trimmed, $m)) {
                    $currentTemp = (string) (float) $m[1];
                    if (!isset($values[$currentTemp])) {
                        $values[$currentTemp] = [];
                    }
                    continue;
                }

                if ($indent >= 4) {
                    if ($currentTemp === null) {
                        throw new InvalidArgumentException('Найдены значения без указания температуры.');
                    }
                    if (!preg_match('/^"?(.*?)"?\s*:\s*([0-9\.\-]+)/', $trimmed, $m)) {
                        throw new InvalidArgumentException("Некорректная строка значения: {$trimmed}");
                    }
                    $label = trim($m[1], "\"' ");
                    $values[$currentTemp][$label] = (float) $m[2];
                    continue;
                }
            }
        }

        if ($unit === null) {
            throw new InvalidArgumentException('Поле unit обязательно.');
        }
        if (empty($weightRanges)) {
            throw new InvalidArgumentException('Секция weight_ranges_g не заполнена.');
        }
        if (empty($temperatures)) {
            throw new InvalidArgumentException('Секция temperatures_c не заполнена.');
        }
        if (empty($values)) {
            throw new InvalidArgumentException('Секция values не заполнена.');
        }

        foreach ($weightRanges as $range) {
            if (!array_key_exists('label', $range) || $range['label'] === '') {
                throw new InvalidArgumentException('Каждый диапазон веса должен содержать label.');
            }
        }

        $temperatures = array_values(array_unique(array_map('floatval', $temperatures)));
        sort($temperatures, SORT_NUMERIC);

        foreach ($temperatures as $temp) {
            $key = (string) $temp;
            if (!array_key_exists($key, $values)) {
                throw new InvalidArgumentException("Для температуры {$temp} не заданы значения.");
            }
        }

        return [
            'unit' => $unit,
            'weight_ranges' => $weightRanges,
            'temperatures' => array_values(array_map('floatval', $temperatures)),
            'values' => $values,
        ];
    }

    /**
     * Возвращает коэффициент для заданных параметров (старый метод, для обратной совместимости).
     *
     * @return array{value:float, temperature:float, weight_label:string}|null
     */
    public static function resolveRate(array $table, float $temperature, float $weightGrams): ?array
    {
        return self::resolveRateWithStrategy($table, $temperature, $weightGrams, 'normal');
    }

    /**
     * Возвращает коэффициент для заданных параметров с учетом стратегии кормления.
     *
     * Логика расчета:
     * - Эконом: выбирается МЕНЬШИЙ коэффициент из двух соседних температурных значений
     * - Норма: линейная интерполяция между двумя соседними значениями пропорционально температуре
     * - Рост: выбирается БОЛЬШИЙ коэффициент из двух соседних температурных значений
     *
     * @param array $table Распарсенная таблица кормления
     * @param float $temperature Текущая температура воды (°C)
     * @param float $weightGrams Средний вес рыбы (граммы)
     * @param string $strategy Стратегия кормления: 'econom', 'normal', 'growth'
     * @return array{value:float, temperature:float, weight_label:string, temp_lower:float|null, temp_upper:float|null, coeff_lower:float|null, coeff_upper:float|null}|null
     */
    public static function resolveRateWithStrategy(array $table, float $temperature, float $weightGrams, string $strategy): ?array
    {
        $range = self::matchWeightRange($weightGrams, $table['weight_ranges'] ?? []);
        if ($range === null) {
            return null;
        }

        $label = $range['label'];
        $temperatures = $table['temperatures'] ?? [];
        if (empty($temperatures)) {
            return null;
        }

        $sortedTemps = array_values($temperatures);
        sort($sortedTemps, SORT_NUMERIC);

        // Находим два соседних значения температуры
        $tempLower = null;
        $tempUpper = null;
        $coeffLower = null;
        $coeffUpper = null;

        // Если температура меньше минимальной - используем минимальную
        if ($temperature <= $sortedTemps[0]) {
            $tempLower = $sortedTemps[0];
            $tempKey = (string) $tempLower;
            if (isset($table['values'][$tempKey][$label])) {
                $coeffLower = (float)$table['values'][$tempKey][$label];
                $coeffUpper = $coeffLower;
                $tempUpper = $tempLower;
            } else {
                return null;
            }
        }
        // Если температура больше максимальной - используем максимальную
        elseif ($temperature >= $sortedTemps[count($sortedTemps) - 1]) {
            $tempUpper = $sortedTemps[count($sortedTemps) - 1];
            $tempKey = (string) $tempUpper;
            if (isset($table['values'][$tempKey][$label])) {
                $coeffUpper = (float)$table['values'][$tempKey][$label];
                $coeffLower = $coeffUpper;
                $tempLower = $tempUpper;
            } else {
                return null;
            }
        }
        // Температура между двумя значениями - находим соседние
        else {
            for ($i = 0; $i < count($sortedTemps) - 1; $i++) {
                if ($temperature >= $sortedTemps[$i] && $temperature <= $sortedTemps[$i + 1]) {
                    $tempLower = $sortedTemps[$i];
                    $tempUpper = $sortedTemps[$i + 1];
                    break;
                }
            }

            if ($tempLower === null || $tempUpper === null) {
                return null;
            }

            $tempLowerKey = (string) $tempLower;
            $tempUpperKey = (string) $tempUpper;

            if (!isset($table['values'][$tempLowerKey][$label]) || !isset($table['values'][$tempUpperKey][$label])) {
                return null;
            }

            $coeffLower = (float)$table['values'][$tempLowerKey][$label];
            $coeffUpper = (float)$table['values'][$tempUpperKey][$label];
        }

        // Вычисляем итоговый коэффициент в зависимости от стратегии
        $finalCoeff = null;
        if ($strategy === 'econom') {
            // Эконом: выбираем МЕНЬШИЙ коэффициент
            $finalCoeff = min($coeffLower, $coeffUpper);
        } elseif ($strategy === 'growth') {
            // Рост: выбираем БОЛЬШИЙ коэффициент
            $finalCoeff = max($coeffLower, $coeffUpper);
        } else {
            // Норма: линейная интерполяция
            if ($tempLower === $tempUpper) {
                $finalCoeff = $coeffLower;
            } else {
                // Линейная интерполяция: coeff = coeffLower + (coeffUpper - coeffLower) * (temp - tempLower) / (tempUpper - tempLower)
                $ratio = ($temperature - $tempLower) / ($tempUpper - $tempLower);
                $finalCoeff = $coeffLower + ($coeffUpper - $coeffLower) * $ratio;
            }
        }

        return [
            'value' => $finalCoeff,
            'temperature' => $temperature,
            'weight_label' => $label,
            'temp_lower' => $tempLower,
            'temp_upper' => $tempUpper,
            'coeff_lower' => $coeffLower,
            'coeff_upper' => $coeffUpper,
        ];
    }

    private static function parseWeightRange(string $line): array
    {
        if (!preg_match('/^-\s*\{(.+)\}\s*$/', $line, $m)) {
            throw new InvalidArgumentException("Некорректная строка диапазона веса: {$line}");
        }

        $parts = preg_split('/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', $m[1]);
        $result = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (!preg_match('/^([a-zA-Z_]+)\s*:\s*(.+)$/', $part, $pm)) {
                throw new InvalidArgumentException("Не удалось разобрать часть диапазона: {$part}");
            }
            $key = $pm[1];
            $value = self::normalizeScalar(self::stripQuotes($pm[2]));
            $result[$key] = $value;
        }

        if (!isset($result['label'])) {
            throw new InvalidArgumentException('Диапазон веса должен содержать поле label.');
        }

        $minRaw = $result['min'] ?? null;
        $maxRaw = $result['max'] ?? null;

        $min = $minRaw === null || $minRaw === '' ? 0.0 : (float)$minRaw;
        $max = $maxRaw === null || $maxRaw === '' ? null : (float)$maxRaw;

        return [
            'label' => $result['label'],
            'min' => $min,
            'max' => $max,
        ];
    }

    private static function matchTemperature(float $temperature, array $temperatures): ?float
    {
        if (empty($temperatures)) {
            return null;
        }

        $sorted = array_values($temperatures);
        sort($sorted, SORT_NUMERIC);
        $selected = $sorted[0];

        foreach ($sorted as $value) {
            if ($temperature < $value) {
                break;
            }
            $selected = $value;
        }

        return (float)$selected;
    }

    private static function matchWeightRange(float $weightGrams, array $ranges): ?array
    {
        foreach ($ranges as $range) {
            $min = $range['min'] ?? 0.0;
            $max = $range['max'] ?? null;
            if ($weightGrams >= $min && ($max === null || $weightGrams < $max)) {
                return $range;
            }
        }

        return null;
    }

    private static function stripQuotes(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            return substr($value, 1, -1);
        }

        return $value;
    }

    private static function normalizeScalar(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $lower = strtolower($value);
        if ($lower === 'null') {
            return null;
        }

        return $value;
    }
}

