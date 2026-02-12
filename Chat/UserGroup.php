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
 * UserGroup chat model for managing user-group relationships.
 */
class UserGroup
{
    private static string $table = 'featherpanel_billingresourcesnewservers_user_groups';

    /**
     * Assign a user to a group.
     *
     * @param int $userId User ID
     * @param int $groupId Group ID
     *
     * @return bool Success status
     */
    public static function assign(int $userId, int $groupId): bool
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO ' . self::$table . ' (user_id, group_id) VALUES (:user_id, :group_id)'
        );

        return $stmt->execute([
            'user_id' => $userId,
            'group_id' => $groupId,
        ]);
    }

    /**
     * Remove a user from a group.
     *
     * @param int $userId User ID
     * @param int $groupId Group ID
     *
     * @return bool Success status
     */
    public static function remove(int $userId, int $groupId): bool
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('DELETE FROM ' . self::$table . ' WHERE user_id = :user_id AND group_id = :group_id');

        return $stmt->execute([
            'user_id' => $userId,
            'group_id' => $groupId,
        ]);
    }

    /**
     * Get all groups for a user.
     *
     * @param int $userId User ID
     *
     * @return array<int> Array of group IDs
     */
    public static function getGroupIdsByUserId(int $userId): array
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT group_id FROM ' . self::$table . ' WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);

        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * Get all users in a group.
     *
     * @param int $groupId Group ID
     *
     * @return array<int> Array of user IDs
     */
    public static function getUserIdsByGroupId(int $groupId): array
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT user_id FROM ' . self::$table . ' WHERE group_id = :group_id');
        $stmt->execute(['group_id' => $groupId]);

        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * Check if a user is in a group.
     *
     * @param int $userId User ID
     * @param int $groupId Group ID
     *
     * @return bool True if user is in group
     */
    public static function isUserInGroup(int $userId, int $groupId): bool
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM ' . self::$table . ' WHERE user_id = :user_id AND group_id = :group_id');
        $stmt->execute([
            'user_id' => $userId,
            'group_id' => $groupId,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Set groups for a user (replaces all existing groups).
     *
     * @param int $userId User ID
     * @param array<int> $groupIds Array of group IDs
     *
     * @return bool Success status
     */
    public static function setGroupsForUser(int $userId, array $groupIds): bool
    {
        $pdo = Database::getPdoConnection();

        // Start transaction
        $pdo->beginTransaction();

        try {
            // Remove all existing groups
            $stmt = $pdo->prepare('DELETE FROM ' . self::$table . ' WHERE user_id = :user_id');
            $stmt->execute(['user_id' => $userId]);

            // Add new groups
            if (!empty($groupIds)) {
                $stmt = $pdo->prepare('INSERT INTO ' . self::$table . ' (user_id, group_id) VALUES (:user_id, :group_id)');
                foreach ($groupIds as $groupId) {
                    $stmt->execute([
                        'user_id' => $userId,
                        'group_id' => (int) $groupId,
                    ]);
                }
            }

            $pdo->commit();

            return true;
        } catch (\Exception $e) {
            $pdo->rollBack();

            return false;
        }
    }
}
