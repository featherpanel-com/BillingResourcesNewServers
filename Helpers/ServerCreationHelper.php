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

namespace App\Addons\billingresourcesnewservers\Helpers;

use App\Chat\Node;
use App\Chat\Realm;
use App\Chat\Spell;
use App\Chat\Location;
use App\Chat\Allocation;
use App\Addons\billingresources\Helpers\ResourcesHelper;

/**
 * Helper for validating server creation and checking resources.
 */
class ServerCreationHelper
{
    /**
     * Validate server creation request and check resources.
     *
     * @param int $userId User ID
     * @param array<string,mixed> $serverData Server creation data
     *
     * @return array{valid: bool, error?: string, error_code?: string} Validation result
     */
    public static function validateServerCreation(int $userId, array $serverData): array
    {
        // Check if user creation is enabled
        if (!SettingsHelper::isUserCreationEnabled()) {
            return [
                'valid' => false,
                'error' => 'User server creation is currently disabled',
                'error_code' => 'USER_CREATION_DISABLED',
            ];
        }

        // Check if user is allowed to create servers
        if (!SettingsHelper::isUserAllowed($userId)) {
            return [
                'valid' => false,
                'error' => 'You do not have permission to create servers',
                'error_code' => 'USER_NOT_ALLOWED',
            ];
        }

        // Validate required fields (allocation_id is optional - will be auto-selected)
        $requiredFields = ['node_id', 'realms_id', 'spell_id', 'name', 'memory', 'cpu', 'disk'];
        foreach ($requiredFields as $field) {
            if (!isset($serverData[$field])) {
                return [
                    'valid' => false,
                    'error' => "Missing required field: {$field}",
                    'error_code' => 'MISSING_FIELD',
                ];
            }
        }

        // Validate node exists and is allowed
        $nodeId = (int) $serverData['node_id'];
        $node = Node::getNodeById($nodeId);
        if (!$node) {
            return [
                'valid' => false,
                'error' => 'Node not found',
                'error_code' => 'NODE_NOT_FOUND',
            ];
        }

        // Check user-specific node permission
        $nodePermission = SettingsHelper::checkUserResourcePermission($userId, 'node', $nodeId);
        if (!$nodePermission['allowed']) {
            return [
                'valid' => false,
                'error' => $nodePermission['custom_error'] ?? 'This node is not available for you',
                'error_code' => 'NODE_NOT_ALLOWED',
            ];
        }

        // Validate location if node has one
        if (isset($node['location_id']) && $node['location_id'] > 0) {
            $locationId = (int) $node['location_id'];
            // Check user-specific location permission
            $locationPermission = SettingsHelper::checkUserResourcePermission($userId, 'location', $locationId);
            if (!$locationPermission['allowed']) {
                return [
                    'valid' => false,
                    'error' => $locationPermission['custom_error'] ?? 'This location is not available for you',
                    'error_code' => 'LOCATION_NOT_ALLOWED',
                ];
            }
        }

        // Validate realm exists and is allowed
        $realmId = (int) $serverData['realms_id'];
        $realm = Realm::getById($realmId);
        if (!$realm) {
            return [
                'valid' => false,
                'error' => 'Realm not found',
                'error_code' => 'REALM_NOT_FOUND',
            ];
        }

        // Check user-specific realm permission
        $realmPermission = SettingsHelper::checkUserResourcePermission($userId, 'realm', $realmId);
        if (!$realmPermission['allowed']) {
            return [
                'valid' => false,
                'error' => $realmPermission['custom_error'] ?? 'This realm is not available for you',
                'error_code' => 'REALM_NOT_ALLOWED',
            ];
        }

        // Validate spell exists and is allowed
        $spellId = (int) $serverData['spell_id'];
        $spell = Spell::getSpellById($spellId);
        if (!$spell) {
            return [
                'valid' => false,
                'error' => 'Spell not found',
                'error_code' => 'SPELL_NOT_FOUND',
            ];
        }

        // Check user-specific spell permission
        $spellPermission = SettingsHelper::checkUserResourcePermission($userId, 'spell', $spellId);
        if (!$spellPermission['allowed']) {
            return [
                'valid' => false,
                'error' => $spellPermission['custom_error'] ?? 'This spell is not available for you',
                'error_code' => 'SPELL_NOT_ALLOWED',
            ];
        }

        // Validate spell belongs to realm
        if (isset($spell['realm_id']) && (int) $spell['realm_id'] !== $realmId) {
            return [
                'valid' => false,
                'error' => 'Spell does not belong to the selected realm',
                'error_code' => 'SPELL_REALM_MISMATCH',
            ];
        }

        // Check if there are available allocations on this node (allocation will be auto-selected)
        $availableAllocations = Allocation::getAll(
            search: null,
            nodeId: $nodeId,
            serverId: null,
            limit: 1,
            offset: 0,
            notUsed: true
        );

        if (empty($availableAllocations)) {
            return [
                'valid' => false,
                'error' => 'No free allocations available on this node',
                'error_code' => 'NO_FREE_ALLOCATIONS',
            ];
        }

        // Validate resource values against minimum requirements
        $memory = (int) $serverData['memory'];
        $cpu = (int) $serverData['cpu'];
        $disk = (int) $serverData['disk'];

        $minMemory = SettingsHelper::getMinimumMemory();
        $minCpu = SettingsHelper::getMinimumCpu();
        $minDisk = SettingsHelper::getMinimumDisk();

        if ($memory < $minMemory) {
            return [
                'valid' => false,
                'error' => "Memory must be at least {$minMemory} MB",
                'error_code' => 'INVALID_MEMORY',
            ];
        }

        if ($cpu < $minCpu) {
            return [
                'valid' => false,
                'error' => "CPU limit must be at least {$minCpu}%",
                'error_code' => 'INVALID_CPU',
            ];
        }

        if ($disk < $minDisk) {
            return [
                'valid' => false,
                'error' => "Disk must be at least {$minDisk} MB",
                'error_code' => 'INVALID_DISK',
            ];
        }

        // Check user resources
        $availableResources = ResourcesHelper::calculateAvailableResources($userId);

        // Check server limit
        if ($availableResources['server_limit'] < 1) {
            return [
                'valid' => false,
                'error' => 'You have reached your server limit',
                'error_code' => 'SERVER_LIMIT_REACHED',
            ];
        }

        // Check memory
        if ($availableResources['memory_limit'] < $memory) {
            return [
                'valid' => false,
                'error' => 'Insufficient memory. Available: ' . $availableResources['memory_limit'] . ' MB, Required: ' . $memory . ' MB',
                'error_code' => 'INSUFFICIENT_MEMORY',
            ];
        }

        // Check CPU
        if ($availableResources['cpu_limit'] < $cpu) {
            return [
                'valid' => false,
                'error' => 'Insufficient CPU. Available: ' . $availableResources['cpu_limit'] . '%, Required: ' . $cpu . '%',
                'error_code' => 'INSUFFICIENT_CPU',
            ];
        }

        // Check disk
        if ($availableResources['disk_limit'] < $disk) {
            return [
                'valid' => false,
                'error' => 'Insufficient disk space. Available: ' . $availableResources['disk_limit'] . ' MB, Required: ' . $disk . ' MB',
                'error_code' => 'INSUFFICIENT_DISK',
            ];
        }

        // Check database limit
        $databaseLimit = isset($serverData['database_limit']) ? (int) $serverData['database_limit'] : 0;
        if ($databaseLimit > 0 && $availableResources['database_limit'] < $databaseLimit) {
            return [
                'valid' => false,
                'error' => 'Insufficient database limit. Available: ' . $availableResources['database_limit'] . ', Required: ' . $databaseLimit,
                'error_code' => 'INSUFFICIENT_DATABASE_LIMIT',
            ];
        }

        // Check backup limit
        $backupLimit = isset($serverData['backup_limit']) ? (int) $serverData['backup_limit'] : 0;
        if ($backupLimit > 0 && $availableResources['backup_limit'] < $backupLimit) {
            return [
                'valid' => false,
                'error' => 'Insufficient backup limit. Available: ' . $availableResources['backup_limit'] . ', Required: ' . $backupLimit,
                'error_code' => 'INSUFFICIENT_BACKUP_LIMIT',
            ];
        }

        // Check allocation limit
        $allocationLimit = isset($serverData['allocation_limit']) ? (int) $serverData['allocation_limit'] : 0;
        if ($allocationLimit > 0 && $availableResources['allocation_limit'] < $allocationLimit) {
            return [
                'valid' => false,
                'error' => 'Insufficient allocation limit. Available: ' . $availableResources['allocation_limit'] . ', Required: ' . $allocationLimit,
                'error_code' => 'INSUFFICIENT_ALLOCATION_LIMIT',
            ];
        }

        return ['valid' => true];
    }

