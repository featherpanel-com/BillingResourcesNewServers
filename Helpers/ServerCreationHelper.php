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

namespace App\Addons\billingresourcesnewservers\Helpers;

use App\Chat\Node;
use App\Chat\Realm;
use App\Chat\Spell;
use App\Chat\Server;
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

        $memory = (int) $serverData['memory'];
        $disk = (int) $serverData['disk'];

        if (SettingsHelper::isNodeAtServerCap($nodeId)) {
            return [
                'valid' => false,
                'error' => SettingsHelper::getNodeAtCapacityErrorMessage($nodeId),
                'error_code' => 'NODE_SERVER_CAP_REACHED',
            ];
        }

        $nodeCapacity = self::evaluateNodeResourceCapacity($nodeId, $memory, $disk);
        if (!$nodeCapacity['eligible']) {
            return [
                'valid' => false,
                'error' => $nodeCapacity['error'] ?? 'This node does not have enough capacity for this server',
                'error_code' => $nodeCapacity['error_code'] ?? 'NODE_INSUFFICIENT_CAPACITY',
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
        $cpu = (int) $serverData['cpu'];

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

            $nodeData = self::annotateNodeCapacity($nodeData);

            $result[] = $nodeData;
        }

        return $result;
    }

    /**
     * Add server-count / cap metadata and mark full nodes as unavailable.
     *
     * @param array<string, mixed> $nodeData
     *
     * @return array<string, mixed>
     */
    public static function annotateNodeCapacity(array $nodeData): array
    {
        if (!isset($nodeData['id'])) {
            return $nodeData;
        }

        $nodeId = (int) $nodeData['id'];
        $serverCount = SettingsHelper::getNodeServerCount($nodeId);
        $maxServers = SettingsHelper::getMaxServersForNode($nodeId);
        $atCapacity = $maxServers > 0 && $serverCount >= $maxServers;

        $nodeData['server_count'] = $serverCount;
        $nodeData['max_servers_per_node'] = $maxServers;
        $nodeData['at_capacity'] = $atCapacity;

        if ($atCapacity && ($nodeData['allowed'] ?? true) !== false) {
            $nodeData['allowed'] = false;
            $nodeData['error_message'] = SettingsHelper::getNodeAtCapacityErrorMessage($nodeId);
        }

        return $nodeData;
    }

    /**
     * @return array{eligible: bool, error?: string, error_code?: string}
     */
    public static function evaluateNodeResourceCapacity(int $nodeId, int $requiredMemory, int $requiredDisk): array
    {
        if (Allocation::getFreeCountByNodeId($nodeId) < 1) {
            return [
                'eligible' => false,
                'error' => 'No free allocations available on this node',
                'error_code' => 'NO_FREE_ALLOCATIONS',
            ];
        }

        $fullNode = Node::getNodeById($nodeId);
        if (!$fullNode) {
            return [
                'eligible' => false,
                'error' => 'Node not found',
                'error_code' => 'NODE_NOT_FOUND',
            ];
        }

        $nodeMemory = (int) ($fullNode['memory'] ?? 0);
        $nodeDisk = (int) ($fullNode['disk'] ?? 0);
        $memOverallocation = (int) ($fullNode['memory_overallocate'] ?? 0);
        $diskOverallocation = (int) ($fullNode['disk_overallocate'] ?? 0);

        $memoryCap = $nodeMemory > 0
            ? (int) floor($nodeMemory * (1 + ($memOverallocation / 100)))
            : 0;
        $diskCap = $nodeDisk > 0
            ? (int) floor($nodeDisk * (1 + ($diskOverallocation / 100)))
            : 0;

        $allocated = self::getNodeAllocatedResources($nodeId);

        if ($requiredMemory > 0 && $memoryCap > 0 && ($allocated['memory'] + $requiredMemory) > $memoryCap) {
            return [
                'eligible' => false,
                'error' => 'This node does not have enough memory capacity for this server',
                'error_code' => 'NODE_INSUFFICIENT_MEMORY',
            ];
        }

        if ($requiredDisk > 0 && $diskCap > 0 && ($allocated['disk'] + $requiredDisk) > $diskCap) {
            return [
                'eligible' => false,
                'error' => 'This node does not have enough disk capacity for this server',
                'error_code' => 'NODE_INSUFFICIENT_DISK',
            ];
        }

        return ['eligible' => true];
    }

    /**
     * Lower score = better placement candidate (spread servers + balance resources).
     */
    public static function scoreNodeForPlacement(int $nodeId, int $requiredMemory, int $requiredDisk): ?float
    {
        if (SettingsHelper::isNodeAtServerCap($nodeId)) {
            return null;
        }

        $capacity = self::evaluateNodeResourceCapacity($nodeId, $requiredMemory, $requiredDisk);
        if (!$capacity['eligible']) {
            return null;
        }

        $fullNode = Node::getNodeById($nodeId);
        if (!$fullNode) {
            return null;
        }

        $serverCount = SettingsHelper::getNodeServerCount($nodeId);
        $maxServers = SettingsHelper::getMaxServersForNode($nodeId);

        if ($maxServers > 0) {
            $serverPressure = $serverCount / $maxServers;
        } else {
            $serverPressure = $serverCount / 100.0;
        }

        $nodeMemory = (int) ($fullNode['memory'] ?? 0);
        $nodeDisk = (int) ($fullNode['disk'] ?? 0);
        $memOverallocation = (int) ($fullNode['memory_overallocate'] ?? 0);
        $diskOverallocation = (int) ($fullNode['disk_overallocate'] ?? 0);

        $memoryCap = $nodeMemory > 0
            ? (int) floor($nodeMemory * (1 + ($memOverallocation / 100)))
            : 0;
        $diskCap = $nodeDisk > 0
            ? (int) floor($nodeDisk * (1 + ($diskOverallocation / 100)))
            : 0;

        $allocated = self::getNodeAllocatedResources($nodeId);
        $memoryUsage = $memoryCap > 0 ? $allocated['memory'] / $memoryCap : 0.0;
        $diskUsage = $diskCap > 0 ? $allocated['disk'] / $diskCap : 0.0;
        $freeAllocations = Allocation::getFreeCountByNodeId($nodeId);

        return ($serverPressure * 10000)
            + ($memoryUsage * 1000)
            + ($diskUsage * 100)
            - min($freeAllocations, 100) * 0.1;
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

    /**
     * Resolve user-mode placement defaults (including auto strategies) for the create form.
     *
     * @param array<string, array{mode: string, value?: int|string|null, default?: int|string|null}> $policies
     *
     * @return array<string, int>
     */
    public static function resolvePlacementDefaultsForForm(
        int $userId,
        array $policies,
        int $requiredMemory = 0,
        int $requiredDisk = 0,
    ): array {
        $out = [];
        $context = [];

        foreach (SettingsHelper::PLACEMENT_FIELD_KEYS as $key) {
            $p = $policies[$key] ?? ['mode' => 'user'];
            $mode = $p['mode'] ?? 'user';
            $def = null;
            if ($mode === 'user' && isset($p['default'])) {
                $def = $p['default'];
            } elseif (($mode === 'fixed' || $mode === 'hidden') && isset($p['value'])) {
                $def = $p['value'];
            }
            if ($def === null || $def === '') {
                continue;
            }

            $resolved = self::resolvePlacementSelection(
                $userId,
                $key,
                $def,
                $context,
                $requiredMemory,
                $requiredDisk
            );
            if ($resolved === null) {
                continue;
            }

            $out[$key] = $resolved;
            if ($key === 'location') {
                $context['location_id'] = $resolved;
            } elseif ($key === 'node') {
                $context['node_id'] = $resolved;
            } elseif ($key === 'realm') {
                $context['realms_id'] = $resolved;
            }
        }

        return $out;
    }

    /**
     * Apply fixed/hidden placement policies to the create-server payload.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function applyPlacementFieldPoliciesToPayload(int $userId, array $data): array
    {
        $policies = SettingsHelper::getPlacementFieldPolicies();
        $memory = isset($data['memory']) ? (int) $data['memory'] : 0;
        $disk = isset($data['disk']) ? (int) $data['disk'] : 0;

        foreach (SettingsHelper::PLACEMENT_FIELD_KEYS as $key) {
            $p = $policies[$key] ?? ['mode' => 'user'];
            $mode = $p['mode'] ?? 'user';
            if ($mode !== 'fixed' && $mode !== 'hidden') {
                continue;
            }
            if (!array_key_exists('value', $p) || $p['value'] === null || $p['value'] === '') {
                continue;
            }

            $resolved = self::resolvePlacementSelection(
                $userId,
                $key,
                $p['value'],
                $data,
                $memory,
                $disk
            );
            if ($resolved === null) {
                continue;
            }

            if ($key === 'location') {
                $data['location_id'] = $resolved;
            } elseif ($key === 'node') {
                $data['node_id'] = $resolved;
            } elseif ($key === 'realm') {
                $data['realms_id'] = $resolved;
            } elseif ($key === 'spell') {
                $data['spell_id'] = $resolved;
            }
        }

        return $data;
    }

    /**
     * Resolve a placement policy value (numeric ID or auto strategy) for the current user.
     *
     * @param int|string $valueOrStrategy Resource ID or strategy (first, least_capacity)
     * @param array<string, mixed> $context Current form payload (location_id, realms_id, etc.)
     */
    public static function resolvePlacementSelection(
        int $userId,
        string $field,
        int | string $valueOrStrategy,
        array $context = [],
        int $requiredMemory = 0,
        int $requiredDisk = 0,
    ): ?int {
        if (is_int($valueOrStrategy) || (is_string($valueOrStrategy) && is_numeric($valueOrStrategy))) {
            return (int) $valueOrStrategy;
        }

        if ($valueOrStrategy === 'first') {
            return self::resolveFirstPlacement($userId, $field, $context);
        }

        if ($valueOrStrategy === 'least_capacity' && $field === 'node') {
            $locationId = isset($context['location_id']) ? (int) $context['location_id'] : null;
            if ($locationId !== null && $locationId <= 0) {
                $locationId = null;
            }

            return self::resolveLeastCapacityNode($userId, $locationId, $requiredMemory, $requiredDisk);
        }

        return null;
    }

    /**
     * Pick the allowed node with the most remaining capacity (lowest memory utilization, then most free allocations).
     */
    public static function resolveLeastCapacityNode(
        int $userId,
        ?int $locationId = null,
        int $requiredMemory = 0,
        int $requiredDisk = 0,
    ): ?int {
        $all = Node::getAllNodes();
        $candidates = self::filterNodes($all, $userId, $locationId);
        if ($candidates === []) {
            return null;
        }

        $bestNodeId = null;
        $bestScore = null;

        foreach ($candidates as $node) {
            if (!isset($node['id'])) {
                continue;
            }
            $nodeId = (int) $node['id'];
            $score = self::scoreNodeForPlacement($nodeId, $requiredMemory, $requiredDisk);
            if ($score === null) {
                continue;
            }

            if ($bestScore === null || $score < $bestScore) {
                $bestScore = $score;
                $bestNodeId = $nodeId;
            }
        }

        return $bestNodeId;
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function resolveFirstPlacement(int $userId, string $field, array $context): ?int
    {
        if ($field === 'location') {
            $all = Location::getAll(null, 1000, 0);
            $filtered = self::filterLocations($all, $userId);

            return isset($filtered[0]['id']) ? (int) $filtered[0]['id'] : null;
        }

        if ($field === 'node') {
            $locationId = isset($context['location_id']) ? (int) $context['location_id'] : null;
            if ($locationId !== null && $locationId <= 0) {
                $locationId = null;
            }
            $all = Node::getAllNodes();
            $filtered = self::filterNodes($all, $userId, $locationId);

            foreach ($filtered as $node) {
                if (!isset($node['id'])) {
                    continue;
                }
                $nodeId = (int) $node['id'];
                if (SettingsHelper::isNodeAtServerCap($nodeId)) {
                    continue;
                }
                if (Allocation::getFreeCountByNodeId($nodeId) < 1) {
                    continue;
                }

                return $nodeId;
            }

            return null;
        }

        if ($field === 'realm') {
            $all = Realm::getAll(null, 1000, 0);
            $filtered = self::filterRealms($all, $userId);

            return isset($filtered[0]['id']) ? (int) $filtered[0]['id'] : null;
        }

        if ($field === 'spell') {
            $realmId = isset($context['realms_id']) ? (int) $context['realms_id'] : null;
            if ($realmId !== null && $realmId <= 0) {
                $realmId = null;
            }
            $all = Spell::getAllSpells();
            $filtered = self::filterSpells($all, $userId, $realmId);

            return isset($filtered[0]['id']) ? (int) $filtered[0]['id'] : null;
        }

        return null;
    }

    /**
     * @return array{memory: int, disk: int}
     */
    private static function getNodeAllocatedResources(int $nodeId): array
    {
        $servers = Server::getServersByNodeId($nodeId);
        $memory = 0;
        $disk = 0;
        foreach ($servers as $server) {
            $memory += (int) ($server['memory'] ?? 0);
            $disk += (int) ($server['disk'] ?? 0);
        }

        return ['memory' => $memory, 'disk' => $disk];
    }
}
