<?php

namespace App\Support;

/** First-party UI assets must not depend on cached CDN settings. */
final class UiAssets
{
    private const FILES = [
        'bootstrap.css' => 'css/buildino-bootstrap.rtl.min.css',
        'bootstrap.js' => 'js/buildino-bootstrap.bundle.min.js',
        'sweetalert2.css' => 'css/buildino-sweetalert2.min.css',
        'sweetalert2.js' => 'js/buildino-sweetalert2.all.min.js',
        'jdate.js' => 'js/buildino-jdate.js',
    ];

    public static function integrity(string $name): string
    {
        static $hashes = [];

        return $hashes[$name] ??= 'sha384-'.base64_encode(hash_file('sha384', public_path(self::FILES[$name]), true));
    }

    public static function url(string $name): string
    {
        return asset(self::FILES[$name]).'?v='.substr(hash('sha256', self::integrity($name)), 0, 12);
    }
}
