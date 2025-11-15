<?php

namespace App\Support;

use InvalidArgumentException;

class FeedingFormula
{
    private const VARIABLES = ['T', 'W'];
    private const OPERATORS = [
        '+' => ['precedence' => 1, 'assoc' => 'left'],
        '-' => ['precedence' => 1, 'assoc' => 'left'],
        '*' => ['precedence' => 2, 'assoc' => 'left'],
        '/' => ['precedence' => 2, 'assoc' => 'left'],
        '^' => ['precedence' => 3, 'assoc' => 'right'],
    ];

    private const SAMPLE_SETS = [
        ['T' => 6.0, 'W' => 50.0],
        ['T' => 12.0, 'W' => 150.0],
    ];

    public static function normalize(?string $formula): ?string
    {
        if ($formula === null) {
            return null;
        }

        $normalized = trim(str_replace(',', '.', $formula));
        return $normalized === '' ? null : $normalized;
    }

    public static function validate(string $formula): void
    {
        $rpn = self::buildRpn($formula);

        foreach (self::SAMPLE_SETS as $sample) {
            self::evaluateRpn($rpn, $sample);
        }
    }

    public static function evaluate(string $formula, float $temperature, float $weightGrams): float
    {
        $rpn = self::buildRpn($formula);

        return self::evaluateRpn($rpn, [
            'T' => $temperature,
            'W' => $weightGrams,
        ]);
    }

    private static function buildRpn(string $formula): array
    {
        $tokens = self::tokenize($formula);
        if (empty($tokens)) {
            throw new InvalidArgumentException('Формула не содержит выражения.');
        }

        return self::toRpn($tokens);
    }

    /**
     * @return array<int,array{type:string,value:mixed}>
     */
    private static function tokenize(string $expression): array
    {
        $tokens = [];
        $length = strlen($expression);
        $position = 0;

        while ($position < $length) {
            $char = $expression[$position];

            if (ctype_space($char)) {
                $position++;
                continue;
            }

            if (ctype_digit($char) || $char === '.') {
                $tokens[] = [
                    'type' => 'number',
                    'value' => self::parseNumber($expression, $position),
                ];
                continue;
            }

            if ($char === '-' && self::needsLeadingZero($tokens)) {
                $tokens[] = ['type' => 'number', 'value' => 0.0];
                $tokens[] = ['type' => 'operator', 'value' => '-'];
                $position++;
                continue;
            }

            if (isset(self::OPERATORS[$char])) {
                $tokens[] = ['type' => 'operator', 'value' => $char];
                $position++;
                continue;
            }

            if ($char === '(' || $char === ')') {
                $tokens[] = ['type' => 'parenthesis', 'value' => $char];
                $position++;
                continue;
            }

            if (ctype_alpha($char)) {
                $tokens[] = self::parseVariable($expression, $position);
                continue;
            }

            throw new InvalidArgumentException("Недопустимый символ '{$char}' в формуле.");
        }

        return $tokens;
    }

    private static function parseNumber(string $expression, int &$position): float
    {
        $number = '';
        $length = strlen($expression);
        $dotCount = 0;

        while ($position < $length) {
            $char = $expression[$position];

            if ($char === '.') {
                $dotCount++;
                if ($dotCount > 1) {
                    throw new InvalidArgumentException('Некорректное число в формуле.');
                }
                $number .= $char;
                $position++;
                continue;
            }

            if (ctype_digit($char)) {
                $number .= $char;
                $position++;
                continue;
            }

            break;
        }

        if ($number === '' || $number === '.') {
            throw new InvalidArgumentException('Некорректное число в формуле.');
        }

        return (float)$number;
    }

    /**
     * @return array{type:string,value:string}
     */
    private static function parseVariable(string $expression, int &$position): array
    {
        $identifier = '';
        $length = strlen($expression);

        while ($position < $length) {
            $char = $expression[$position];
            if (!ctype_alpha($char)) {
                break;
            }
            $identifier .= $char;
            $position++;
        }

        $identifier = strtoupper($identifier);
        $map = [
            'T' => 'T',
            'TEMPERATURE' => 'T',
            'W' => 'W',
            'WEIGHT' => 'W',
        ];

        if (!isset($map[$identifier])) {
            throw new InvalidArgumentException("Неизвестная переменная '{$identifier}'. Используйте T (температура) и W (вес).");
        }

        return ['type' => 'variable', 'value' => $map[$identifier]];
    }

