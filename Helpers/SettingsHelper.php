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

use App\Plugins\PluginSettings;

/**
 * Helper for managing plugin settings using PluginSettings.
 */
class SettingsHelper
{
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
            'permission_mode_location' => self::getResourcePermissionMode('location'),
            'permission_mode_node' => self::getResourcePermissionMode('node'),
            'permission_mode_realm' => self::getResourcePermissionMode('realm'),
            'permission_mode_spell' => self::getResourcePermissionMode('spell'),
            'default_error_location' => self::getResourceDefaultErrorMessage('location'),
            'default_error_node' => self::getResourceDefaultErrorMessage('node'),
            'default_error_realm' => self::getResourceDefaultErrorMessage('realm'),
            'default_error_spell' => self::getResourceDefaultErrorMessage('spell'),
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
}
