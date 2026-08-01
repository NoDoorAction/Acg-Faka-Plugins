<?php
declare(strict_types=1);

namespace App\Plugin\ShineBanner\Hook;

use App\Util\Plugin;
use App\Plugin\ShineBanner\Library\Url;
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
<style>
.shine-banner-wrap{max-width:<?php echo $width; ?>px;width:calc(100% - 120px);margin:14px auto 0;padding:0 32px;}
.shine-banner-carousel{position:relative;border-radius:14px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.10);}
.shine-banner-slide{display:none;}
.shine-banner-slide.is-active{display:block;}
.shine-banner-slide a,.shine-banner-slide{display:block;}
.shine-banner-slide img{width:100%;height:<?php echo $height; ?>px;object-fit:cover;display:block;}
.shine-banner-prev,.shine-banner-next{position:absolute;top:50%;z-index:1;transform:translateY(-50%);border:0;background:rgba(0,0,0,.3);color:#fff;font-size:28px;line-height:36px;width:36px;height:36px;border-radius:50%;cursor:pointer;}
.shine-banner-prev{left:14px}.shine-banner-next{right:14px}
.shine-banner-dots{position:absolute;bottom:10px;left:0;right:0;text-align:center;}
.shine-banner-dot{display:inline-block;width:8px;height:8px;margin:0 4px;border:0;border-radius:50%;background:rgba(255,255,255,.6);cursor:pointer;padding:0;}
.shine-banner-dot.is-active{background:#fff;}
@media(max-width:768px){
  .shine-banner-wrap{padding:0 14px;width:auto;}
  .shine-banner-slide img{height:<?php echo $mHeight; ?>px;}
  .shine-banner-prev,.shine-banner-next{display:none;}
}
</style>
<div id="shine-banner-portal">
<div class="shine-banner-wrap">
  <div class="shine-banner-carousel" data-interval="<?php echo $interval; ?>">
    <div class="shine-banner-slides">
      <?php foreach ($banners as $b): ?>
      <?php $image = Url::sanitize((string)($b['image'] ?? '')); $link = Url::sanitize((string)($b['link'] ?? '')); ?>
      <?php if ($image !== ''): ?>
      <div class="shine-banner-slide">
        <?php if ($link !== ''): ?>
        <a href="<?php echo htmlspecialchars($link, ENT_QUOTES, 'UTF-8'); ?>" target="<?php echo ($b['target'] ?? '') === '_blank' ? '_blank' : '_self'; ?>" rel="noopener">
          <img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" alt="banner" loading="lazy">
        </a>
        <?php else: ?>
        <img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" alt="banner" loading="lazy">
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <?php endforeach; ?>
    </div>
    <?php if ($multiple): ?>
    <button class="shine-banner-prev" type="button" aria-label="Previous banner">&#8249;</button>
    <button class="shine-banner-next" type="button" aria-label="Next banner">&#8250;</button>
    <div class="shine-banner-dots"></div>
    <?php endif; ?>
  </div>
</div>
</div>
<script>
(function(){
  var carousel=document.querySelector('.shine-banner-carousel');
  var slides=carousel ? carousel.querySelectorAll('.shine-banner-slide') : [];
  if (slides.length > 1) {
    var current=0, dots=carousel.querySelector('.shine-banner-dots'), timer;
    Array.prototype.forEach.call(slides,function(_,index){var dot=document.createElement('button');dot.type='button';dot.className='shine-banner-dot';dot.setAttribute('aria-label','Show banner '+(index+1));dot.onclick=function(){show(index);};dots.appendChild(dot);});
    function show(index){current=(index+slides.length)%slides.length;Array.prototype.forEach.call(slides,function(slide,i){slide.classList.toggle('is-active',i===current);dots.children[i].classList.toggle('is-active',i===current);});}
    function restart(){clearInterval(timer);timer=setInterval(function(){show(current+1);},Number(carousel.dataset.interval)||4000);}
    carousel.querySelector('.shine-banner-prev').onclick=function(){show(current-1);restart();};
    carousel.querySelector('.shine-banner-next').onclick=function(){show(current+1);restart();};
    show(0);restart();
  } else if (slides.length === 1) { slides[0].classList.add('is-active'); }

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
