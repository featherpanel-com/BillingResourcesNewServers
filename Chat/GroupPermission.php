<?php

/*
 * This file is part of FeatherPanel.
 *
 * MIT License
 *
 * Copyright (c) 2025 MythicalSystems
 * Copyright (c) 2025 Cassian Gherman (NaysKutzu)
 * Copyright (c) 2018 - 2021 Dane Everitt <dane@daneeveritt.com> and Contributors
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace App\Addons\billingresourcesnewservers\Chat;

use App\Chat\Database;

/**
 * GroupPermission chat model for managing group permissions.
 */
class GroupPermission
{
    private static string $table = 'featherpanel_billingresourcesnewservers_group_permissions';

    /**
     * Get all permissions for a group.
     *
     * @param int $groupId Group ID
     *
     * @return array<array<string,mixed>> Array of permission records
     */
    public static function getByGroupId(int $groupId): array
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE group_id = :group_id ORDER BY resource_type, resource_id');
        $stmt->execute(['group_id' => $groupId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get permissions for a group filtered by resource type.
     *
     * @param int $groupId Group ID
     * @param string $resourceType Resource type (location, node, realm, spell)
     *
     * @return array<array<string,mixed>> Array of permission records
     */
    public static function getByGroupIdAndType(int $groupId, string $resourceType): array
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE group_id = :group_id AND resource_type = :resource_type ORDER BY resource_id');
        $stmt->execute([
            'group_id' => $groupId,
            'resource_type' => $resourceType,
        ]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Check if group has permission for a specific resource.
     *
     * @param int $groupId Group ID
     * @param string $resourceType Resource type (location, node, realm, spell)
     * @param int $resourceId Resource ID
     *
     * @return array<string,mixed>|null Permission record with custom error message, or null if not found
     */
    public static function getGroupPermission(int $groupId, string $resourceType, int $resourceId): ?array
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE group_id = :group_id AND resource_type = :resource_type AND resource_id = :resource_id LIMIT 1');
        $stmt->execute([
            'group_id' => $groupId,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
        ]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get allowed resource IDs for a group and resource type.
     *
     * @param int $groupId Group ID
     * @param string $resourceType Resource type (location, node, realm, spell)
     *
     * @return array<int> Array of allowed resource IDs
     */
    public static function getAllowedResourceIds(int $groupId, string $resourceType): array
    {
        $permissions = self::getByGroupIdAndType($groupId, $resourceType);

        return array_map(function ($perm) {
            return (int) $perm['resource_id'];
        }, $permissions);
    }

    /**
     * Create or update a group permission.
     *
     * @param int $groupId Group ID
     * @param string $resourceType Resource type (location, node, realm, spell)
     * @param int $resourceId Resource ID
     * @param string|null $customErrorMessage Custom error message (optional)
     *
     * @return bool Success status
     */
    public static function createOrUpdate(int $groupId, string $resourceType, int $resourceId, ?string $customErrorMessage = null): bool
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO ' . self::$table . ' (group_id, resource_type, resource_id, custom_error_message) 
             VALUES (:group_id, :resource_type, :resource_id, :custom_error_message)
             ON DUPLICATE KEY UPDATE custom_error_message = :custom_error_message, updated_at = CURRENT_TIMESTAMP'
        );

        return $stmt->execute([
            'group_id' => $groupId,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'custom_error_message' => $customErrorMessage,
        ]);
    }

    /**
     * Delete a group permission.
     *
     * @param int $groupId Group ID
     * @param string $resourceType Resource type (location, node, realm, spell)
     * @param int $resourceId Resource ID
     *
     * @return bool Success status
     */
    public static function delete(int $groupId, string $resourceType, int $resourceId): bool
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('DELETE FROM ' . self::$table . ' WHERE group_id = :group_id AND resource_type = :resource_type AND resource_id = :resource_id');

        return $stmt->execute([
            'group_id' => $groupId,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
        ]);
    }

    /**
     * Get all permissions for a user (through their groups).
     *
     * @param int $userId User ID
     *
     * @return array<array<string,mixed>> Array of permission records grouped by resource type
     */
    public static function getByUserId(int $userId): array
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare(
            'SELECT gp.* FROM ' . self::$table . ' gp
             INNER JOIN featherpanel_billingresourcesnewservers_user_groups ug ON gp.group_id = ug.group_id
             WHERE ug.user_id = :user_id
             ORDER BY gp.resource_type, gp.resource_id'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get allowed resource IDs for a user (through their groups) and resource type.
     *
     * @param int $userId User ID
     * @param string $resourceType Resource type (location, node, realm, spell)
     *
     * @return array<int> Array of allowed resource IDs
     */
    public static function getAllowedResourceIdsForUser(int $userId, string $resourceType): array
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare(
            'SELECT DISTINCT gp.resource_id FROM ' . self::$table . ' gp
             INNER JOIN featherpanel_billingresourcesnewservers_user_groups ug ON gp.group_id = ug.group_id
             WHERE ug.user_id = :user_id AND gp.resource_type = :resource_type'
        );
        $stmt->execute([
            'user_id' => $userId,
            'resource_type' => $resourceType,
        ]);

        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }
}

