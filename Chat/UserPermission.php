<?php

/*
 * This file is part of FeatherPanel.
 *
 * Copyright (C) 2025 MythicalSystems Studios
 * Copyright (C) 2025 FeatherPanel Contributors
 * Copyright (C) 2025 Cassian Gherman (aka NaysKutzu)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See the LICENSE file or <https://www.gnu.org/licenses/>.
 */

namespace App\Addons\billingresourcesnewservers\Chat;

use App\Chat\Database;

/**
 * UserPermission chat model for managing user-specific permissions.
 */
class UserPermission
{
    private static string $table = 'featherpanel_billingresourcesnewservers_user_permissions';

    /**
     * Get all permissions for a user.
     *
     * @param int $userId User ID
     *
     * @return array<array<string,mixed>> Array of permission records
     */
    public static function getByUserId(int $userId): array
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE user_id = :user_id ORDER BY resource_type, resource_id');
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get permissions for a user filtered by resource type.
     *
     * @param int $userId User ID
     * @param string $resourceType Resource type (location, node, realm, spell)
     *
     * @return array<array<string,mixed>> Array of permission records
     */
    public static function getByUserIdAndType(int $userId, string $resourceType): array
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE user_id = :user_id AND resource_type = :resource_type ORDER BY resource_id');
        $stmt->execute([
            'user_id' => $userId,
            'resource_type' => $resourceType,
        ]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Check if user has permission for a specific resource.
     *
     * @param int $userId User ID
     * @param string $resourceType Resource type (location, node, realm, spell)
     * @param int $resourceId Resource ID
     *
     * @return array<string,mixed>|null Permission record with custom error message, or null if not found
     */
    public static function getUserPermission(int $userId, string $resourceType, int $resourceId): ?array
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE user_id = :user_id AND resource_type = :resource_type AND resource_id = :resource_id LIMIT 1');
        $stmt->execute([
            'user_id' => $userId,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
        ]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get allowed resource IDs for a user and resource type.
     *
     * @param int $userId User ID
     * @param string $resourceType Resource type (location, node, realm, spell)
     *
     * @return array<int> Array of allowed resource IDs
     */
    public static function getAllowedResourceIds(int $userId, string $resourceType): array
    {
        $permissions = self::getByUserIdAndType($userId, $resourceType);

        return array_map(function ($perm) {
            return (int) $perm['resource_id'];
        }, $permissions);
    }

    /**
     * Create or update a user permission.
     *
     * @param int $userId User ID
     * @param string $resourceType Resource type (location, node, realm, spell)
     * @param int $resourceId Resource ID
     * @param string|null $customErrorMessage Custom error message (optional)
     *
     * @return bool Success status
     */
    public static function createOrUpdate(int $userId, string $resourceType, int $resourceId, ?string $customErrorMessage = null): bool
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO ' . self::$table . ' (user_id, resource_type, resource_id, custom_error_message) 
             VALUES (:user_id, :resource_type, :resource_id, :custom_error_message)
             ON DUPLICATE KEY UPDATE custom_error_message = :custom_error_message, updated_at = CURRENT_TIMESTAMP'
        );

        return $stmt->execute([
            'user_id' => $userId,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'custom_error_message' => $customErrorMessage,
        ]);
    }

    /**
     * Delete a user permission.
     *
     * @param int $userId User ID
     * @param string $resourceType Resource type (location, node, realm, spell)
     * @param int $resourceId Resource ID
     *
     * @return bool Success status
     */
    public static function delete(int $userId, string $resourceType, int $resourceId): bool
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('DELETE FROM ' . self::$table . ' WHERE user_id = :user_id AND resource_type = :resource_type AND resource_id = :resource_id');

        return $stmt->execute([
            'user_id' => $userId,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
        ]);
    }

    /**
     * Delete all permissions for a user.
     *
     * @param int $userId User ID
     *
     * @return bool Success status
     */
    public static function deleteByUserId(int $userId): bool
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('DELETE FROM ' . self::$table . ' WHERE user_id = :user_id');

        return $stmt->execute(['user_id' => $userId]);
    }

    /**
     * Delete all permissions for a resource.
     *
     * @param string $resourceType Resource type (location, node, realm, spell)
     * @param int $resourceId Resource ID
     *
     * @return bool Success status
     */
    public static function deleteByResource(string $resourceType, int $resourceId): bool
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('DELETE FROM ' . self::$table . ' WHERE resource_type = :resource_type AND resource_id = :resource_id');

        return $stmt->execute([
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
        ]);
    }

    /**
     * Get all users who have permission for a specific resource.
     *
     * @param string $resourceType Resource type (location, node, realm, spell)
     * @param int $resourceId Resource ID
     *
     * @return array<array<string,mixed>> Array of permission records
     */
    public static function getByResource(string $resourceType, int $resourceId): array
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE resource_type = :resource_type AND resource_id = :resource_id ORDER BY user_id');
        $stmt->execute([
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
        ]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
}
