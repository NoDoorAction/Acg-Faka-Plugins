<?php
declare(strict_types=1);

namespace App\Plugin\LiveChat\Controller;

use App\Controller\Base\API\UserPlugin;
use App\Interceptor\Waf;
use App\Plugin\LiveChat\Library\Csrf;
use App\Plugin\LiveChat\Library\LiveChatService;
use App\Util\Plugin as PluginUtil;
use Illuminate\Database\Capsule\Manager as DB;
use Kernel\Annotation\Interceptor;
use Kernel\Exception\JSONException;
use Kernel\Waf\Filter;

#[Interceptor(Waf::class, Interceptor::TYPE_API)]
class Client extends UserPlugin
{
    public function bootstrap(): array
    {
        $this->assertPost();
        $cfg = PluginUtil::getConfig(LiveChatService::PLUGIN_KEY);
        $token = LiveChatService::getTokenFromCookie();
        $cookieSession = $token !== '' ? LiveChatService::sessionByToken($token) : null;
        if ($token !== '' && !$cookieSession) {
            LiveChatService::clearTokenCookie();
            $token = '';
        } elseif ($cookieSession && (int)$cookieSession->status === LiveChatService::STATUS_CLOSED) {
            LiveChatService::clearTokenCookie();
            $hasIntake = trim((string)$this->request->post('category', Filter::NORMAL)) !== ''
                || trim((string)$this->request->post('message', Filter::NORMAL)) !== '';
            if (!$hasIntake) {
                return $this->json(200, 'success', [
                    'session' => LiveChatService::clientSessionToArray($cookieSession),
                    'messages' => LiveChatService::messages((int)$cookieSession->id, 0, false),
                    'config' => [
                        'title' => (string)($cfg['widget_title'] ?? '在线客服'),
                        'offline_text' => (string)($cfg['offline_text'] ?? ''),
                        'poll_interval_seconds' => max(2, (int)($cfg['poll_interval_seconds'] ?? 4)),
                    ],
                ]);
            }
            $token = '';
        }
        $session = LiveChatService::ensureSession(
            $token,
            $cfg,
            [
                'category' => (string)$this->request->post('category', Filter::NORMAL),
                'email' => (string)$this->request->post('email', Filter::NORMAL),
                'qq' => (string)$this->request->post('qq', Filter::NORMAL),
                'order_no' => (string)$this->request->post('order_no', Filter::NORMAL),
                'message' => (string)$this->request->post('message', Filter::NORMAL),
            ]
        );
        LiveChatService::setTokenCookie((string)$session->visitor_token);

        return $this->json(200, 'success', [
            'session' => LiveChatService::clientSessionToArray($session),
            'messages' => LiveChatService::messages((int)$session->id, 0, false),
            'config' => [
                'title' => (string)($cfg['widget_title'] ?? '在线客服'),
                'offline_text' => (string)($cfg['offline_text'] ?? ''),
                'poll_interval_seconds' => max(2, (int)($cfg['poll_interval_seconds'] ?? 4)),
            ],
        ]);
    }

    public function send(): array
    {
        $this->assertPost();
        $cfg = PluginUtil::getConfig(LiveChatService::PLUGIN_KEY);
        $token = LiveChatService::getTokenFromCookie();
        $session = $token ? LiveChatService::sessionByToken($token) : null;
        if (!$session) {
            LiveChatService::clearTokenCookie();
            throw new JSONException('会话不存在，请重新打开客服窗口');
        }

        if ((int)$session->status === LiveChatService::STATUS_CLOSED) {
            throw new JSONException('会话已结束，请刷新页面后重新发起咨询');
        }

        $limit = max(0, (int)($cfg['rate_limit_seconds'] ?? 2));
        if ($limit > 0) {
            $latest = DB::table('plugin_livechat_message')
                ->where('session_id', (int)$session->id)
                ->where('sender', 'visitor')
                ->orderBy('id', 'desc')
                ->first();
            if ($latest && strtotime((string)$latest->create_time) > time() - $limit) {
                throw new JSONException("发送太快了，请{$limit}秒后再试");
            }
        }

        LiveChatService::assertVisitorMessageAllowed((int)$session->id, $cfg);
        LiveChatService::addMessage((int)$session->id, 'visitor', (string)$this->request->post('content', Filter::NORMAL));
        $session = DB::table('plugin_livechat_session')->where('id', (int)$session->id)->first();

        return $this->json(200, 'success', [
            'session' => LiveChatService::clientSessionToArray($session),
            'messages' => LiveChatService::messages((int)$session->id, 0, false),
        ]);
    }

    public function poll(): array
    {
        $this->assertPost();
        $token = LiveChatService::getTokenFromCookie();
        $session = $token ? LiveChatService::sessionByToken($token) : null;
        if (!$session) {
            LiveChatService::clearTokenCookie();
            throw new JSONException('会话不存在');
        }

        $afterId = max(0, (int)$this->request->post('after_id', Filter::INTEGER));
        if ((int)$session->status === LiveChatService::STATUS_CLOSED) {
            LiveChatService::clearTokenCookie();
        }

        return $this->json(200, 'success', [
            'session' => LiveChatService::clientSessionToArray($session),
            'messages' => LiveChatService::messages((int)$session->id, $afterId, false),
        ]);
    }

    public function end(): array
    {
        $this->assertPost();
        $token = LiveChatService::getTokenFromCookie();
        $session = $token ? LiveChatService::sessionByToken($token) : null;
        if (!$session) {
            LiveChatService::clearTokenCookie();
            throw new JSONException('会话不存在');
        }

        LiveChatService::closeSession((int)$session->id, 'visitor');
        LiveChatService::clearTokenCookie();
        $session = DB::table('plugin_livechat_session')->where('id', (int)$session->id)->first();

        return $this->json(200, '会话已结束', [
            'session' => LiveChatService::clientSessionToArray($session),
            'messages' => LiveChatService::messages((int)$session->id, 0, false),
        ]);
    }

    private function assertPost(): void
    {
        if ($this->request->method() !== 'POST') {
            throw new JSONException('请求方式不正确');
        }

        $token = (string)$this->request->post('_csrf_token', Filter::NORMAL);
        if (!Csrf::validateToken($token)) {
            throw new JSONException('安全验证失败，请刷新页面重试');
        }
    }
}
