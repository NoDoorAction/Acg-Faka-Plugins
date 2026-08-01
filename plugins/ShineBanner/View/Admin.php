<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Banner 管理</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
<style>
:root{--blue:#0d6efd;--r:10px;}
body{background:#f5f5f7;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:14px;}
.top-bar{background:#fff;border-bottom:1px solid rgba(0,0,0,.08);padding:0 24px;height:54px;display:flex;align-items:center;gap:16px;position:sticky;top:0;z-index:100;}
.top-bar a{color:#6c757d;font-size:13px;text-decoration:none;display:flex;align-items:center;gap:5px;}
.top-bar a:hover{color:#0d6efd;}
.top-bar h1{font-size:16px;font-weight:700;color:#1d1d1f;margin:0;}
.wrap{max-width:960px;margin:24px auto;padding:0 20px 60px;}
.card{background:#fff;border-radius:var(--r);box-shadow:0 1px 4px rgba(0,0,0,.06);border:none;margin-bottom:18px;}
.card-header{padding:14px 20px;border-bottom:1px solid rgba(0,0,0,.06);font-weight:600;font-size:14.5px;display:flex;align-items:center;gap:8px;background:transparent;}
.card-header i{color:#0d6efd;}
.card-body{padding:20px;}
.preview-img{width:96px;height:54px;object-fit:cover;border-radius:6px;border:1px solid rgba(0,0,0,.08);}
.preview-img-placeholder{width:96px;height:54px;background:#f0f0f0;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#aaa;font-size:11px;border:1px solid rgba(0,0,0,.08);}
.badge-on{background:#d1fae5;color:#065f46;border-radius:20px;padding:2px 10px;font-size:11.5px;font-weight:600;}
.badge-off{background:#fef3c7;color:#92400e;border-radius:20px;padding:2px 10px;font-size:11.5px;font-weight:600;}
.btn-icon{border:none;background:transparent;cursor:pointer;padding:4px 8px;border-radius:6px;transition:background .12s;color:#6c757d;}
.btn-icon:hover{background:rgba(0,0,0,.06);color:#1d1d1f;}
.btn-icon.text-danger:hover{background:rgba(220,53,69,.08);color:#dc3545;}
.drag-handle{cursor:grab;color:#ccc;}
.drag-handle:hover{color:#999;}
.empty-tip{text-align:center;padding:40px 20px;color:#aaa;font-size:13.5px;}
.form-label{font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.4px;color:#6c757d;margin-bottom:5px;}
.form-control,.form-select{border-radius:8px;border:1.5px solid rgba(0,0,0,.1);font-size:13.5px;}
.form-control:focus,.form-select:focus{border-color:#0d6efd;box-shadow:0 0 0 3px rgba(13,110,253,.12);}
.btn-save-main{height:40px;padding:0 24px;border-radius:8px;font-weight:600;font-size:13.5px;}
table thead th{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#6c757d;border-bottom:2px solid rgba(0,0,0,.06)!important;background:transparent;}
table td{vertical-align:middle;border-color:rgba(0,0,0,.05);}
</style>
</head>
<body>

<div class="top-bar">
    <a href="/admin/config/index"><i class="fa fa-arrow-left"></i> 返回后台</a>
    <span style="color:#ddd;">|</span>
    <h1><i class="fa fa-image" style="color:#0d6efd;margin-right:6px;"></i>Banner 管理</h1>
</div>

<div class="wrap">

    <!-- 设置卡片 -->
    <div class="card">
        <div class="card-header"><i class="fa fa-sliders"></i> 轮播设置</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">切换间隔（毫秒）</label>
                    <input type="number" id="inputInterval" class="form-control" value="<?php echo (int)$data['interval']; ?>" min="500" step="500" placeholder="4000">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Banner 高度（像素）</label>
                    <input type="number" id="inputHeight" class="form-control" value="<?php echo (int)$data['height']; ?>" min="100" step="10" placeholder="360">
                    <div class="form-text">移动端自动缩小为约 55%</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Banner 最大宽度（像素）</label>
                    <input type="number" id="inputWidth" class="form-control" value="<?php echo (int)($data['width'] ?? 1680); ?>" min="200" step="10" placeholder="1680">
                    <div class="form-text">部分主题内容区较窄时可调小</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 添加 Banner -->
    <div class="card">
        <div class="card-header"><i class="fa fa-plus-circle"></i> 添加 Banner</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">图片地址 <span style="color:red">*</span></label>
                    <input type="url" id="addImage" class="form-control" placeholder="https://example.com/banner.jpg">
                </div>
                <div class="col-md-4">
                    <label class="form-label">跳转链接（留空则不跳转）</label>
                    <input type="url" id="addLink" class="form-control" placeholder="https://example.com">
                </div>
                <div class="col-md-1">
                    <label class="form-label">打开方式</label>
                    <select id="addTarget" class="form-select">
                        <option value="_self">当前页</option>
                        <option value="_blank">新标签</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label">排序</label>
                    <input type="number" id="addSort" class="form-control" value="0" min="0">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button class="btn btn-primary w-100" style="border-radius:8px;height:38px;" onclick="addBanner()">
                        <i class="fa fa-plus"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Banner 列表 -->
    <div class="card">
        <div class="card-header"><i class="fa fa-list"></i> Banner 列表</div>
        <div class="card-body p-0">
            <table class="table mb-0" id="bannerTable">
                <thead>
                    <tr>
                        <th style="width:36px;padding-left:16px;"></th>
                        <th style="width:110px;">预览</th>
                        <th>图片地址</th>
                        <th>链接</th>
                        <th style="width:90px;">打开方式</th>
                        <th style="width:70px;">排序</th>
                        <th style="width:80px;">状态</th>
                        <th style="width:90px;">操作</th>
                    </tr>
                </thead>
                <tbody id="bannerTbody">
                    <!-- JS 渲染 -->
                </tbody>
            </table>
            <div class="empty-tip" id="emptyTip" style="display:none;">
                <i class="fa fa-image" style="font-size:28px;margin-bottom:8px;display:block;"></i>
                暂无 Banner，在上方添加第一张图片
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2">
        <button class="btn btn-outline-secondary btn-save-main" onclick="location.reload()">
            <i class="fa fa-rotate-right"></i> 重置
        </button>
        <button class="btn btn-primary btn-save-main" onclick="saveAll()">
            <i class="fa fa-floppy-disk"></i> 保存所有设置
        </button>
    </div>

</div><!-- /.wrap -->

<script>
// 从 PHP 注入初始数据
var _banners = <?php echo json_encode($data['banners'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

function renderTable() {
    var tbody = document.getElementById('bannerTbody');
    var empty = document.getElementById('emptyTip');
    if (!_banners.length) {
        tbody.innerHTML = '';
        empty.style.display = '';
        return;
    }
    empty.style.display = 'none';
    var html = '';
    _banners.forEach(function(b, i) {
        html += '<tr data-index="' + i + '">' +
            '<td style="padding-left:16px;"><i class="fa fa-grip-vertical drag-handle"></i></td>' +
            '<td>' + (b.image ? '<img src="' + escHtml(b.image) + '" class="preview-img" onerror="this.src=\'\';">' : '<div class="preview-img-placeholder">无图</div>') + '</td>' +
            '<td><input type="url" class="form-control form-control-sm" value="' + escHtml(b.image) + '" onchange="_banners[' + i + '].image=this.value;refreshPreview(this,' + i + ');" style="font-size:12.5px;"></td>' +
            '<td><input type="url" class="form-control form-control-sm" value="' + escHtml(b.link||'') + '" onchange="_banners[' + i + '].link=this.value;" style="font-size:12.5px;" placeholder="留空不跳转"></td>' +
            '<td><select class="form-select form-select-sm" onchange="_banners[' + i + '].target=this.value;" style="font-size:12.5px;">' +
                '<option value="_self"' + (b.target==='_self'?'selected':'') + '>当前页</option>' +
                '<option value="_blank"' + (b.target==='_blank'?'selected':'') + '>新标签</option>' +
            '</select></td>' +
            '<td><input type="number" class="form-control form-control-sm" value="' + (b.sort||0) + '" onchange="_banners[' + i + '].sort=parseInt(this.value)||0;" style="width:58px;font-size:12.5px;" min="0"></td>' +
            '<td><span class="' + (b.status===0?'badge-off':'badge-on') + '" style="cursor:pointer;" onclick="toggleStatus(' + i + ')" id="statusBadge' + i + '">' + (b.status===0?'隐藏':'显示') + '</span></td>' +
            '<td><button class="btn-icon text-danger" onclick="deleteBanner(' + i + ')"><i class="fa fa-trash"></i></button></td>' +
        '</tr>';
    });
    tbody.innerHTML = html;
}

function escHtml(s) {
    return (s || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function refreshPreview(input, i) {
    var img = input.closest('tr').querySelector('.preview-img');
    if (img) img.src = input.value;
}

function addBanner() {
    var img = document.getElementById('addImage').value.trim();
    if (!img) { alert('请填写图片地址'); return; }
    _banners.push({
        image:  img,
        link:   document.getElementById('addLink').value.trim(),
        target: document.getElementById('addTarget').value,
        sort:   parseInt(document.getElementById('addSort').value) || 0,
        status: 1,
    });
    document.getElementById('addImage').value = '';
    document.getElementById('addLink').value  = '';
    document.getElementById('addSort').value  = '0';
    renderTable();
}

function deleteBanner(i) {
    if (!confirm('确定删除这张 Banner？')) return;
    _banners.splice(i, 1);
    renderTable();
}

function toggleStatus(i) {
    _banners[i].status = _banners[i].status === 0 ? 1 : 0;
    var badge = document.getElementById('statusBadge' + i);
    badge.className = _banners[i].status === 0 ? 'badge-off' : 'badge-on';
    badge.textContent = _banners[i].status === 0 ? '隐藏' : '显示';
}

function saveAll() {
    var btn = document.querySelector('.btn-primary.btn-save-main');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> 保存中...';

    var interval = parseInt(document.getElementById('inputInterval').value) || 4000;
    var height   = parseInt(document.getElementById('inputHeight').value) || 360;
    var width    = parseInt(document.getElementById('inputWidth').value) || 1680;

    var fd = new FormData();
    fd.append('banners',  JSON.stringify(_banners));
    fd.append('interval', interval);
    fd.append('height',   height);
    fd.append('width',    width);

    fetch('/plugin/ShineBanner/Api/save', { method: 'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(res){
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-floppy-disk"></i> 保存所有设置';
            if (res.code === 200) {
                showToast('success', res.msg || '保存成功');
            } else {
                showToast('danger', res.msg || '保存失败');
            }
        })
        .catch(function(){
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-floppy-disk"></i> 保存所有设置';
            showToast('danger', '请求失败，请检查网络');
        });
}

function showToast(type, msg) {
    var d = document.createElement('div');
    d.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;background:' +
        (type==='success'?'#34c759':'#ff3b30') +
        ';color:#fff;padding:12px 20px;border-radius:10px;font-size:13.5px;font-weight:600;box-shadow:0 4px 16px rgba(0,0,0,.18);transition:opacity .3s;';
    d.textContent = (type==='success'?'✓ ':'✕ ') + msg;
    document.body.appendChild(d);
    setTimeout(function(){ d.style.opacity='0'; setTimeout(function(){ d.remove(); },300); }, 2500);
}

// 初始化渲染
renderTable();
</script>

</body>
</html>
