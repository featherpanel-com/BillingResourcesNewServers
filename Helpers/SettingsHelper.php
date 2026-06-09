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

use App\Chat\Server;
use App\Plugins\PluginSettings;

/**
 * Helper for managing plugin settings using PluginSettings.
 */
class SettingsHelper
{
    /** @var list<string> */
    public const RESOURCE_FIELD_KEYS = [
        'memory',
        'cpu',
        'disk',
        'swap',
        'io',
        'database_limit',
        'allocation_limit',
        'backup_limit',
    ];

    /** @var list<string> */
    public const PLACEMENT_FIELD_KEYS = [
        'location',
        'node',
        'realm',
        'spell',
    ];

    /** @var list<string> */
    public const PLACEMENT_AUTO_STRATEGIES = [
        'first',
        'least_capacity',
    ];

    /**
     * Default policies: users can edit all fields (panel chooses form defaults).
     *
     * @return array<string, array{mode: string, value?: int|null, default?: int|null}>
     */
    public static function getDefaultResourceFieldPolicies(): array
    {
        $out = [];
        foreach (self::RESOURCE_FIELD_KEYS as $key) {
            $out[$key] = ['mode' => 'user'];
        }

        return $out;
    }

    /**
     * Parsed resource field policies merged with defaults.
     *
     * @return array<string, array{mode: string, value?: int|null, default?: int|null}>
     */
    public static function getResourceFieldPolicies(): array
    {
        $raw = PluginSettings::getSetting('billingresourcesnewservers', 'resource_field_policies');
        $defaults = self::getDefaultResourceFieldPolicies();
        if ($raw === null || $raw === '') {
            return $defaults;
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            $decoded = json_decode(
                html_entity_decode((string) $raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                true
            );
        }
        if (!is_array($decoded)) {
            return $defaults;
        }

        foreach (self::RESOURCE_FIELD_KEYS as $key) {
            if (!isset($decoded[$key]) || !is_array($decoded[$key])) {
                continue;
            }
            $entry = $decoded[$key];
            $mode = $entry['mode'] ?? 'user';
            if (!in_array($mode, ['user', 'fixed', 'hidden'], true)) {
                $mode = 'user';
            }
            $row = ['mode' => $mode];
            if (array_key_exists('value', $entry) && $entry['value'] !== null && $entry['value'] !== '') {
                $row['value'] = (int) $entry['value'];
            }
            if (array_key_exists('default', $entry) && $entry['default'] !== null && $entry['default'] !== '') {
                $row['default'] = (int) $entry['default'];
            }
            $defaults[$key] = $row;
        }

        return $defaults;
    }

    /**
     * @param array<string, mixed> $policies
     */
    public static function setResourceFieldPolicies(array $policies): void
    {
        $merged = self::getDefaultResourceFieldPolicies();
        foreach (self::RESOURCE_FIELD_KEYS as $key) {
            if (!isset($policies[$key]) || !is_array($policies[$key])) {
                continue;
            }
            $entry = $policies[$key];
            $mode = isset($entry['mode']) ? (string) $entry['mode'] : 'user';
            if (!in_array($mode, ['user', 'fixed', 'hidden'], true)) {
                $mode = 'user';
            }
            $row = ['mode' => $mode];
            if ($mode === 'fixed' || $mode === 'hidden') {
                $val = isset($entry['value']) ? (int) $entry['value'] : 0;
                if (in_array($key, ['memory', 'disk'], true)) {
                    $val = max(128, $val);
                }
                if ($key === 'cpu') {
                    $val = max(0, $val);
                }
                if (in_array($key, ['swap', 'io', 'database_limit', 'allocation_limit', 'backup_limit'], true)) {
                    $val = max(0, $val);
                }
                $row['value'] = $val;
            } elseif ($mode === 'user' && isset($entry['default']) && $entry['default'] !== '' && $entry['default'] !== null) {
                $d = (int) $entry['default'];
                if (in_array($key, ['memory', 'disk'], true)) {
                    $d = max(128, $d);
                }
                if ($key === 'cpu') {
                    $d = max(0, $d);
                }
                if (in_array($key, ['swap', 'io', 'database_limit', 'allocation_limit', 'backup_limit'], true)) {
                    $d = max(0, $d);
                }
                $row['default'] = $d;
            }
            $merged[$key] = $row;
        }

        PluginSettings::setSetting('billingresourcesnewservers', 'resource_field_policies', json_encode($merged));
    }

    /**
     * Force server creation payload to match fixed/hidden field policies (anti-tamper).
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function applyResourceFieldPoliciesToPayload(array $data): array
    {
        $policies = self::getResourceFieldPolicies();
        foreach (self::RESOURCE_FIELD_KEYS as $key) {
            $p = $policies[$key] ?? ['mode' => 'user'];
            $mode = $p['mode'] ?? 'user';
            if ($mode !== 'fixed' && $mode !== 'hidden') {
                continue;
            }
            $val = isset($p['value']) ? (int) $p['value'] : 0;
            if (in_array($key, ['memory', 'disk'], true)) {
                $val = max(128, $val);
            }
            if ($key === 'cpu') {
                $val = max(0, $val);
            }
            if (in_array($key, ['swap', 'io', 'database_limit', 'allocation_limit', 'backup_limit'], true)) {
                $val = max(0, $val);
            }
            $data[$key] = $val;
        }

        return $data;
    }

    /**
     * Default placement policies: users choose location, node, realm, and spell.
     *
     * @return array<string, array{mode: string, value?: int|string|null, default?: int|string|null}>
     */
    public static function getDefaultPlacementFieldPolicies(): array
    {
        $out = [];
        foreach (self::PLACEMENT_FIELD_KEYS as $key) {
            $out[$key] = ['mode' => 'user'];
        }

        return $out;
    }

    /**
     * Parsed placement field policies merged with defaults.
     *
     * @return array<string, array{mode: string, value?: int|string|null, default?: int|string|null}>
     */
    public static function getPlacementFieldPolicies(): array
    {
        $raw = PluginSettings::getSetting('billingresourcesnewservers', 'placement_field_policies');
        $defaults = self::getDefaultPlacementFieldPolicies();
        if ($raw === null || $raw === '') {
            return $defaults;
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            $decoded = json_decode(
                html_entity_decode((string) $raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                true
            );
        }
        if (!is_array($decoded)) {
            return $defaults;
        }

        foreach (self::PLACEMENT_FIELD_KEYS as $key) {
            if (!isset($decoded[$key]) || !is_array($decoded[$key])) {
                continue;
            }
            $entry = $decoded[$key];
            $mode = $entry['mode'] ?? 'user';
            if (!in_array($mode, ['user', 'fixed', 'hidden'], true)) {
                $mode = 'user';
            }
            $row = ['mode' => $mode];
            if ($mode === 'fixed' || $mode === 'hidden') {
                $val = self::normalizePlacementPolicyValue($entry['value'] ?? null, $key);
                if ($val !== null) {
                    $row['value'] = $val;
                }
            } elseif ($mode === 'user' && array_key_exists('default', $entry)) {
                $def = self::normalizePlacementPolicyValue($entry['default'], $key);
                if ($def !== null) {
                    $row['default'] = $def;
                }
            }
            $defaults[$key] = $row;
        }

        return $defaults;
    }

    /**
     * @param array<string, mixed> $policies
     */
    public static function setPlacementFieldPolicies(array $policies): void
    {
        $merged = self::getDefaultPlacementFieldPolicies();
        foreach (self::PLACEMENT_FIELD_KEYS as $key) {
            if (!isset($policies[$key]) || !is_array($policies[$key])) {
                continue;
            }
            $entry = $policies[$key];
            $mode = isset($entry['mode']) ? (string) $entry['mode'] : 'user';
            if (!in_array($mode, ['user', 'fixed', 'hidden'], true)) {
                $mode = 'user';
            }
            $row = ['mode' => $mode];
            if ($mode === 'fixed' || $mode === 'hidden') {
                $val = self::normalizePlacementPolicyValue($entry['value'] ?? null, $key);
                if ($val !== null) {
                    $row['value'] = $val;
                }
            } elseif ($mode === 'user' && array_key_exists('default', $entry)) {
                $def = self::normalizePlacementPolicyValue($entry['default'], $key);
                if ($def !== null) {
                    $row['default'] = $def;
                }
            }
            $merged[$key] = $row;
        }

        PluginSettings::setSetting('billingresourcesnewservers', 'placement_field_policies', json_encode($merged));
    }

    /**
     * Force server creation payload to match fixed/hidden placement policies (anti-tamper).
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function applyPlacementFieldPoliciesToPayload(int $userId, array $data): array
    {
        return ServerCreationHelper::applyPlacementFieldPoliciesToPayload($userId, $data);
    }

    /**
     * Check if user server creation is enabled.
     *
     * @return bool True if enabled, false otherwise
     */
    public static function isUserCreationEnabled(): bool
    {
        $enabled = PluginSettings::getSetting('billingresourcesnewservers', 'user_creation_enabled');

        return $enabled === 'true';
    }

    /**
     * Set user creation enabled status.
     *
     * @param bool $enabled Whether to enable user creation
     */
    public static function setUserCreationEnabled(bool $enabled): void
    {
        PluginSettings::setSetting('billingresourcesnewservers', 'user_creation_enabled', $enabled ? 'true' : 'false');
    }

    /**
     * Get allowed location IDs.
     *
     * @return array<int> Array of location IDs (empty array = all allowed)
     */
    public static function getAllowedLocations(): array
    {
        $locationsJson = PluginSettings::getSetting('billingresourcesnewservers', 'allowed_locations');
        if ($locationsJson === null || $locationsJson === '') {
            return [];
        }

        $decoded = json_decode($locationsJson, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_map('intval', $decoded);
    }

    /**
     * Set allowed location IDs.
     *
     * @param array<int> $locationIds Array of location IDs (empty array = all allowed)
     */
    public static function setAllowedLocations(array $locationIds): void
    {
        $locationIds = array_map('intval', $locationIds);
        PluginSettings::setSetting('billingresourcesnewservers', 'allowed_locations', json_encode($locationIds));
    }

    /**
     * Get allowed node IDs.
     *
     * @return array<int> Array of node IDs (empty array = all allowed)
     */
    public static function getAllowedNodes(): array
    {
        $nodesJson = PluginSettings::getSetting('billingresourcesnewservers', 'allowed_nodes');
        if ($nodesJson === null || $nodesJson === '') {
            return [];
        }

        $decoded = json_decode($nodesJson, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_map('intval', $decoded);
    }

    /**
     * Set allowed node IDs.
     *
     * @param array<int> $nodeIds Array of node IDs (empty array = all allowed)
     */
    public static function setAllowedNodes(array $nodeIds): void
    {
        $nodeIds = array_map('intval', $nodeIds);
        PluginSettings::setSetting('billingresourcesnewservers', 'allowed_nodes', json_encode($nodeIds));
    }

    /**
     * Get allowed realm IDs.
     *
     * @return array<int> Array of realm IDs (empty array = all allowed)
     */
    public static function getAllowedRealms(): array
    {
        $realmsJson = PluginSettings::getSetting('billingresourcesnewservers', 'allowed_realms');
        if ($realmsJson === null || $realmsJson === '') {
            return [];
        }

        $decoded = json_decode($realmsJson, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_map('intval', $decoded);
    }

    /**
     * Set allowed realm IDs.
     *
     * @param array<int> $realmIds Array of realm IDs (empty array = all allowed)
     */
    public static function setAllowedRealms(array $realmIds): void
    {
        $realmIds = array_map('intval', $realmIds);
        PluginSettings::setSetting('billingresourcesnewservers', 'allowed_realms', json_encode($realmIds));
    }

    /**
     * Get allowed spell IDs (nests).
     *
     * @return array<int> Array of spell IDs (empty array = all allowed)
     */
    public static function getAllowedSpells(): array
    {
        $spellsJson = PluginSettings::getSetting('billingresourcesnewservers', 'allowed_spells');
        if ($spellsJson === null || $spellsJson === '') {
            return [];
        }

        $decoded = json_decode($spellsJson, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_map('intval', $decoded);
    }

    /**
     * Set allowed spell IDs (nests).
     *
     * @param array<int> $spellIds Array of spell IDs (empty array = all allowed)
     */
    public static function setAllowedSpells(array $spellIds): void
    {
        $spellIds = array_map('intval', $spellIds);
        PluginSettings::setSetting('billingresourcesnewservers', 'allowed_spells', json_encode($spellIds));
    }

    /**
     * Get minimum memory required (MB).
     *
     * @return int Minimum memory in MB (default: 128)
     */
    public static function getMinimumMemory(): int
    {
        $min = PluginSettings::getSetting('billingresourcesnewservers', 'minimum_memory');
        if ($min === null || $min === '') {
            return 128;
        }

        return (int) $min;
    }

    /**
     * Set minimum memory required (MB).
     *
     * @param int $memory Minimum memory in MB
     */
    public static function setMinimumMemory(int $memory): void
    {
        PluginSettings::setSetting('billingresourcesnewservers', 'minimum_memory', (string) max(128, $memory));
    }

    /**
     * Get minimum CPU required (%).
     *
     * @return int Minimum CPU in % (default: 0)
     */
    public static function getMinimumCpu(): int
    {
        $min = PluginSettings::getSetting('billingresourcesnewservers', 'minimum_cpu');
        if ($min === null || $min === '') {
            return 0;
        }

        return (int) $min;
    }

    /**
     * Set minimum CPU required (%).
     *
     * @param int $cpu Minimum CPU in %
     */
    public static function setMinimumCpu(int $cpu): void
    {
        PluginSettings::setSetting('billingresourcesnewservers', 'minimum_cpu', (string) max(0, $cpu));
    }

    /**
     * Get minimum disk required (MB).
     *
     * @return int Minimum disk in MB (default: 128)
     */
    public static function getMinimumDisk(): int
    {
        $min = PluginSettings::getSetting('billingresourcesnewservers', 'minimum_disk');
        if ($min === null || $min === '') {
            return 128;
        }

        return (int) $min;
    }

    /**
     * Set minimum disk required (MB).
     *
     * @param int $disk Minimum disk in MB
     */
    public static function setMinimumDisk(int $disk): void
    {
        PluginSettings::setSetting('billingresourcesnewservers', 'minimum_disk', (string) max(128, $disk));
    }

    /**
     * Default max servers per node when a node has no individual cap (0 = unlimited).
     */
    public static function getMaxServersPerNode(): int
    {
        $max = PluginSettings::getSetting('billingresourcesnewservers', 'max_servers_per_node');
        if ($max === null || $max === '') {
            return 0;
        }

        return max(0, (int) $max);
    }

    /**
     * @param int $max Default maximum servers per node (0 = unlimited)
     */
    public static function setMaxServersPerNode(int $max): void
    {
        PluginSettings::setSetting('billingresourcesnewservers', 'max_servers_per_node', (string) max(0, $max));
    }

    /**
     * Per-node server caps (node ID => max). Empty entry = use default above.
     *
     * @return array<int, int>
     */
    public static function getNodeServerCaps(): array
    {
        $raw = PluginSettings::getSetting('billingresourcesnewservers', 'node_server_caps');
        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            $decoded = json_decode(
                html_entity_decode((string) $raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                true
            );
        }
        if (!is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $nodeId => $max) {
            $id = (int) $nodeId;
            $m = max(0, (int) $max);
            if ($id > 0 && $m > 0) {
                $out[$id] = $m;
            }
        }

        return $out;
    }

    /**
     * @param array<int|string, mixed> $caps Node ID => max servers (> 0); omit or 0 to clear override
     */
    public static function setNodeServerCaps(array $caps): void
    {
        $normalized = [];
        foreach ($caps as $nodeId => $max) {
            $id = (int) $nodeId;
            $m = max(0, (int) $max);
            if ($id > 0 && $m > 0) {
                $normalized[(string) $id] = $m;
            }
        }

        PluginSettings::setSetting('billingresourcesnewservers', 'node_server_caps', json_encode($normalized));
    }

    /**
     * Effective max servers for one node (individual cap, else default; 0 = unlimited).
     */
    public static function getMaxServersForNode(int $nodeId): int
    {
        if ($nodeId <= 0) {
            return 0;
        }

        $caps = self::getNodeServerCaps();
        if (isset($caps[$nodeId])) {
            return $caps[$nodeId];
        }

        return self::getMaxServersPerNode();
    }

    /**
     * Count servers currently on a node (panel-wide, all owners).
     */
    public static function getNodeServerCount(int $nodeId): int
    {
        if ($nodeId <= 0) {
            return 0;
        }

        return Server::getCount(nodeId: $nodeId);
    }

    /**
     * Whether the node has reached its configured server cap.
     */
    public static function isNodeAtServerCap(int $nodeId): bool
    {
        $max = self::getMaxServersForNode($nodeId);
        if ($max <= 0) {
            return false;
        }

        return self::getNodeServerCount($nodeId) >= $max;
    }

    /**
     * Error shown when a node is at its server cap. Supports {max} placeholder.
     */
    public static function getNodeAtCapacityErrorMessage(int $nodeId): string
    {
        $max = self::getMaxServersForNode($nodeId);
        $message = PluginSettings::getSetting('billingresourcesnewservers', 'node_at_capacity_error');
        if ($message === null || $message === '') {
            $message = 'This node has reached the maximum of {max} servers';
        }

        if (str_contains($message, '{max}')) {
            return str_replace('{max}', (string) $max, $message);
        }

        return $message;
    }

    /**
     * @param string $message Custom error message (may include {max} placeholder)
     */
    public static function setNodeAtCapacityErrorMessage(string $message): void
    {
        PluginSettings::setSetting('billingresourcesnewservers', 'node_at_capacity_error', $message);
    }

    /**
     * Get user restriction mode.
     *
     * @return string 'all' for all users, 'specific' for specific users only
     */
    public static function getUserRestrictionMode(): string
    {
        $mode = PluginSettings::getSetting('billingresourcesnewservers', 'user_restriction_mode');
        if ($mode === null || $mode === '') {
            return 'all'; // Default to all users
        }

        return $mode === 'specific' ? 'specific' : 'all';
    }

    /**
     * Set user restriction mode.
     *
     * @param string $mode 'all' or 'specific'
     */
    public static function setUserRestrictionMode(string $mode): void
    {
        $mode = $mode === 'specific' ? 'specific' : 'all';
        PluginSettings::setSetting('billingresourcesnewservers', 'user_restriction_mode', $mode);
    }

    /**
     * Get allowed user IDs.
     *
     * @return array<int> Array of user IDs (empty array = no specific users allowed if mode is 'specific')
     */
    public static function getAllowedUsers(): array
    {
        $usersJson = PluginSettings::getSetting('billingresourcesnewservers', 'allowed_users');
        if ($usersJson === null || $usersJson === '') {
            return [];
        }

        $decoded = json_decode($usersJson, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_map('intval', $decoded);
    }

    /**
     * Set allowed user IDs.
     *
     * @param array<int> $userIds Array of user IDs
     */
    public static function setAllowedUsers(array $userIds): void
    {
        $userIds = array_map('intval', $userIds);
        PluginSettings::setSetting('billingresourcesnewservers', 'allowed_users', json_encode($userIds));
    }

    /**
     * Check if a user is allowed to create servers.
     *
     * @param int $userId User ID
     *
     * @return bool True if user is allowed, false otherwise
     */
    public static function isUserAllowed(int $userId): bool
    {
        // First check if user creation is enabled globally
        if (!self::isUserCreationEnabled()) {
            return false;
        }

        $mode = self::getUserRestrictionMode();

        // If mode is 'all', allow everyone
        if ($mode === 'all') {
            return true;
        }

        // If mode is 'specific', check if user is in allowed list OR has any permissions
        $allowedUsers = self::getAllowedUsers();
        if (in_array($userId, $allowedUsers, true)) {
            return true;
        }

        // Check if user has any specific permissions (locations, nodes, realms, spells)
        $userPermissions = \App\Addons\billingresourcesnewservers\Chat\UserPermission::getByUserId($userId);

        return !empty($userPermissions);
    }

    /**
     * Check if user has permission for a specific resource.
     *
     * @param int $userId User ID
     * @param string $resourceType Resource type (location, node, realm, spell)
     * @param int $resourceId Resource ID
     *
     * @return array{allowed: bool, custom_error?: string} Permission check result
     */
    public static function checkUserResourcePermission(int $userId, string $resourceType, int $resourceId): array
    {
        // First check per-resource permission mode
        $resourcePermissionMode = \App\Addons\billingresourcesnewservers\Chat\ResourcePermission::getPermissionMode($resourceType, $resourceId);

        // If resource is set to 'open', check global restrictions only
        if ($resourcePermissionMode === 'open') {
            // Check global restrictions
            switch ($resourceType) {
                case 'location':
                    if (!self::isLocationAllowed($resourceId)) {
                        $defaultError = \App\Addons\billingresourcesnewservers\Chat\ResourcePermission::getDefaultErrorMessage($resourceType, $resourceId)
                            ?? self::getResourceDefaultErrorMessage('location');

                        return ['allowed' => false, 'custom_error' => $defaultError];
                    }
                    break;
                case 'node':
                    if (!self::isNodeAllowed($resourceId)) {
                        $defaultError = \App\Addons\billingresourcesnewservers\Chat\ResourcePermission::getDefaultErrorMessage($resourceType, $resourceId)
                            ?? self::getResourceDefaultErrorMessage('node');

                        return ['allowed' => false, 'custom_error' => $defaultError];
                    }
                    break;
                case 'realm':
                    if (!self::isRealmAllowed($resourceId)) {
                        $defaultError = \App\Addons\billingresourcesnewservers\Chat\ResourcePermission::getDefaultErrorMessage($resourceType, $resourceId)
                            ?? self::getResourceDefaultErrorMessage('realm');

                        return ['allowed' => false, 'custom_error' => $defaultError];
                    }
                    break;
                case 'spell':
                    if (!self::isSpellAllowed($resourceId)) {
                        $defaultError = \App\Addons\billingresourcesnewservers\Chat\ResourcePermission::getDefaultErrorMessage($resourceType, $resourceId)
                            ?? self::getResourceDefaultErrorMessage('spell');

                        return ['allowed' => false, 'custom_error' => $defaultError];
                    }
                    break;
            }

            return ['allowed' => true];
        }

        // Resource requires permissions - check user-specific and group permissions
        // First check user-specific permissions
        $userPermission = \App\Addons\billingresourcesnewservers\Chat\UserPermission::getUserPermission($userId, $resourceType, $resourceId);
        if ($userPermission) {
            $customError = $userPermission['custom_error_message']
                ?? \App\Addons\billingresourcesnewservers\Chat\ResourcePermission::getDefaultErrorMessage($resourceType, $resourceId)
                ?? self::getResourceDefaultErrorMessage($resourceType);

            return ['allowed' => true, 'custom_error' => $customError];
        }

        // Check group permissions
        $groupPermissions = \App\Addons\billingresourcesnewservers\Chat\GroupPermission::getByUserId($userId);
        foreach ($groupPermissions as $perm) {
            if ($perm['resource_type'] === $resourceType && (int) $perm['resource_id'] === $resourceId) {
                $customError = $perm['custom_error_message']
                    ?? \App\Addons\billingresourcesnewservers\Chat\ResourcePermission::getDefaultErrorMessage($resourceType, $resourceId)
                    ?? self::getResourceDefaultErrorMessage($resourceType);

                return ['allowed' => true, 'custom_error' => $customError];
            }
        }

        // User doesn't have permission for this resource
        $defaultError = \App\Addons\billingresourcesnewservers\Chat\ResourcePermission::getDefaultErrorMessage($resourceType, $resourceId)
            ?? self::getResourceDefaultErrorMessage($resourceType);

        return ['allowed' => false, 'custom_error' => $defaultError];
    }

    /**
     * Get permission mode for a resource type.
     *
     * @param string $resourceType Resource type (location, node, realm, spell)
     *
     * @return string 'open' for open to everyone, 'restricted' for permission-based
     */
    public static function getResourcePermissionMode(string $resourceType): string
    {
        $mode = PluginSettings::getSetting('billingresourcesnewservers', 'permission_mode_' . $resourceType);
        if ($mode === null || $mode === '') {
            return 'open'; // Default to open
        }

        return $mode === 'restricted' ? 'restricted' : 'open';
    }

    /**
     * Set permission mode for a resource type.
     *
     * @param string $resourceType Resource type (location, node, realm, spell)
     * @param string $mode 'open' or 'restricted'
     */
    public static function setResourcePermissionMode(string $resourceType, string $mode): void
    {
        $mode = $mode === 'restricted' ? 'restricted' : 'open';
        PluginSettings::setSetting('billingresourcesnewservers', 'permission_mode_' . $resourceType, $mode);
    }

    /**
     * Get default error message for a resource type.
     *
     * @param string $resourceType Resource type (location, node, realm, spell)
     *
     * @return string Default error message
     */
    public static function getResourceDefaultErrorMessage(string $resourceType): string
    {
        $message = PluginSettings::getSetting('billingresourcesnewservers', 'default_error_' . $resourceType);
        if ($message === null || $message === '') {
            return 'You do not have permission to use this ' . $resourceType;
        }

        return $message;
    }

    /**
     * Set default error message for a resource type.
     *
     * @param string $resourceType Resource type (location, node, realm, spell)
     * @param string $message Error message
     */
    public static function setResourceDefaultErrorMessage(string $resourceType, string $message): void
    {
        PluginSettings::setSetting('billingresourcesnewservers', 'default_error_' . $resourceType, $message);
    }

    /**
     * Get all settings.
     *
     * @return array<string,mixed> Settings structure
     */
    public static function getAllSettings(): array
    {
        return [
            'user_creation_enabled' => self::isUserCreationEnabled(),
            'user_restriction_mode' => self::getUserRestrictionMode(),
            'allowed_users' => self::getAllowedUsers(),
            'allowed_locations' => self::getAllowedLocations(),
            'allowed_nodes' => self::getAllowedNodes(),
            'allowed_realms' => self::getAllowedRealms(),
            'allowed_spells' => self::getAllowedSpells(),
            'minimum_memory' => self::getMinimumMemory(),
            'minimum_cpu' => self::getMinimumCpu(),
            'minimum_disk' => self::getMinimumDisk(),
            'max_servers_per_node' => self::getMaxServersPerNode(),
            'node_server_caps' => self::getNodeServerCaps(),
            'node_at_capacity_error' => PluginSettings::getSetting('billingresourcesnewservers', 'node_at_capacity_error') ?? '',
            'permission_mode_location' => self::getResourcePermissionMode('location'),
            'permission_mode_node' => self::getResourcePermissionMode('node'),
            'permission_mode_realm' => self::getResourcePermissionMode('realm'),
            'permission_mode_spell' => self::getResourcePermissionMode('spell'),
            'default_error_location' => self::getResourceDefaultErrorMessage('location'),
            'default_error_node' => self::getResourceDefaultErrorMessage('node'),
            'default_error_realm' => self::getResourceDefaultErrorMessage('realm'),
            'default_error_spell' => self::getResourceDefaultErrorMessage('spell'),
            'resource_field_policies' => self::getResourceFieldPolicies(),
            'placement_field_policies' => self::getPlacementFieldPolicies(),
        ];
    }

    /**
     * Check if a location is allowed.
     *
     * @param int $locationId Location ID
     *
     * @return bool True if allowed (or no restrictions), false otherwise
     */
    public static function isLocationAllowed(int $locationId): bool
    {
        $allowed = self::getAllowedLocations();

        // Empty array means all locations are allowed
        return empty($allowed) || in_array($locationId, $allowed, true);
    }

    /**
     * Check if a node is allowed.
     *
     * @param int $nodeId Node ID
     *
     * @return bool True if allowed (or no restrictions), false otherwise
     */
    public static function isNodeAllowed(int $nodeId): bool
    {
        $allowed = self::getAllowedNodes();

        // Empty array means all nodes are allowed
        return empty($allowed) || in_array($nodeId, $allowed, true);
    }

    /**
     * Check if a realm is allowed.
     *
     * @param int $realmId Realm ID
     *
     * @return bool True if allowed (or no restrictions), false otherwise
     */
    public static function isRealmAllowed(int $realmId): bool
    {
        $allowed = self::getAllowedRealms();

        // Empty array means all realms are allowed
        return empty($allowed) || in_array($realmId, $allowed, true);
    }

    /**
     * Check if a spell is allowed.
     *
     * @param int $spellId Spell ID
     *
     * @return bool True if allowed (or no restrictions), false otherwise
     */
    public static function isSpellAllowed(int $spellId): bool
    {
        $allowed = self::getAllowedSpells();

        // Empty array means all spells are allowed
        return empty($allowed) || in_array($spellId, $allowed, true);
    }

    private static function normalizePlacementPolicyValue(mixed $raw, string $key): int | string | null
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_string($raw)) {
            $s = trim($raw);
            if ($s === 'first') {
                return 'first';
            }
            if ($s === 'least_capacity' && $key === 'node') {
                return 'least_capacity';
            }
            if (is_numeric($s)) {
                return (int) $s;
            }

            return null;
        }

        if (is_numeric($raw)) {
            return (int) $raw;
        }

        return null;
    }
}