    /**
     * Get filtered locations based on settings and user permissions.
     * Returns only allowed locations with permission information.
     *
     * @param array<array<string,mixed>> $allLocations All locations
     * @param int|null $userId User ID (optional, for user-specific filtering)
     *
     * @return array<array<string,mixed>> Allowed locations with permission info (allowed, error_message)
     */
    public static function filterLocations(array $allLocations, ?int $userId = null): array
    {
        $result = [];

        foreach ($allLocations as $location) {
            if (!isset($location['id'])) {
                continue;
            }

            $locationId = (int) $location['id'];
            $locationData = $location;

            if ($userId !== null) {
                $permission = SettingsHelper::checkUserResourcePermission($userId, 'location', $locationId);
                $locationData['allowed'] = $permission['allowed'];
                $locationData['error_message'] = $permission['custom_error'] ?? null;

                // Only include locations that are allowed
                if (!$permission['allowed']) {
                    continue;
                }
            } else {
                // If no user ID provided, check global restrictions only
                $allowed = SettingsHelper::getAllowedLocations();
                $isAllowed = empty($allowed) || in_array($locationId, $allowed, true);
                $locationData['allowed'] = $isAllowed;
                $locationData['error_message'] = $isAllowed ? null : 'This location is not available';

                // Only include locations that are allowed
                if (!$isAllowed) {
                    continue;
                }
            }

            $result[] = $locationData;
        }

        return $result;
    }

