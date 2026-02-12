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
