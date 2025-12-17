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
     * Get all settings.
     *
     * @return array<string,mixed> Settings structure
     */
    public static function getAllSettings(): array
    {
        return [
            'user_creation_enabled' => self::isUserCreationEnabled(),
            'allowed_locations' => self::getAllowedLocations(),
            'allowed_nodes' => self::getAllowedNodes(),
            'allowed_realms' => self::getAllowedRealms(),
            'allowed_spells' => self::getAllowedSpells(),
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
