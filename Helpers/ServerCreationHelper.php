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

use App\Addons\billingresources\Helpers\ResourcesHelper;
use App\Chat\Node;
use App\Chat\Location;
use App\Chat\Realm;
use App\Chat\Spell;
use App\Chat\Allocation;

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

        // Validate required fields
        $requiredFields = ['node_id', 'realms_id', 'spell_id', 'allocation_id', 'name', 'memory', 'cpu', 'disk'];
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

        if (!SettingsHelper::isNodeAllowed($nodeId)) {
            return [
                'valid' => false,
                'error' => 'This node is not available for user server creation',
                'error_code' => 'NODE_NOT_ALLOWED',
            ];
        }

        // Validate location if node has one
        if (isset($node['location_id']) && $node['location_id'] > 0) {
            $locationId = (int) $node['location_id'];
            if (!SettingsHelper::isLocationAllowed($locationId)) {
                return [
                    'valid' => false,
                    'error' => 'This location is not available for user server creation',
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

        if (!SettingsHelper::isRealmAllowed($realmId)) {
            return [
                'valid' => false,
                'error' => 'This realm is not available for user server creation',
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

        if (!SettingsHelper::isSpellAllowed($spellId)) {
            return [
                'valid' => false,
                'error' => 'This spell is not available for user server creation',
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

        // Validate allocation exists and belongs to node
        $allocationId = (int) $serverData['allocation_id'];
        $allocation = Allocation::getAllocationById($allocationId);
        if (!$allocation) {
            return [
                'valid' => false,
                'error' => 'Allocation not found',
                'error_code' => 'ALLOCATION_NOT_FOUND',
            ];
        }

        if ((int) $allocation['node_id'] !== $nodeId) {
            return [
                'valid' => false,
                'error' => 'Allocation does not belong to the selected node',
                'error_code' => 'ALLOCATION_NODE_MISMATCH',
            ];
        }

        // Check if allocation is already in use
        $existingServer = \App\Chat\Server::getServerByAllocationId($allocationId);
        if ($existingServer) {
            return [
                'valid' => false,
                'error' => 'Allocation is already in use',
                'error_code' => 'ALLOCATION_IN_USE',
            ];
        }

        // Validate resource values
        $memory = (int) $serverData['memory'];
        $cpu = (int) $serverData['cpu'];
        $disk = (int) $serverData['disk'];

        if ($memory < 128) {
            return [
                'valid' => false,
                'error' => 'Memory must be at least 128 MB',
                'error_code' => 'INVALID_MEMORY',
            ];
        }

        if ($cpu < 0) {
            return [
                'valid' => false,
                'error' => 'CPU limit cannot be negative',
                'error_code' => 'INVALID_CPU',
            ];
        }

        if ($disk < 128) {
            return [
                'valid' => false,
                'error' => 'Disk must be at least 128 MB',
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
     * Get filtered locations based on settings.
     *
     * @param array<array<string,mixed>> $allLocations All locations
     *
     * @return array<array<string,mixed>> Filtered locations
     */
    public static function filterLocations(array $allLocations): array
    {
        $allowed = SettingsHelper::getAllowedLocations();
        
        // Empty array means all locations are allowed
        if (empty($allowed)) {
            return $allLocations;
        }

        return array_filter($allLocations, function ($location) use ($allowed) {
            return isset($location['id']) && in_array((int) $location['id'], $allowed, true);
        });
    }

    /**
     * Get filtered nodes based on settings.
     *
     * @param array<array<string,mixed>> $allNodes All nodes
     *
     * @return array<array<string,mixed>> Filtered nodes
     */
    public static function filterNodes(array $allNodes): array
    {
        $allowedNodes = SettingsHelper::getAllowedNodes();
        $allowedLocations = SettingsHelper::getAllowedLocations();

        return array_filter($allNodes, function ($node) use ($allowedNodes, $allowedLocations) {
            $nodeId = isset($node['id']) ? (int) $node['id'] : 0;
            $locationId = isset($node['location_id']) ? (int) $node['location_id'] : 0;

            // Check node restriction
            if (!empty($allowedNodes) && !in_array($nodeId, $allowedNodes, true)) {
                return false;
            }

            // Check location restriction
            if ($locationId > 0 && !empty($allowedLocations) && !in_array($locationId, $allowedLocations, true)) {
                return false;
            }

            return true;
        });
    }

    /**
     * Get filtered realms based on settings.
     *
     * @param array<array<string,mixed>> $allRealms All realms
     *
     * @return array<array<string,mixed>> Filtered realms
     */
    public static function filterRealms(array $allRealms): array
    {
        $allowed = SettingsHelper::getAllowedRealms();
        
        // Empty array means all realms are allowed
        if (empty($allowed)) {
            return $allRealms;
        }

        return array_filter($allRealms, function ($realm) use ($allowed) {
            return isset($realm['id']) && in_array((int) $realm['id'], $allowed, true);
        });
    }

    /**
     * Get filtered spells based on settings.
     *
     * @param array<array<string,mixed>> $allSpells All spells
     *
     * @return array<array<string,mixed>> Filtered spells
     */
    public static function filterSpells(array $allSpells): array
    {
        $allowed = SettingsHelper::getAllowedSpells();
        
        // Empty array means all spells are allowed
        if (empty($allowed)) {
            return $allSpells;
        }

        return array_filter($allSpells, function ($spell) use ($allowed) {
            return isset($spell['id']) && in_array((int) $spell['id'], $allowed, true);
        });
    }
}

