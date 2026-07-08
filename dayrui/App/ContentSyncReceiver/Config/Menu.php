<?php

/**
 * 菜单配置
 */
return [

    'admin' => [

        'app' => [
            'left' => [
                'app-plugin' => [
                    'link' => [
                        'app-contentsyncreceiver' => [
                            'name' => '新闻同步接收',
                            'icon' => 'fa fa-rss',
                            'uri' => 'contentsyncreceiver/api/config',
                        ],
                    ],
                ],
            ],
        ],

    ],

];
