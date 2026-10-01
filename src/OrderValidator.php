<?php
declare(strict_types=1);

namespace Pixelite;

final class OrderValidator
{
    public const MAX = ['name' => 100, 'company' => 120, 'email' => 254, 'phone' => 30, 'description' => 3000];

    /**
     * @param array<string,string> $input raw values
     * @return array{0: array<string,string>, 1: array<string,string>} [clean values, errors as field => message key]
     */
    public static function validate(array $input, bool $consent): array
    {
        $single = fn(string $k): string => trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $input[$k] ?? '') ?? '');
        $clean = [
            'name' => $single('name'),
            'company' => $single('company'),
            'email' => $single('email'),
            'phone' => $single('phone'),
            'project_type' => $single('project_type'),
            'budget' => $single('budget'),
            'timeframe' => $single('timeframe'),
            // multi-line: normalise newlines, strip other control chars
            'description' => trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', str_replace("\r\n", "\n", $input['description'] ?? '')) ?? ''),
        ];
        $e = [];
        $len = fn(string $s): int => mb_strlen($s);

        if ($len($clean['name']) < 2) {
            $e['name'] = 'name_required';
        } elseif ($len($clean['name']) > self::MAX['name']) {
            $e['name'] = 'too_long';
        }
        if ($len($clean['company']) > self::MAX['company']) {
            $e['company'] = 'too_long';
        }
        if ($clean['email'] === '') {
            $e['email'] = 'email_required';
        } elseif ($len($clean['email']) > self::MAX['email'] || !filter_var($clean['email'], FILTER_VALIDATE_EMAIL)) {
            $e['email'] = 'email_invalid';
        }
        if ($clean['phone'] !== '' && (!preg_match('/^\+?[0-9 ()\-\/.]{6,30}$/', $clean['phone']))) {
            $e['phone'] = 'phone_invalid';
        }
        if (!in_array($clean['project_type'], OrderOptions::PROJECT_TYPES, true)) {
            $e['project_type'] = 'choose';
        }
        if (!in_array($clean['budget'], OrderOptions::BUDGETS, true)) {
            $e['budget'] = 'choose';
        }
        if (!in_array($clean['timeframe'], OrderOptions::TIMEFRAMES, true)) {
            $e['timeframe'] = 'choose';
        }
        if ($len($clean['description']) < 10) {
            $e['description'] = 'description_short';
        } elseif ($len($clean['description']) > self::MAX['description']) {
            $e['description'] = 'too_long';
        }
        if (!$consent) {
            $e['consent'] = 'consent_required';
        }
        return [$clean, $e];
    }
}
