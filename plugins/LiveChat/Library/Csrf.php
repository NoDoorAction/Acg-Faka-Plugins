<?php
declare(strict_types=1);

namespace App\Plugin\LiveChat\Library;

use Kernel\Util\Session;

class Csrf
{
    public const TOKEN_NAME = 'livechat_csrf_token';
    private const TOKEN_LENGTH = 32;
    private const TOKEN_LIFETIME = 86400;

    public static function generateToken(): string
    {
        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        Session::set(self::TOKEN_NAME, $token);
        Session::set(self::TOKEN_NAME . '_time', time());

        return $token;
    }

    public static function getToken(): string
    {
        $token = Session::get(self::TOKEN_NAME);
        $timestamp = Session::get(self::TOKEN_NAME . '_time');

        if (!is_string($token) || $token === '' || $timestamp === null || self::isExpired((int)$timestamp)) {
            return self::generateToken();
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

        if (!is_string($sessionToken) || $sessionToken === '' || $timestamp === null) {
            return false;
        }

        if (self::isExpired((int)$timestamp)) {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    private static function isExpired(int $timestamp): bool
    {
        return (time() - $timestamp) > self::TOKEN_LIFETIME;
    }

    public static function clearToken(): void
    {
        Session::remove(self::TOKEN_NAME);
        Session::remove(self::TOKEN_NAME . '_time');
    }
}
