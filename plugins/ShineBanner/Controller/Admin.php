<?php
declare(strict_types=1);

namespace App\Plugin\ShineBanner\Controller;

use App\Interceptor\ManageSession;
use App\Interceptor\Waf;
use App\Model\Config as ConfigModel;
use App\Util\Client;
use App\Util\Plugin;
use Kernel\Annotation\Interceptor;

#[Interceptor([Waf::class, ManageSession::class], Interceptor::TYPE_VIEW)]
class Admin extends \App\Controller\Base\View\Manage
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
            'toolbar'  => [
                ['name' => '🤡 基本设置',  'url' => '/admin/config/index'],
                ['name' => '👹 短信设置',  'url' => '/admin/config/sms'],
                ['name' => '👺 邮箱设置',  'url' => '/admin/config/email'],
                ['name' => '🛡️ 其他设置', 'url' => '/admin/config/other'],
                ['name' => '🖼️ Banner管理', 'url' => '/plugin/ShineBanner/Admin/index'],
            ],
        ];

        return $this->renderPlugin('Banner管理', $data);
    }

    private function renderPlugin(string $title, array $data): string
    {
        require(BASE_PATH . "/app/View/Admin/Helper.php");

        $data['title'] = $title;
        $data['app']['version'] = config("app")['version'] ?? '';
        $data['app']['server']  = (int)(config("store")['server'] ?? 0);

        $cfg = ConfigModel::list();
        foreach ($cfg as $k => $v) {
            $data["config"][$k] = $v;
        }

        if (Client::isMobile() && ($data['config']['background_mobile_url'] ?? '')) {
            $data['config']['background_url'] = $data['config']['background_mobile_url'];
        }

        $manage = $this->getManage();
        if ($manage) {
            $data["user"] = $manage->toArray();
            $data['user']['type_text'] = match ((int)$data['user']['type']) {
                0 => "SYSTEM",
                1 => "超级管理员",
                2 => "白班",
                3 => "夜班",
                default => "未知",
            };
        }

        $data['_store_initialize'] = file_exists(BASE_PATH . "/kernel/Plugin.php");

        $engine = new \Smarty();
        $engine->setTemplateDir([
            BASE_PATH . '/app/View',
            __DIR__ . '/../View',
        ]);
        $engine->setCacheDir(BASE_PATH . '/runtime/view/cache');
        $engine->setCompileDir(BASE_PATH . '/runtime/view/compile');
        $engine->left_delimiter  = '#{';
        $engine->right_delimiter = '}';
        $engine->escape_html = false;
        $engine->force_compile = true;

        foreach ($data as $key => $item) {
            $engine->assign($key, $item);
        }

        $result = $engine->fetch('ShineBannerAdmin.html');
        hook(\App\Consts\Hook::RENDER_VIEW, $result);
        return $result;
    }
}
