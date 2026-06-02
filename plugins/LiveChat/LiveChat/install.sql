-- LiveChat plugin tables

CREATE TABLE IF NOT EXISTS `__PREFIX__plugin_livechat_session` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `visitor_token`  VARCHAR(64) NOT NULL,
    `visitor_name`   VARCHAR(64) DEFAULT NULL,
    `status`         TINYINT(1) NOT NULL DEFAULT 0,
    `last_message`   VARCHAR(255) DEFAULT NULL,
    `last_sender`    VARCHAR(16) DEFAULT NULL,
    `last_msg_at`    DATETIME NULL DEFAULT NULL,
    `closed_by`      VARCHAR(16) DEFAULT NULL,
    `closed_at`      DATETIME NULL DEFAULT NULL,
    `client_ip`      VARCHAR(64) DEFAULT NULL,
    `client_fingerprint` VARCHAR(64) NOT NULL DEFAULT '',
    `user_agent`     VARCHAR(255) DEFAULT NULL,
    `create_time`    DATETIME NULL DEFAULT NULL,
    `update_time`    DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_visitor_token` (`visitor_token`),
    KEY `idx_status_last` (`status`, `last_msg_at`),
    KEY `idx_client_ip_create` (`client_ip`, `create_time`),
    KEY `idx_fingerprint_time` (`client_fingerprint`, `create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `__PREFIX__plugin_livechat_message` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id`  BIGINT UNSIGNED NOT NULL,
    `sender`      VARCHAR(16) NOT NULL,
    `content`     TEXT NOT NULL,
    `is_read`     TINYINT(1) NOT NULL DEFAULT 0,
    `create_time` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_session_id` (`session_id`, `id`),
    KEY `idx_session_sender_create` (`session_id`, `sender`, `create_time`),
    KEY `idx_is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
