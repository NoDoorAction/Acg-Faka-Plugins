<?php
declare(strict_types=1);

namespace App\Plugin\LiveChat\Controller;

use App\Controller\Base\API\ManagePlugin;
use App\Interceptor\ManageSession;
use App\Plugin\LiveChat\Library\Csrf;
use App\Plugin\LiveChat\Library\LiveChatService;
use Illuminate\Database\Capsule\Manager as DB;
use Kernel\Annotation\Interceptor;
use Kernel\Exception\JSONException;
use Kernel\Waf\Filter;

#[Interceptor(ManageSession::class, Interceptor::TYPE_API)]
class Console extends ManagePlugin
{
    public function sessions(): array
    {
        $this->assertPost();
        $rawStatus = (string)$this->request->post('status', Filter::NORMAL);
        $status = $rawStatus === '' ? null : (int)$this->request->post('status', Filter::INTEGER);

        return $this->json(200, 'success', [
            'list' => LiveChatService::listSessions($status),
        ]);
    }

    public function messages(): array
    {
        $this->assertPost();
        $sessionId = $this->validateSessionId((int)$this->request->post('session_id', Filter::INTEGER));
        $session = DB::table('plugin_livechat_session')->where('id', $sessionId)->first();
        if (!$session) {
            throw new JSONException('会话不存在');
        }

        LiveChatService::markVisitorMessagesRead($sessionId);
        return $this->json(200, 'success', [
            'session' => LiveChatService::sessionToArray($session),
            'messages' => LiveChatService::messages($sessionId),
        ]);
    }

    public function reply(): array
    {
        $this->assertPost();
        $sessionId = $this->validateSessionId((int)$this->request->post('session_id', Filter::INTEGER));
        $session = DB::table('plugin_livechat_session')->where('id', $sessionId)->first();
        if (!$session) {
            throw new JSONException('会话不存在');
        }
        if ((int)$session->status === LiveChatService::STATUS_CLOSED) {
            throw new JSONException('会话已结束，不能继续回复');
        }

        LiveChatService::addMessage($sessionId, 'admin', (string)$this->request->post('content', Filter::NORMAL));
        $session = DB::table('plugin_livechat_session')->where('id', $sessionId)->first();

        return $this->json(200, '回复成功', [
            'session' => LiveChatService::sessionToArray($session),
            'messages' => LiveChatService::messages($sessionId),
        ]);
    }

    public function end(): array
    {
        $this->assertPost();
        $sessionId = $this->validateSessionId((int)$this->request->post('session_id', Filter::INTEGER));
        LiveChatService::closeSession($sessionId, 'admin');
        $session = DB::table('plugin_livechat_session')->where('id', $sessionId)->first();

        return $this->json(200, '会话已结束，历史消息已保留', [
            'session' => LiveChatService::sessionToArray($session),
            'messages' => LiveChatService::messages($sessionId),
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

    private function validateSessionId(int $sessionId): int
    {
        if ($sessionId <= 0) {
            throw new JSONException('无效的会话ID');
        }

        return $sessionId;
    }
}
