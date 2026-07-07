<?php

/**
 * 菜单配置
 */


return [

    'admin' => [


        'code' => [
            'name' => '界面',
            'icon' => 'fa fa-html5',
            'left' => [
                'code-html' => [
                    'name' => '模板管理',
                    'icon' => 'fa fa-home',
                    'link' => [
                        [
                            'name' => '电脑模板',
                            'icon' => 'fa fa-desktop',
                            'uri' => 'tpl/tpl_pc/index',
                        ],
                        [
                            'name' => '手机模板',
                            'icon' => 'fa fa-mobile',
                            'uri' => 'tpl/tpl_mobile/index',
                        ],
                        [
                            'name' => '终端模板',
                            'icon' => 'fa fa-cogs',
                            'uri' => 'tpl/tpl_client/index',
                        ],
                    ]
                ],
                'code-css' => [
                    'name' => '风格管理',
                    'icon' => 'fa fa-css3',
                    'link' => [
                        [
                            'name' => '系统文件',
                            'icon' => 'fa fa-chrome',
                            'uri' => 'tpl/system_theme/index',
                        ],
                        [
                            'name' => '项目风格',
                            'icon' => 'fa fa-photo',
                            'uri' => 'tpl/theme/index',
                        ],
                    ],
                    'displayorder' => 99
                ],
            ],
        ],


    ],



];