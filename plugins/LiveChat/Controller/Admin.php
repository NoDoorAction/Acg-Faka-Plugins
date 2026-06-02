<?php
declare(strict_types=1);

namespace App\Plugin\LiveChat\Controller;

use App\Consts\Plugin as PluginConst;
use App\Controller\Base\View\ManagePlugin;
use App\Interceptor\ManageSession;
use App\Plugin\LiveChat\Library\Csrf;
use App\Plugin\LiveChat\Library\LiveChatService;
use Kernel\Annotation\Interceptor;
use Kernel\Exception\ViewException;
use Kernel\Util\Plugin as PluginInfo;

#[Interceptor(ManageSession::class)]
class Admin extends ManagePlugin
{
    /**
     * @throws ViewException
     */
    public function console(): string
    {
        $plugin = PluginInfo::getPlugin(LiveChatService::PLUGIN_KEY) ?: [];
        $version = preg_replace('/[^A-Za-z0-9._-]/', '', (string)($plugin[PluginConst::VERSION] ?? '1.0.0')) ?: '1.0.0';

        return $this->render(
            title: '在线客服工作台',
            template: 'Console.html',
            data: [
                'csrf_token' => Csrf::getToken(),
                'asset_version' => $version,
            ],
            controller: true,
        );
    }
}