    private static function needsLeadingZero(array $tokens): bool
    {
        if (empty($tokens)) {
            return true;
        }

        $last = end($tokens);
        if ($last['type'] === 'operator') {
            return true;
        }
        if ($last['type'] === 'parenthesis' && $last['value'] === '(') {
            return true;
        }

        return false;
    }

    /**
     * @param array<int,array{type:string,value:mixed}> $tokens
     * @return array<int,array{type:string,value:mixed}>
     */
    private static function toRpn(array $tokens): array
    {
        $output = [];
        $stack = [];

        foreach ($tokens as $token) {
            if ($token['type'] === 'number' || $token['type'] === 'variable') {
                $output[] = $token;
                continue;
            }

            if ($token['type'] === 'operator') {
                $op1 = $token['value'];
                while (!empty($stack)) {
                    $top = end($stack);
                    if ($top['type'] !== 'operator') {
                        break;
                    }

                    $op2 = $top['value'];
                    $precedence1 = self::OPERATORS[$op1]['precedence'];
                    $precedence2 = self::OPERATORS[$op2]['precedence'];
                    $assoc1 = self::OPERATORS[$op1]['assoc'];

                    if (
                        ($assoc1 === 'left' && $precedence1 <= $precedence2) ||
                        ($assoc1 === 'right' && $precedence1 < $precedence2)
                    ) {
                        $output[] = array_pop($stack);
                        continue;
                    }

                    break;
                }
                $stack[] = $token;
                continue;
            }

            if ($token['type'] === 'parenthesis') {
                if ($token['value'] === '(') {
                    $stack[] = $token;
                } else {
                    while (!empty($stack) && end($stack)['type'] !== 'parenthesis') {
                        $output[] = array_pop($stack);
                    }
                    if (empty($stack) || end($stack)['value'] !== '(') {
                        throw new InvalidArgumentException('Скобки расставлены некорректно.');
                    }
                    array_pop($stack);
                }
            }
        }

        while (!empty($stack)) {
            $token = array_pop($stack);
            if ($token['type'] === 'parenthesis') {
                throw new InvalidArgumentException('Скобки расставлены некорректно.');
            }
            $output[] = $token;
        }

        return $output;
    }

    /**
     * @param array<int,array{type:string,value:mixed}> $rpn
     * @param array{T:float,W:float} $variables
     */
    private static function evaluateRpn(array $rpn, array $variables): float
    {
        $stack = [];

        foreach ($rpn as $token) {
            if ($token['type'] === 'number') {
                $stack[] = (float)$token['value'];
                continue;
            }

            if ($token['type'] === 'variable') {
                $varName = $token['value'];
                if (!array_key_exists($varName, $variables)) {
                    throw new InvalidArgumentException("Неизвестная переменная {$varName}.");
                }
                $stack[] = (float)$variables[$varName];
                continue;
            }

            if ($token['type'] === 'operator') {
                if (count($stack) < 2) {
                    throw new InvalidArgumentException('Недостаточно операндов в формуле.');
                }
                $b = array_pop($stack);
                $a = array_pop($stack);
                $stack[] = self::applyOperator($token['value'], $a, $b);
                continue;
            }
        }

        if (count($stack) !== 1) {
            throw new InvalidArgumentException('Не удалось вычислить формулу.');
        }

        $result = array_pop($stack);
        if (!is_finite($result)) {
            throw new InvalidArgumentException('Формула возвращает некорректное значение.');
        }

        return $result;
    }

    private static function applyOperator(string $operator, float $a, float $b): float
    {
        return match ($operator) {
            '+' => $a + $b,
            '-' => $a - $b,
            '*' => $a * $b,
            '/' => self::safeDivide($a, $b),
            '^' => pow($a, $b),
            default => throw new InvalidArgumentException('Недопустимый оператор в формуле.'),
        };
    }

    private static function safeDivide(float $a, float $b): float
    {
        if (abs($b) < 1e-9) {
            throw new InvalidArgumentException('Деление на ноль в формуле.');
        }
        return $a / $b;
    }
}