    /**
     * Get filtered nodes based on settings and user permissions.
     * Returns only allowed nodes with permission information.
     *
     * @param array<array<string,mixed>> $allNodes All nodes
     * @param int|null $userId User ID (optional, for user-specific filtering)
     * @param int|null $locationId Location ID to filter by (optional)
     *
     * @return array<array<string,mixed>> Allowed nodes with permission info (allowed, error_message)
     */
    public static function filterNodes(array $allNodes, ?int $userId = null, ?int $locationId = null): array
    {
        $result = [];

        foreach ($allNodes as $node) {
            if (!isset($node['id'])) {
                continue;
            }

            // Filter by location if provided
            if ($locationId !== null) {
                $nodeLocationId = isset($node['location_id']) ? (int) $node['location_id'] : 0;
                if ($nodeLocationId !== $locationId) {
                    continue;
                }
            }

            $nodeId = (int) $node['id'];
            $nodeData = $node;

            if ($userId !== null) {
                $permission = SettingsHelper::checkUserResourcePermission($userId, 'node', $nodeId);
                $nodeData['allowed'] = $permission['allowed'];
                $nodeData['error_message'] = $permission['custom_error'] ?? null;

                // Only include nodes that are allowed
                if (!$permission['allowed']) {
                    continue;
                }
            } else {
                // If no user ID provided, check global restrictions only
                $allowedNodes = SettingsHelper::getAllowedNodes();
                $allowedLocations = SettingsHelper::getAllowedLocations();
                $nodeLocationId = isset($node['location_id']) ? (int) $node['location_id'] : 0;

                $allowed = true;
                if (!empty($allowedNodes) && !in_array($nodeId, $allowedNodes, true)) {
                    $allowed = false;
                }
                if ($nodeLocationId > 0 && !empty($allowedLocations) && !in_array($nodeLocationId, $allowedLocations, true)) {
                    $allowed = false;
                }

                $nodeData['allowed'] = $allowed;
                $nodeData['error_message'] = $allowed ? null : 'This node is not available';

                // Only include nodes that are allowed
                if (!$allowed) {
                    continue;
                }
            }

            $result[] = $nodeData;
        }

        return $result;
    }

