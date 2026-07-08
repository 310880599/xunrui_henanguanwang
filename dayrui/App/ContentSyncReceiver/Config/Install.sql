DROP TABLE IF EXISTS `{dbprefix}content_sync_receive_log`;
CREATE TABLE IF NOT EXISTS `{dbprefix}content_sync_receive_log` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `source_site` varchar(50) NOT NULL COMMENT '来源站点标识',
    `source_content_id` varchar(100) NOT NULL COMMENT '来源内容ID',
    `local_content_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '本地内容ID',
    `title` varchar(255) NOT NULL DEFAULT '' COMMENT '标题',
    `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态:1成功,0处理中,-1失败',
    `error_message` varchar(500) NOT NULL DEFAULT '' COMMENT '错误信息',
    `create_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '接收时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_source_content` (`source_site`, `source_content_id`),
    KEY `idx_local_content_id` (`local_content_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT='新闻同步接收日志';
