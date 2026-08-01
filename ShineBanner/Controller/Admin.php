<?php
declare(strict_types=1);

namespace App\Plugin\ShineBanner\Controller;

use App\Controller\Base\View\ManagePlugin;
use App\Interceptor\ManageSession;
use App\Interceptor\Waf;
use App\Plugin\ShineBanner\Library\Csrf;
use App\Util\Plugin;
use Kernel\Annotation\Interceptor;

#[Interceptor([Waf::class, ManageSession::class], Interceptor::TYPE_VIEW)]
class Admin extends ManagePlugin
{
    public function index(): string
    {
        $config  = Plugin::getConfig("ShineBanner");
        $banners = json_decode($config['banners'] ?? '[]', true) ?: [];

        $data = [
            'banners_b64'  => base64_encode(json_encode($banners, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'interval'     => (int)($config['interval'] ?? 4000),
            'height'       => (int)($config['height'] ?? 360),
            'width'        => (int)($config['width'] ?? 1680),
            'csrf_token'   => Csrf::getToken(),
            'toolbar'  => [
                ['name' => '🤡 基本设置',  'url' => '/admin/config/index'],
                ['name' => '👹 短信设置',  'url' => '/admin/config/sms'],
                ['name' => '👺 邮箱设置',  'url' => '/admin/config/email'],
                ['name' => '🛡️ 其他设置', 'url' => '/admin/config/other'],
                ['name' => '🖼️ Banner管理', 'url' => '/plugin/ShineBanner/Admin/index'],
            ],
        ];

        return $this->render(
            title: 'Banner管理',
            template: 'ShineBannerAdmin.html',
            data: $data,
            controller: true,
        );
    }
}
