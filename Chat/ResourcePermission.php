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
 * ResourcePermission chat model for managing per-resource permission settings.
 */
class ResourcePermission
{
    private static string $table = 'featherpanel_billingresourcesnewservers_resource_permissions';

    /**
     * Get permission setting for a specific resource.
     *
     * @param string $resourceType Resource type (location, node, realm, spell)
     * @param int $resourceId Resource ID
     *
     * @return array<string,mixed>|null Permission setting or null if not found (defaults to 'open')
     */
    public static function getByResource(string $resourceType, int $resourceId): ?array
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE resource_type = :resource_type AND resource_id = :resource_id LIMIT 1');
        $stmt->execute([
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
        ]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get permission mode for a resource (defaults to 'open' if not set).
     *
     * @param string $resourceType Resource type
     * @param int $resourceId Resource ID
     *
     * @return string 'open' or 'restricted'
     */
    public static function getPermissionMode(string $resourceType, int $resourceId): string
    {
        $permission = self::getByResource($resourceType, $resourceId);
        if ($permission === null) {
            return 'open'; // Default to open
        }

        return $permission['permission_mode'] === 'restricted' ? 'restricted' : 'open';
    }

    /**
     * Get default error message for a resource.
     *
     * @param string $resourceType Resource type
     * @param int $resourceId Resource ID
     *
     * @return string|null Error message or null
     */
    public static function getDefaultErrorMessage(string $resourceType, int $resourceId): ?string
    {
        $permission = self::getByResource($resourceType, $resourceId);
        if ($permission === null) {
            return null;
        }

        return $permission['default_error_message'] ?: null;
    }

    /**
     * Set permission mode for a resource.
     *
     * @param string $resourceType Resource type
     * @param int $resourceId Resource ID
     * @param string $mode 'open' or 'restricted'
     * @param string|null $defaultErrorMessage Default error message (optional)
     *
     * @return bool Success status
     */
    public static function setPermissionMode(string $resourceType, int $resourceId, string $mode, ?string $defaultErrorMessage = null): bool
    {
        $pdo = Database::getPdoConnection();
        $mode = $mode === 'restricted' ? 'restricted' : 'open';

        $stmt = $pdo->prepare(
            'INSERT INTO ' . self::$table . ' (resource_type, resource_id, permission_mode, default_error_message) 
             VALUES (:resource_type, :resource_id, :permission_mode, :default_error_message)
             ON DUPLICATE KEY UPDATE permission_mode = :permission_mode, default_error_message = :default_error_message, updated_at = CURRENT_TIMESTAMP'
        );

        return $stmt->execute([
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'permission_mode' => $mode,
            'default_error_message' => $defaultErrorMessage,
        ]);
    }

    /**
     * Get all permission settings for a resource type.
     *
     * @param string $resourceType Resource type
     *
     * @return array<array<string,mixed>> Array of permission settings
     */
    public static function getByResourceType(string $resourceType): array
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE resource_type = :resource_type ORDER BY resource_id');
        $stmt->execute(['resource_type' => $resourceType]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Delete permission setting for a resource (reverts to default 'open').
     *
     * @param string $resourceType Resource type
     * @param int $resourceId Resource ID
     *
     * @return bool Success status
     */
    public static function delete(string $resourceType, int $resourceId): bool
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('DELETE FROM ' . self::$table . ' WHERE resource_type = :resource_type AND resource_id = :resource_id');

        return $stmt->execute([
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
        ]);
    }

    /**
     * Batch set permission modes for multiple resources.
     *
     * @param array<array{resource_type: string, resource_id: int, permission_mode: string, default_error_message?: string|null}> $permissions Array of permission settings
     *
     * @return bool Success status
     */
    public static function batchSet(array $permissions): bool
    {
        $pdo = Database::getPdoConnection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO ' . self::$table . ' (resource_type, resource_id, permission_mode, default_error_message) 
                 VALUES (:resource_type, :resource_id, :permission_mode, :default_error_message)
                 ON DUPLICATE KEY UPDATE permission_mode = :permission_mode, default_error_message = :default_error_message, updated_at = CURRENT_TIMESTAMP'
            );

            foreach ($permissions as $perm) {
                $mode = ($perm['permission_mode'] ?? 'open') === 'restricted' ? 'restricted' : 'open';
                $stmt->execute([
                    'resource_type' => $perm['resource_type'],
                    'resource_id' => (int) $perm['resource_id'],
                    'permission_mode' => $mode,
                    'default_error_message' => $perm['default_error_message'] ?? null,
                ]);
            }

            $pdo->commit();

            return true;
        } catch (\Exception $e) {
            $pdo->rollBack();

            return false;
        }
    }
}

