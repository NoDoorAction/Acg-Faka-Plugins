<?php
declare(strict_types=1);

return [
    [
        'title' => '客服窗口标题',
        'name' => 'widget_title',
        'type' => 'input',
        'default' => '在线客服',
        'placeholder' => '显示在前台聊天窗口顶部',
    ],
    [
        'title' => '欢迎语',
        'name' => 'welcome_text',
        'type' => 'textarea',
        'default' => '您好，请描述您遇到的问题，我们会尽快回复。',
    ],
    [
        'title' => '离线提示',
        'name' => 'offline_text',
        'type' => 'textarea',
        'default' => '当前客服可能不在线，请留下您的问题，我们稍后回复。',
    ],
    [
        'title' => '访客发言限频（秒）',
        'name' => 'rate_limit_seconds',
        'type' => 'number',
        'default' => '2',
    ],
    [
        'title' => '同IP每小时新会话上限',
        'name' => 'session_create_limit_per_hour',
        'type' => 'number',
        'default' => '20',
    ],
    [
        'title' => '同IP每分钟发言上限',
        'name' => 'ip_message_limit_per_minute',
        'type' => 'number',
        'default' => '30',
    ],
    [
        'title' => '消息刷新间隔（秒）',
        'name' => 'poll_interval_seconds',
        'type' => 'number',
        'default' => '4',
    ],
    [
        'title' => '客服工作台',
        'name' => '_console_link',
        'type' => 'html',
        'default' => '<div style="padding:12px;background:#f5f8ff;border-left:4px solid #3b82f6;border-radius:4px;line-height:1.8;"><b>在线客服工作台</b>：<a href="/plugin/LiveChat/admin/console" target="_blank">点击打开</a></div>',
    ],
];
