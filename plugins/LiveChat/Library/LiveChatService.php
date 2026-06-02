<?php
declare(strict_types=1);

namespace App\Plugin\LiveChat\Library;

use App\Util\Client;
use App\Util\Date;
use Illuminate\Database\Capsule\Manager as DB;
use Kernel\Exception\JSONException;

class LiveChatService
{
    public const STATUS_OPEN = 0;
    public const STATUS_CLOSED = 1;
    public const PLUGIN_KEY = 'LiveChat';
    private const TOKEN_COOKIE_NAME = 'livechat_token';
    private const TOKEN_COOKIE_LIFETIME = 86400;
    private const SESSION_EXPIRE_SECONDS = 86400;

    private static ?bool $hasFingerprintColumn = null;
    private static ?bool $tablesInstalled = null;

    public static function normalizeToken(?string $token): string
    {
        $token = trim((string)$token);
        return preg_match('/^[A-Za-z0-9_-]{32,64}$/', $token) ? $token : '';
    }

    public static function createToken(): string
    {
        return bin2hex(random_bytes(24));
    }

    public static function setTokenCookie(string $token): void
    {
        if (headers_sent()) {
            return;
        }

        setcookie(self::TOKEN_COOKIE_NAME, $token, [
            'expires' => time() + self::TOKEN_COOKIE_LIFETIME,
            'path' => '/',
            'secure' => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        $_COOKIE[self::TOKEN_COOKIE_NAME] = $token;
    }

    public static function getTokenFromCookie(): string
    {
        $token = $_COOKIE[self::TOKEN_COOKIE_NAME] ?? '';
        return is_string($token) ? self::normalizeToken($token) : '';
    }

    public static function clearTokenCookie(): void
    {
        if (!headers_sent()) {
            setcookie(self::TOKEN_COOKIE_NAME, '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => self::isHttps(),
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
        }
        unset($_COOKIE[self::TOKEN_COOKIE_NAME]);
    }

    public static function trimMessage(string $content): string
    {
        $content = trim(str_replace(["\r\n", "\r"], "\n", $content));
        if ($content === '') {
            throw new JSONException('消息内容不能为空');
        }

        if (mb_strlen($content, 'UTF-8') > 1000) {
            throw new JSONException('消息内容不能超过1000个字符');
        }

        return $content;
    }

    public static function sessionByToken(string $token): ?object
    {
        self::assertTablesInstalled();

        $token = self::normalizeToken($token);
        if ($token === '') {
            return null;
        }

        $row = DB::table('plugin_livechat_session')->where('visitor_token', $token)->first();
        if (!$row || self::isTokenExpired($row)) {
            return null;
        }

        return $row;
    }

    public static function intakeCategories(): array
    {
        return [
            'order' => '订单/支付问题',
            'delivery' => '发货/卡密问题',
            'account' => '账号/使用问题',
            'other' => '其他咨询',
        ];
    }

    public static function ensureSession(string $token, array $config = [], array $intake = []): object
    {
        self::assertTablesInstalled();

        $existingToken = self::normalizeToken($token);
        $row = $existingToken ? self::sessionByToken($existingToken) : null;
        if ($row) {
            return $row;
        }

        $intake = self::normalizeIntake($intake);
        self::assertSessionCreateAllowed($config);

        $now = Date::current();
        $token = self::createToken();
        $hasFingerprint = self::hasFingerprintColumn();
        $fingerprint = self::generateFingerprint();
        $id = DB::transaction(function () use ($token, $config, $intake, $now, $hasFingerprint, $fingerprint) {
            $session = [
                'visitor_token' => $token,
                'visitor_name' => self::intakeVisitorName($intake, $token),
                'status' => self::STATUS_OPEN,
                'last_message' => null,
                'last_sender' => null,
                'last_msg_at' => $now,
                'client_ip' => self::safeClientIp(),
                'user_agent' => self::safeUserAgent(),
                'create_time' => $now,
                'update_time' => $now,
            ];
            if ($hasFingerprint) {
                $session['client_fingerprint'] = $fingerprint;
            }

            $id = DB::table('plugin_livechat_session')->insertGetId($session);

            $welcome = trim((string)($config['welcome_text'] ?? ''));
            if ($welcome !== '') {
                self::addMessage((int)$id, 'admin', $welcome, false);
            }

            self::addMessage((int)$id, 'system', self::intakeSummary($intake), false);
            self::addMessage((int)$id, 'visitor', $intake['message']);

            return $id;
        });

        return DB::table('plugin_livechat_session')->where('id', $id)->first();
    }

    public static function normalizeIntake(array $intake): array
    {
        $categories = self::intakeCategories();
        $category = self::cleanLine((string)($intake['category'] ?? ''), 32);
        if (!isset($categories[$category])) {
            throw new JSONException('请选择咨询分类');
        }

        $email = self::cleanLine((string)($intake['email'] ?? ''), 120);
        if (!self::validateEmail($email)) {
            throw new JSONException('请填写正确的邮箱');
        }

        $qq = self::cleanLine((string)($intake['qq'] ?? ''), 20);
        if (!self::validateQQ($qq)) {
            throw new JSONException('QQ格式不正确');
        }

        $orderNo = self::cleanLine((string)($intake['order_no'] ?? ''), 80);
        if (!self::validateOrderNo($orderNo)) {
            throw new JSONException('订单号格式不正确');
        }
        $message = self::trimMessage((string)($intake['message'] ?? ''));

        return [
            'category' => $category,
            'category_label' => $categories[$category],
            'email' => $email,
            'qq' => $qq,
            'order_no' => $orderNo,
            'message' => $message,
        ];
    }

    public static function validateEmail(string $email): bool
    {
        return $email !== ''
            && mb_strlen($email, 'UTF-8') <= 120
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function validateQQ(string $qq): bool
    {
        return $qq === '' || preg_match('/^\d{4,20}$/', $qq) === 1;
    }

    public static function validateOrderNo(string $orderNo): bool
    {
        return mb_strlen($orderNo, 'UTF-8') <= 80;
    }

    private static function cleanLine(string $value, int $maxLength): string
    {
        $value = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '');
        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }

    private static function intakeVisitorName(array $intake, string $token): string
    {
        if ($intake['email'] !== '') {
            return mb_substr($intake['email'], 0, 64, 'UTF-8');
        }

        return '访客-' . substr($token, 0, 6);
    }

    private static function intakeSummary(array $intake): string
    {
        $lines = [
            '咨询资料',
            '分类：' . $intake['category_label'],
            '邮箱：' . $intake['email'],
        ];

        if ($intake['qq'] !== '') {
            $lines[] = 'QQ：' . $intake['qq'];
        }
        if ($intake['order_no'] !== '') {
            $lines[] = '订单号：' . $intake['order_no'];
        }

        return implode("\n", $lines);
    }

    public static function addMessage(int $sessionId, string $sender, string $content, bool $touchSession = true): int
    {
        self::assertTablesInstalled();

        $content = self::trimMessage($content);
        $now = Date::current();
        $id = DB::table('plugin_livechat_message')->insertGetId([
            'session_id' => $sessionId,
            'sender' => $sender,
            'content' => $content,
            'is_read' => $sender === 'visitor' ? 0 : 1,
            'create_time' => $now,
        ]);

        if ($touchSession) {
            DB::table('plugin_livechat_session')->where('id', $sessionId)->update([
                'last_message' => mb_substr($content, 0, 250, 'UTF-8'),
                'last_sender' => $sender,
                'last_msg_at' => $now,
                'update_time' => $now,
            ]);
        }

        return (int)$id;
    }

    public static function assertSessionCreateAllowed(array $config = []): void
    {
        self::assertTablesInstalled();

        $limit = max(0, (int)($config['session_create_limit_per_hour'] ?? 20));
        $ip = self::safeClientIp();
        if ($limit <= 0 || $ip === '') {
            return;
        }

        $query = DB::table('plugin_livechat_session')
            ->where('create_time', '>=', date('Y-m-d H:i:s', time() - 3600));
        self::applyClientFingerprintScope($query);
        $created = $query->count();

        if ($created >= $limit) {
            throw new JSONException('咨询请求过于频繁，请稍后再试');
        }
    }

    public static function assertVisitorMessageAllowed(int $sessionId, array $config = []): void
    {
        self::assertTablesInstalled();

        $limit = max(0, (int)($config['ip_message_limit_per_minute'] ?? 30));
        $ip = self::safeClientIp();
        if ($limit <= 0 || $ip === '') {
            return;
        }

        $query = DB::table('plugin_livechat_message as message')
            ->join('plugin_livechat_session as session', 'session.id', '=', 'message.session_id')
            ->where('message.sender', 'visitor')
            ->where('message.create_time', '>=', date('Y-m-d H:i:s', time() - 60));
        self::applyClientFingerprintScope($query, 'session');
        $sent = $query->count();

        if ($sent >= $limit) {
            throw new JSONException('发送过于频繁，请稍后再试');
        }
    }

    public static function messages(int $sessionId, int $afterId = 0, bool $includeSessionId = true): array
    {
        self::assertTablesInstalled();

        $query = DB::table('plugin_livechat_message')
            ->where('session_id', $sessionId)
            ->orderBy('id', 'asc')
            ->limit(200);

        if ($afterId > 0) {
            $query->where('id', '>', $afterId);
        }
        if (!$includeSessionId) {
            $query->where('sender', '<>', 'system');
        }

        return array_map(
            $includeSessionId ? [self::class, 'messageToArray'] : [self::class, 'clientMessageToArray'],
            $query->get()->all()
        );
    }

    public static function closeSession(int $sessionId, string $closedBy): void
    {
        self::assertTablesInstalled();

        $row = DB::table('plugin_livechat_session')->where('id', $sessionId)->first();
        if (!$row) {
            throw new JSONException('会话不存在');
        }

        $now = Date::current();
        DB::transaction(function () use ($sessionId, $closedBy, $now) {
            DB::table('plugin_livechat_session')->where('id', $sessionId)->update([
                'status' => self::STATUS_CLOSED,
                'last_message' => null,
                'last_sender' => null,
                'last_msg_at' => $now,
                'closed_by' => $closedBy,
                'closed_at' => $now,
                'update_time' => $now,
            ]);
        });
    }

    public static function listSessions(?int $status = null): array
    {
        self::assertTablesInstalled();

        $query = DB::table('plugin_livechat_session')->orderBy('last_msg_at', 'desc')->limit(100);
        if ($status !== null && in_array($status, [self::STATUS_OPEN, self::STATUS_CLOSED], true)) {
            $query->where('status', $status);
        }

        $rows = $query->get()->all();
        return array_map(function ($row) {
            $unread = DB::table('plugin_livechat_message')
                ->where('session_id', (int)$row->id)
                ->where('sender', 'visitor')
                ->where('is_read', 0)
                ->count();

            return self::sessionToArray($row) + ['unread' => (int)$unread];
        }, $rows);
    }

    public static function markVisitorMessagesRead(int $sessionId): void
    {
        self::assertTablesInstalled();

        DB::table('plugin_livechat_message')
            ->where('session_id', $sessionId)
            ->where('sender', 'visitor')
            ->where('is_read', 0)
            ->update(['is_read' => 1]);
    }

    public static function sessionToArray(object $row): array
    {
        return [
            'id' => (int)$row->id,
            'visitor_name' => (string)($row->visitor_name ?? ''),
            'status' => (int)$row->status,
            'status_text' => (int)$row->status === self::STATUS_CLOSED ? '已结束' : '进行中',
            'last_message' => (string)($row->last_message ?? ''),
            'last_sender' => (string)($row->last_sender ?? ''),
            'last_msg_at' => (string)($row->last_msg_at ?? ''),
            'closed_by' => (string)($row->closed_by ?? ''),
            'closed_at' => (string)($row->closed_at ?? ''),
            'client_ip' => (string)($row->client_ip ?? ''),
            'user_agent' => (string)($row->user_agent ?? ''),
            'create_time' => (string)($row->create_time ?? ''),
            'update_time' => (string)($row->update_time ?? ''),
        ];
    }

    public static function clientSessionToArray(object $row): array
    {
        return [
            'status' => (int)$row->status,
            'status_text' => (int)$row->status === self::STATUS_CLOSED ? '已结束' : '进行中',
            'last_msg_at' => (string)($row->last_msg_at ?? ''),
            'closed_at' => (string)($row->closed_at ?? ''),
        ];
    }

    public static function messageToArray(object $row): array
    {
        return [
            'id' => (int)$row->id,
            'session_id' => (int)$row->session_id,
            'sender' => (string)$row->sender,
            'content' => (string)$row->content,
            'is_read' => (int)$row->is_read,
            'create_time' => (string)$row->create_time,
        ];
    }

    public static function clientMessageToArray(object $row): array
    {
        return [
            'id' => (int)$row->id,
            'sender' => (string)$row->sender,
            'content' => (string)$row->content,
            'create_time' => (string)$row->create_time,
        ];
    }

    public static function safeClientIp(): string
    {
        try {
            return mb_substr(Client::getAddress(), 0, 64, 'UTF-8');
        } catch (\Throwable) {
            return '';
        }
    }

    public static function safeUserAgent(): string
    {
        try {
            return mb_substr(Client::getUserAgent(), 0, 255, 'UTF-8');
        } catch (\Throwable) {
            return '';
        }
    }

    public static function generateFingerprint(): string
    {
        return hash('sha256', self::safeClientIp() . '|' . self::safeUserAgent());
    }

    public static function isTokenExpired(object $session): bool
    {
        $time = strtotime((string)($session->last_msg_at ?? $session->update_time ?? $session->create_time ?? ''));
        if ($time <= 0) {
            return true;
        }

        return (time() - $time) > self::SESSION_EXPIRE_SECONDS;
    }

    private static function hasFingerprintColumn(): bool
    {
        self::assertTablesInstalled();

        if (self::$hasFingerprintColumn !== null) {
            return self::$hasFingerprintColumn;
        }

        try {
            self::$hasFingerprintColumn = DB::schema()->hasColumn('plugin_livechat_session', 'client_fingerprint');
        } catch (\Throwable) {
            self::$hasFingerprintColumn = false;
        }

        return self::$hasFingerprintColumn;
    }

    private static function assertTablesInstalled(): void
    {
        if (self::$tablesInstalled === true) {
            return;
        }

        try {
            $schema = DB::schema();
            $installed = $schema->hasTable('plugin_livechat_session')
                && $schema->hasTable('plugin_livechat_message');
        } catch (\Throwable) {
            $installed = false;
        }

        if (!$installed) {
            throw new JSONException('LiveChat 数据表未安装，请重新安装插件或手动执行 install.sql');
        }

        self::$tablesInstalled = true;
    }

    private static function applyClientFingerprintScope($query, string $sessionAlias = ''): void
    {
        $prefix = $sessionAlias === '' ? '' : $sessionAlias . '.';
        if (self::hasFingerprintColumn()) {
            $query->where($prefix . 'client_fingerprint', self::generateFingerprint());
            return;
        }

        $query->where($prefix . 'client_ip', self::safeClientIp());
        $userAgent = self::safeUserAgent();
        if ($userAgent !== '') {
            $query->where($prefix . 'user_agent', $userAgent);
        }
    }

    private static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }
}
