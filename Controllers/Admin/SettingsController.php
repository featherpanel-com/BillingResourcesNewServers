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

namespace App\Addons\billingresourcesnewservers\Controllers\Admin;

use App\Chat\Activity;
use App\Helpers\ApiResponse;
use OpenApi\Attributes as OA;
use App\CloudFlare\CloudFlareRealIP;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Addons\billingresourcesnewservers\Helpers\SettingsHelper;

#[OA\Tag(name: 'Admin - Billing Resources New Servers', description: 'Settings management for user server creation')]
class SettingsController
{
    #[OA\Get(
        path: '/api/admin/billingresourcesnewservers/settings',
        summary: 'Get plugin settings',
        description: 'Get all settings for the billingresourcesnewservers plugin',
        tags: ['Admin - Billing Resources New Servers'],
        responses: [
            new OA\Response(response: 200, description: 'Settings retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function getSettings(Request $request): Response
    {
        $settings = SettingsHelper::getAllSettings();

        return ApiResponse::success($settings, 'Settings retrieved successfully', 200);
    }

    #[OA\Patch(
        path: '/api/admin/billingresourcesnewservers/settings',
        summary: 'Update plugin settings',
        description: 'Update settings for the billingresourcesnewservers plugin',
        tags: ['Admin - Billing Resources New Servers'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'user_creation_enabled', type: 'boolean', description: 'Enable/disable user server creation'),
                    new OA\Property(
                        property: 'allowed_locations',
                        type: 'array',
                        description: 'Array of allowed location IDs (empty array = all allowed)',
                        items: new OA\Items(type: 'integer')
                    ),
                    new OA\Property(
                        property: 'allowed_nodes',
                        type: 'array',
                        description: 'Array of allowed node IDs (empty array = all allowed)',
                        items: new OA\Items(type: 'integer')
                    ),
                    new OA\Property(
                        property: 'allowed_realms',
                        type: 'array',
                        description: 'Array of allowed realm IDs (empty array = all allowed)',
                        items: new OA\Items(type: 'integer')
                    ),
                    new OA\Property(
                        property: 'allowed_spells',
                        type: 'array',
                        description: 'Array of allowed spell IDs (empty array = all allowed)',
                        items: new OA\Items(type: 'integer')
                    ),
                    new OA\Property(property: 'minimum_memory', type: 'integer', description: 'Minimum memory required (MB)', example: 128),
                    new OA\Property(property: 'minimum_cpu', type: 'integer', description: 'Minimum CPU required (%)', example: 0),
                    new OA\Property(property: 'minimum_disk', type: 'integer', description: 'Minimum disk required (MB)', example: 128),
                    new OA\Property(property: 'user_restriction_mode', type: 'string', enum: ['all', 'specific'], description: 'User restriction mode: "all" for all users, "specific" for specific users only', example: 'all'),
                    new OA\Property(
                        property: 'allowed_users',
                        type: 'array',
                        description: 'Array of allowed user IDs (only used when user_restriction_mode is "specific")',
                        items: new OA\Items(type: 'integer')
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Settings updated successfully'),
            new OA\Response(response: 400, description: 'Invalid input'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function updateSettings(Request $request): Response
    {
        $admin = $request->get('user');
        $data = json_decode($request->getContent(), true);

        if ($data === null) {
            return ApiResponse::error('Invalid JSON', 'INVALID_JSON', 400);
        }

        // Update user creation enabled
        if (isset($data['user_creation_enabled'])) {
            $enabled = filter_var($data['user_creation_enabled'], FILTER_VALIDATE_BOOLEAN);
            SettingsHelper::setUserCreationEnabled($enabled);
        }

        // Update allowed locations
        if (isset($data['allowed_locations'])) {
            if (!is_array($data['allowed_locations'])) {
                return ApiResponse::error('allowed_locations must be an array', 'INVALID_TYPE', 400);
            }
            SettingsHelper::setAllowedLocations($data['allowed_locations']);
        }

        // Update allowed nodes
        if (isset($data['allowed_nodes'])) {
            if (!is_array($data['allowed_nodes'])) {
                return ApiResponse::error('allowed_nodes must be an array', 'INVALID_TYPE', 400);
            }
            SettingsHelper::setAllowedNodes($data['allowed_nodes']);
        }

        // Update allowed realms
        if (isset($data['allowed_realms'])) {
            if (!is_array($data['allowed_realms'])) {
                return ApiResponse::error('allowed_realms must be an array', 'INVALID_TYPE', 400);
            }
            SettingsHelper::setAllowedRealms($data['allowed_realms']);
        }

        // Update allowed spells
        if (isset($data['allowed_spells'])) {
            if (!is_array($data['allowed_spells'])) {
                return ApiResponse::error('allowed_spells must be an array', 'INVALID_TYPE', 400);
            }
            SettingsHelper::setAllowedSpells($data['allowed_spells']);
        }

        // Update minimum memory
        if (isset($data['minimum_memory'])) {
            if (!is_numeric($data['minimum_memory']) || (int) $data['minimum_memory'] < 128) {
                return ApiResponse::error('minimum_memory must be at least 128 MB', 'INVALID_MINIMUM_MEMORY', 400);
            }
            SettingsHelper::setMinimumMemory((int) $data['minimum_memory']);
        }

        // Update minimum CPU
        if (isset($data['minimum_cpu'])) {
            if (!is_numeric($data['minimum_cpu']) || (int) $data['minimum_cpu'] < 0) {
                return ApiResponse::error('minimum_cpu must be 0 or greater', 'INVALID_MINIMUM_CPU', 400);
            }
            SettingsHelper::setMinimumCpu((int) $data['minimum_cpu']);
        }

        // Update minimum disk
        if (isset($data['minimum_disk'])) {
            if (!is_numeric($data['minimum_disk']) || (int) $data['minimum_disk'] < 128) {
                return ApiResponse::error('minimum_disk must be at least 128 MB', 'INVALID_MINIMUM_DISK', 400);
            }
            SettingsHelper::setMinimumDisk((int) $data['minimum_disk']);
        }

        // Update user restriction mode
        if (isset($data['user_restriction_mode'])) {
            if (!in_array($data['user_restriction_mode'], ['all', 'specific'], true)) {
                return ApiResponse::error('user_restriction_mode must be "all" or "specific"', 'INVALID_USER_RESTRICTION_MODE', 400);
            }
            SettingsHelper::setUserRestrictionMode($data['user_restriction_mode']);
        }

        // Update allowed users
        if (isset($data['allowed_users'])) {
            if (!is_array($data['allowed_users'])) {
                return ApiResponse::error('allowed_users must be an array', 'INVALID_TYPE', 400);
            }
            SettingsHelper::setAllowedUsers($data['allowed_users']);
        }

        // Update permission modes for resource types
        $resourceTypes = ['location', 'node', 'realm', 'spell'];
        foreach ($resourceTypes as $resourceType) {
            $key = 'permission_mode_' . $resourceType;
            if (isset($data[$key])) {
                if (!in_array($data[$key], ['open', 'restricted'], true)) {
                    return ApiResponse::error($key . ' must be "open" or "restricted"', 'INVALID_PERMISSION_MODE', 400);
                }
                SettingsHelper::setResourcePermissionMode($resourceType, $data[$key]);
            }

            // Update default error messages
            $errorKey = 'default_error_' . $resourceType;
            if (isset($data[$errorKey])) {
                SettingsHelper::setResourceDefaultErrorMessage($resourceType, (string) $data[$errorKey]);
            }
        }

        // Log activity
        Activity::createActivity([
            'user_uuid' => $admin['uuid'] ?? null,
            'name' => 'billingresourcesnewservers_update_settings',
            'context' => 'Updated billingresourcesnewservers plugin settings',
            'ip_address' => CloudFlareRealIP::getRealIP(),
        ]);

        $settings = SettingsHelper::getAllSettings();

        return ApiResponse::success($settings, 'Settings updated successfully', 200);
    }
}
