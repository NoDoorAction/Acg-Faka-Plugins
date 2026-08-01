<?php
declare(strict_types=1);

namespace App\Plugin\ShineBanner\Hook;

use App\Util\Plugin;
use Kernel\Annotation\Hook;

class Main
{
    /**
     * 在后台设置页 Toolbar 注入"Banner管理"入口
     */
    #[Hook(point: \App\Consts\Hook::ADMIN_VIEW_CONFIG_TOOLBAR)]
    public function toolbar(): array
    {
        return ["name" => "🖼️ Banner管理", "url" => "/plugin/ShineBanner/Admin/index"];
    }

    /**
     * 在首页公告下方注入轮播 Banner
     */
    #[Hook(point: \App\Consts\Hook::USER_VIEW_INDEX_BODY)]
    public function banner(): void
    {
        // 默认(Cartoon)主题的 Index/Footer.html 被 Index/Item/Query 共用，
        // USER_VIEW_INDEX_BODY 会在商品详情/结算页、订单查询页一并触发。
        // 用黑名单而非白名单过滤：只排除已知的非首页动作，避免因不同部署环境下
        // 首页路由字符串跟预期不完全一致（是否带 /user 前缀等）而把首页也一并挡掉。
        $route = strtolower(trim(getLocalRouter(), '/'));
        if (str_ends_with($route, '/index/item') || str_ends_with($route, '/index/query')) {
            return;
        }

        $config = Plugin::getConfig("ShineBanner");

        // 框架的插件启用/停用（_plugin_start）写入的是 int 1，不是字符串 '1'，
        // 之前这里用 !== 严格比较导致插件管理里点"启用"之后这个判断恒为真，Banner 永远不显示。
        if ((int)($config['STATUS'] ?? 0) !== 1) {
            return;
        }

        $banners = json_decode($config['banners'] ?? '[]', true);
        if (!is_array($banners) || empty($banners)) {
            return;
        }

        // 过滤已禁用
        $banners = array_values(array_filter($banners, fn($b) => (int)($b['status'] ?? 1) === 1));
        if (empty($banners)) {
            return;
        }

        $interval = max(500, (int)($config['interval'] ?? 4000));
        $height   = max(100, (int)($config['height'] ?? 360));
        $width    = max(200, (int)($config['width'] ?? 1680));
        $mHeight  = max(140, (int)($height * 0.55));
        $multiple = count($banners) > 1;
        ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
<style>
.shine-banner-wrap{max-width:<?php echo $width; ?>px;width:calc(100% - 120px);margin:14px auto 0;padding:0 32px;}
.shine-banner-swiper{border-radius:14px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.10);}
.shine-banner-swiper .swiper-slide a,.shine-banner-swiper .swiper-slide{display:block;}
.shine-banner-swiper .swiper-slide img{width:100%;height:<?php echo $height; ?>px;object-fit:cover;display:block;}
.shine-banner-swiper .swiper-button-next,.shine-banner-swiper .swiper-button-prev{color:#fff;opacity:.75;transition:opacity .15s;}
.shine-banner-swiper .swiper-button-next:hover,.shine-banner-swiper .swiper-button-prev:hover{opacity:1;}
.shine-banner-swiper .swiper-pagination-bullet-active{background:#fff;}
@media(max-width:768px){
  .shine-banner-wrap{padding:0 14px;width:auto;}
  .shine-banner-swiper .swiper-slide img{height:<?php echo $mHeight; ?>px;}
  .shine-banner-swiper .swiper-button-next,.shine-banner-swiper .swiper-button-prev{display:none;}
}
</style>
<div id="shine-banner-portal">
<div class="shine-banner-wrap">
  <div class="swiper shine-banner-swiper">
    <div class="swiper-wrapper">
      <?php foreach ($banners as $b): ?>
      <div class="swiper-slide">
        <?php if (!empty($b['link'])): ?>
        <a href="<?php echo htmlspecialchars($b['link']); ?>" target="<?php echo htmlspecialchars($b['target'] ?? '_self'); ?>" rel="noopener">
          <img src="<?php echo htmlspecialchars($b['image']); ?>" alt="banner" loading="lazy">
        </a>
        <?php else: ?>
        <img src="<?php echo htmlspecialchars($b['image']); ?>" alt="banner" loading="lazy">
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if ($multiple): ?>
    <div class="swiper-pagination"></div>
    <div class="swiper-button-prev"></div>
    <div class="swiper-button-next"></div>
    <?php endif; ?>
  </div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
(function(){
  var opts={loop:<?php echo $multiple ? 'true' : 'false'; ?>,effect:'slide'<?php if($multiple): ?>,autoplay:{delay:<?php echo $interval; ?>,disableOnInteraction:false},pagination:{el:'.swiper-pagination',clickable:true},navigation:{nextEl:'.swiper-button-next',prevEl:'.swiper-button-prev'}<?php endif; ?>};
  new Swiper('.shine-banner-swiper',opts);

  /* 跨主题定位：若 Banner 未紧跟导航栏则自动移位 */
  var portal = document.getElementById('shine-banner-portal');
  if (portal) {
    function _placeBanner() {
      var prev = portal.previousElementSibling;
      if (prev && (prev.id === 'shineNavOverlay' ||
          (prev.className && prev.className.indexOf('shine-nav-overlay') !== -1) ||
          prev.tagName === 'NAV' || prev.tagName === 'HEADER')) return;
      var anchor = document.getElementById('shineNavOverlay') ||
                   document.querySelector('.shine-nav-overlay') ||
                   document.querySelector('nav') ||
                   document.querySelector('header');
      if (anchor) { anchor.parentNode.insertBefore(portal, anchor.nextSibling); }
      else         { document.body.insertBefore(portal, document.body.firstChild); }
    }
    document.readyState === 'loading'
      ? document.addEventListener('DOMContentLoaded', _placeBanner)
      : _placeBanner();
  }
})();
</script>
        <?php
    }
}
