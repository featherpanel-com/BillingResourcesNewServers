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

