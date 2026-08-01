<?php
declare(strict_types=1);

namespace App\Plugin\ShineBanner\Library;

use Kernel\Util\Session;

class Csrf
{
    private const TOKEN_NAME = 'shinebanner_csrf_token';
    private const TOKEN_LIFETIME = 86400;

    public static function getToken(): string
    {
        $token = Session::get(self::TOKEN_NAME);
        $timestamp = Session::get(self::TOKEN_NAME . '_time');

        if (!is_string($token) || $token === '' || $timestamp === null || self::isExpired((int)$timestamp)) {
            $token = bin2hex(random_bytes(32));
            Session::set(self::TOKEN_NAME, $token);
            Session::set(self::TOKEN_NAME . '_time', time());
        }

        return $token;
    }

    public static function validateToken(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $sessionToken = Session::get(self::TOKEN_NAME);
        $timestamp = Session::get(self::TOKEN_NAME . '_time');

        return is_string($sessionToken)
            && $sessionToken !== ''
            && $timestamp !== null
            && !self::isExpired((int)$timestamp)
            && hash_equals($sessionToken, $token);
    }

    private static function isExpired(int $timestamp): bool
    {
        return (time() - $timestamp) > self::TOKEN_LIFETIME;
    }
}