    /**
     * Get filtered realms based on settings and user permissions.
     * Returns only allowed realms with permission information.
     *
     * @param array<array<string,mixed>> $allRealms All realms
     * @param int|null $userId User ID (optional, for user-specific filtering)
     *
     * @return array<array<string,mixed>> Allowed realms with permission info (allowed, error_message)
     */
    public static function filterRealms(array $allRealms, ?int $userId = null): array
    {
        $result = [];

        foreach ($allRealms as $realm) {
            if (!isset($realm['id'])) {
                continue;
            }

            $realmId = (int) $realm['id'];
            $realmData = $realm;

            if ($userId !== null) {
                $permission = SettingsHelper::checkUserResourcePermission($userId, 'realm', $realmId);
                $realmData['allowed'] = $permission['allowed'];
                $realmData['error_message'] = $permission['custom_error'] ?? null;

                // Only include realms that are allowed
                if (!$permission['allowed']) {
                    continue;
                }
            } else {
                // If no user ID provided, check global restrictions only
                $allowed = SettingsHelper::getAllowedRealms();
                $isAllowed = empty($allowed) || in_array($realmId, $allowed, true);
                $realmData['allowed'] = $isAllowed;
                $realmData['error_message'] = $isAllowed ? null : 'This realm is not available';

                // Only include realms that are allowed
                if (!$isAllowed) {
                    continue;
                }
            }

            $result[] = $realmData;
        }

        return $result;
    }

    /**
     * Get filtered spells based on settings and user permissions.
     * Returns only allowed spells with permission information.
     *
     * @param array<array<string,mixed>> $allSpells All spells
     * @param int|null $userId User ID (optional, for user-specific filtering)
     * @param int|null $realmId Realm ID to filter by (optional)
     *
     * @return array<array<string,mixed>> Allowed spells with permission info (allowed, error_message)
     */
    public static function filterSpells(array $allSpells, ?int $userId = null, ?int $realmId = null): array
    {
        $result = [];

        foreach ($allSpells as $spell) {
            if (!isset($spell['id'])) {
                continue;
            }

            // Filter by realm if provided
            if ($realmId !== null) {
                $spellRealmId = isset($spell['realm_id']) ? (int) $spell['realm_id'] : 0;
                if ($spellRealmId !== $realmId) {
                    continue;
                }
            }

            $spellId = (int) $spell['id'];
            $spellData = $spell;

            if ($userId !== null) {
                $permission = SettingsHelper::checkUserResourcePermission($userId, 'spell', $spellId);
                $spellData['allowed'] = $permission['allowed'];
                $spellData['error_message'] = $permission['custom_error'] ?? null;

                // Only include spells that are allowed
                if (!$permission['allowed']) {
                    continue;
                }
            } else {
                // If no user ID provided, check global restrictions only
                $allowed = SettingsHelper::getAllowedSpells();
                $isAllowed = empty($allowed) || in_array($spellId, $allowed, true);
                $spellData['allowed'] = $isAllowed;
                $spellData['error_message'] = $isAllowed ? null : 'This spell is not available';

                // Only include spells that are allowed
                if (!$isAllowed) {
                    continue;
                }
            }

            $result[] = $spellData;
        }

        return $result;
    }
}
