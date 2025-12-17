-- User Permissions Table for BillingResourcesNewServers
-- Stores per-user permissions for locations, nodes, realms, and spells
CREATE TABLE
	IF NOT EXISTS `featherpanel_billingresourcesnewservers_user_permissions` (
		`id` INT (11) NOT NULL AUTO_INCREMENT,
		`user_id` INT (11) NOT NULL,
		`resource_type` ENUM ('location', 'node', 'realm', 'spell') NOT NULL,
		`resource_id` INT (11) NOT NULL,
		`custom_error_message` TEXT NULL DEFAULT NULL,
		`created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
		`updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (`id`),
		UNIQUE KEY `unique_user_resource` (`user_id`, `resource_type`, `resource_id`),
		KEY `idx_user_id` (`user_id`),
		KEY `idx_resource` (`resource_type`, `resource_id`),
		CONSTRAINT `user_permissions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `featherpanel_users` (`id`) ON DELETE CASCADE
	) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;