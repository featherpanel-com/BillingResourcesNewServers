-- Groups Table for BillingResourcesNewServers
-- Stores groups/ranks that can be assigned permissions
CREATE TABLE
	IF NOT EXISTS `featherpanel_billingresourcesnewservers_groups` (
		`id` INT (11) NOT NULL AUTO_INCREMENT,
		`name` VARCHAR(255) NOT NULL,
		`description` TEXT NULL DEFAULT NULL,
		`color` VARCHAR(7) NULL DEFAULT '#3B82F6',
		`priority` INT (11) NOT NULL DEFAULT 0,
		`created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
		`updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (`id`),
		UNIQUE KEY `unique_name` (`name`),
		KEY `idx_priority` (`priority`)
	) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- Group Permissions Table
-- Stores permissions for groups (locations, nodes, realms, spells)
CREATE TABLE
	IF NOT EXISTS `featherpanel_billingresourcesnewservers_group_permissions` (
		`id` INT (11) NOT NULL AUTO_INCREMENT,
		`group_id` INT (11) NOT NULL,
		`resource_type` ENUM ('location', 'node', 'realm', 'spell') NOT NULL,
		`resource_id` INT (11) NOT NULL,
		`custom_error_message` TEXT NULL DEFAULT NULL,
		`created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
		`updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (`id`),
		UNIQUE KEY `unique_group_resource` (`group_id`, `resource_type`, `resource_id`),
		KEY `idx_group_id` (`group_id`),
		KEY `idx_resource` (`resource_type`, `resource_id`),
		CONSTRAINT `fk_group_permissions_group_id` FOREIGN KEY (`group_id`) REFERENCES `featherpanel_billingresourcesnewservers_groups` (`id`) ON DELETE CASCADE
	) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- User Groups Table
-- Links users to groups
CREATE TABLE
	IF NOT EXISTS `featherpanel_billingresourcesnewservers_user_groups` (
		`id` INT (11) NOT NULL AUTO_INCREMENT,
		`user_id` INT (11) NOT NULL,
		`group_id` INT (11) NOT NULL,
		`created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (`id`),
		UNIQUE KEY `unique_user_group` (`user_id`, `group_id`),
		KEY `idx_user_id` (`user_id`),
		KEY `idx_group_id` (`group_id`),
		CONSTRAINT `fk_user_groups_user_id` FOREIGN KEY (`user_id`) REFERENCES `featherpanel_users` (`id`) ON DELETE CASCADE,
		CONSTRAINT `fk_user_groups_group_id` FOREIGN KEY (`group_id`) REFERENCES `featherpanel_billingresourcesnewservers_groups` (`id`) ON DELETE CASCADE
	) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;