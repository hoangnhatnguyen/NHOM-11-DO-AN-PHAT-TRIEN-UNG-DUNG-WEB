<?php

class Avatar {
    private const PALETTE = [
        ['bg' => '#E6F4FF', 'fg' => '#005B96'],
        ['bg' => '#E8F8F5', 'fg' => '#0F766E'],
        ['bg' => '#FFF4E5', 'fg' => '#B45309'],
        ['bg' => '#F3E8FF', 'fg' => '#7E22CE'],
        ['bg' => '#FFECEE', 'fg' => '#BE123C'],
        ['bg' => '#EAF2FF', 'fg' => '#1D4ED8'],
    ];

    public static function initials(string $name): string {
        $name = trim($name);
        if ($name === '') {
            return '?';
        }

        return strtoupper(substr($name, 0, 1));
    }

    public static function colors(string $key): array {
        $key = strtolower(trim($key));
        if ($key === '') {
            $key = 'user';
        }

        $hash = (int) sprintf('%u', crc32($key));
        return self::PALETTE[$hash % count(self::PALETTE)];
    }
}
