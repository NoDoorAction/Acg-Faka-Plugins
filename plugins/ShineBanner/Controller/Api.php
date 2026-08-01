<?php
declare(strict_types=1);

namespace App\Plugin\ShineBanner\Controller;

use App\Controller\Base\API\ManagePlugin;
use App\Interceptor\ManageSession;
use App\Interceptor\Waf;
use Kernel\Annotation\Interceptor;
use Kernel\Exception\JSONException;

#[Interceptor([Waf::class, ManageSession::class], Interceptor::TYPE_API)]
class Api extends ManagePlugin
{
    /**
     * 保存全部 Banner 列表 + 轮播设置
     * POST /plugin/ShineBanner/Api/save
     *
     * @throws JSONException
     */
    public function save(): array
    {
        // Framework Request constructor runs htmlspecialchars() on all $_POST values.
        // html_entity_decode reverses that before we parse the JSON.
        $rawBanners = html_entity_decode($_POST['banners'] ?? '[]', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $interval   = max(500, (int)($_POST['interval'] ?? 4000));
        $height     = max(100, (int)($_POST['height'] ?? 360));
        $width      = max(200, (int)($_POST['width'] ?? 1680));

        $banners = json_decode($rawBanners, true);
        if (!is_array($banners)) {
            throw new JSONException("数据格式错误，请刷新页面重试");
        }

        // 清理并重新编号
        $clean = [];
        foreach ($banners as $i => $b) {
            if (empty($b['image'])) {
                continue;
            }
            $clean[] = [
                'id'     => $i + 1,
                'image'  => trim((string)$b['image']),
                'link'   => trim((string)($b['link'] ?? '')),
                'target' => in_array($b['target'] ?? '', ['_blank', '_self']) ? $b['target'] : '_self',
                'sort'   => (int)($b['sort'] ?? $i),
                'status' => (int)($b['status'] ?? 1) === 1 ? 1 : 0,
            ];
        }

        // 按 sort 排序
        usort($clean, fn($a, $b) => $a['sort'] <=> $b['sort']);

        $config = \App\Util\Plugin::getConfig("ShineBanner", false);
        $config['banners']  = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $config['interval'] = (string)$interval;
        $config['height']   = (string)$height;
        $config['width']    = (string)$width;

        setConfig($config, BASE_PATH . '/app/Plugin/ShineBanner/Config/Config.php');

        return $this->json(200, "保存成功");
    }
}
