<?php

if (! defined('ABSPATH')) {
    exit;
}

class TerzoConto_Admin_Validator {
    public function is_valid_date(string $value): bool {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value;
    }

    public function is_valid_money(float $value): bool {
        return $value > 0 && $value < 1000000000;
    }

    public function is_valid_conto_name(string $name): bool {
        $len = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
        return $len >= 2 && $len <= 120;
    }

    public function is_valid_short_text(string $text, int $max = 255): bool {
        $len = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
        return $len <= $max;
    }

    public function sanitize_csv_cell(string $value): string {
        $trimmed = ltrim($value);
        if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@'], true)) {
            return "'" . $value;
        }

        return $value;
    }
}
