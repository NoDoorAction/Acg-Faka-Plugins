<?php
declare(strict_types=1);

namespace App\Plugin\LiveChat\Hook;

use App\Consts\Plugin as PluginConst;
use App\Consts\Hook;
use App\Controller\Base\View\UserPlugin;
use App\Plugin\LiveChat\Library\Csrf;
use App\Plugin\LiveChat\Library\LiveChatService;
use App\Util\Plugin as PluginUtil;
use Kernel\Annotation\Hook as HookAttr;
use Kernel\Util\Plugin as PluginInfo;

class Main extends UserPlugin
{
    #[HookAttr(point: Hook::USER_GLOBAL_VIEW_FOOTER)]
    public function userFooter(): string
    {
        $cfg = PluginUtil::getConfig(LiveChatService::PLUGIN_KEY);
        $title = htmlspecialchars((string)($cfg['widget_title'] ?? '在线客服'), ENT_QUOTES, 'UTF-8');
        $interval = max(2, (int)($cfg['poll_interval_seconds'] ?? 4));
        $categories = htmlspecialchars(json_encode(LiveChatService::intakeCategories(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
        $csrfToken = htmlspecialchars(Csrf::getToken(), ENT_QUOTES, 'UTF-8');
        $plugin = PluginInfo::getPlugin(LiveChatService::PLUGIN_KEY) ?: [];
        $version = preg_replace('/[^A-Za-z0-9._-]/', '', (string)($plugin[PluginConst::VERSION] ?? '1.0.0')) ?: '1.0.0';

        return <<<HTML
<meta name="csrf-token" content="{$csrfToken}">
<link rel="stylesheet" href="/app/Plugin/LiveChat/View/Widget.css?v={$version}">
<div id="livechat-root" data-title="{$title}" data-poll-interval="{$interval}" data-intake-categories="{$categories}"></div>
<script src="/app/Plugin/LiveChat/View/Widget.js?v={$version}"></script>
HTML;
    }

    #[HookAttr(point: Hook::ADMIN_VIEW_NAV)]
    public function adminNav(): string
    {
        return '<a href="/plugin/LiveChat/admin/console" class="layui-btn layui-btn-xs layui-btn-normal" target="_blank"><i class="fa-duotone fa-regular fa-comments"></i> 在线客服</a>';
    }
}
