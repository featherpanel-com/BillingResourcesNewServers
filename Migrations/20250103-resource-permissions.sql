-- Resource Permission Settings Table
-- Stores per-resource permission settings (open vs restricted)
CREATE TABLE
	IF NOT EXISTS `featherpanel_billingresourcesnewservers_resource_permissions` (
		`id` INT (11) NOT NULL AUTO_INCREMENT,
		`resource_type` ENUM ('location', 'node', 'realm', 'spell') NOT NULL,
		`resource_id` INT (11) NOT NULL,
		`permission_mode` ENUM ('open', 'restricted') NOT NULL DEFAULT 'open',
		`default_error_message` TEXT NULL DEFAULT NULL,
		`created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
		`updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (`id`),
		UNIQUE KEY `unique_resource` (`resource_type`, `resource_id`),
		KEY `idx_resource_type` (`resource_type`),
		KEY `idx_resource_id` (`resource_id`)
	) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;