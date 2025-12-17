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
 * Group chat model for managing groups/ranks.
 */
class Group
{
    private static string $table = 'featherpanel_billingresourcesnewservers_groups';

    /**
     * Get all groups.
     *
     * @return array<array<string,mixed>> Array of group records
     */
    public static function getAll(): array
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->query('SELECT * FROM ' . self::$table . ' ORDER BY priority DESC, name ASC');

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get a group by ID.
     *
     * @param int $groupId Group ID
     *
     * @return array<string,mixed>|null Group record or null if not found
     */
    public static function getById(int $groupId): ?array
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $groupId]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Create a new group.
     *
     * @param string $name Group name
     * @param string|null $description Group description
     * @param string|null $color Group color (hex)
     * @param int $priority Priority (higher = more important)
     *
     * @return int|false Group ID or false on failure
     */
    public static function create(string $name, ?string $description = null, ?string $color = null, int $priority = 0): int | false
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO ' . self::$table . ' (name, description, color, priority) VALUES (:name, :description, :color, :priority)'
        );

        if ($stmt->execute([
            'name' => $name,
            'description' => $description,
            'color' => $color ?? '#3B82F6',
            'priority' => $priority,
        ])) {
            return (int) $pdo->lastInsertId();
        }

        return false;
    }

    /**
     * Update a group.
     *
     * @param int $groupId Group ID
     * @param array<string,mixed> $data Update data (name, description, color, priority)
     *
     * @return bool Success status
     */
    public static function update(int $groupId, array $data): bool
    {
        $pdo = Database::getPdoConnection();
        $fields = [];
        $params = ['id' => $groupId];

        if (isset($data['name'])) {
            $fields[] = 'name = :name';
            $params['name'] = $data['name'];
        }
        if (isset($data['description'])) {
            $fields[] = 'description = :description';
            $params['description'] = $data['description'];
        }
        if (isset($data['color'])) {
            $fields[] = 'color = :color';
            $params['color'] = $data['color'];
        }
        if (isset($data['priority'])) {
            $fields[] = 'priority = :priority';
            $params['priority'] = (int) $data['priority'];
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = 'updated_at = CURRENT_TIMESTAMP';
        $stmt = $pdo->prepare('UPDATE ' . self::$table . ' SET ' . implode(', ', $fields) . ' WHERE id = :id');

        return $stmt->execute($params);
    }

    /**
     * Delete a group.
     *
     * @param int $groupId Group ID
     *
     * @return bool Success status
     */
    public static function delete(int $groupId): bool
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('DELETE FROM ' . self::$table . ' WHERE id = :id');

        return $stmt->execute(['id' => $groupId]);
    }

    /**
     * Get groups for a user.
     *
     * @param int $userId User ID
     *
     * @return array<array<string,mixed>> Array of group records
     */
    public static function getByUserId(int $userId): array
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare(
            'SELECT g.* FROM ' . self::$table . ' g
             INNER JOIN featherpanel_billingresourcesnewservers_user_groups ug ON g.id = ug.group_id
             WHERE ug.user_id = :user_id
             ORDER BY g.priority DESC, g.name ASC'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
}
