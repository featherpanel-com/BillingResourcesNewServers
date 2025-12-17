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
use App\Addons\billingresourcesnewservers\Chat\ResourcePermission;

#[OA\Tag(name: 'Admin - Billing Resources New Servers - Resource Permissions', description: 'Manage per-resource permission settings')]
class ResourcePermissionsController
{
    #[OA\Get(
        path: '/api/admin/billingresourcesnewservers/resource-permissions/{resourceType}',
        summary: 'Get resource permissions',
        description: 'Get all permission settings for a resource type',
        tags: ['Admin - Billing Resources New Servers - Resource Permissions'],
        parameters: [
            new OA\Parameter(name: 'resourceType', description: 'Type of resource (location, node, realm, spell)', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['location', 'node', 'realm', 'spell'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Permissions retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function getResourcePermissions(Request $request, string $resourceType): Response
    {
        if (!in_array($resourceType, ['location', 'node', 'realm', 'spell'], true)) {
            return ApiResponse::error('Invalid resource type', 'INVALID_RESOURCE_TYPE', 400);
        }

        $permissions = ResourcePermission::getByResourceType($resourceType);

        return ApiResponse::success($permissions, 'Permissions retrieved successfully', 200);
    }

    #[OA\Post(
        path: '/api/admin/billingresourcesnewservers/resource-permissions',
        summary: 'Set resource permission',
        description: 'Set permission mode for a specific resource',
        tags: ['Admin - Billing Resources New Servers - Resource Permissions'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'resource_type', type: 'string', enum: ['location', 'node', 'realm', 'spell'], description: 'Type of resource'),
                    new OA\Property(property: 'resource_id', type: 'integer', description: 'ID of the resource'),
                    new OA\Property(property: 'permission_mode', type: 'string', enum: ['open', 'restricted'], description: 'Permission mode'),
                    new OA\Property(property: 'default_error_message', type: 'string', nullable: true, description: 'Default error message'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Permission set successfully'),
            new OA\Response(response: 400, description: 'Invalid input'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function setResourcePermission(Request $request): Response
    {
        $admin = $request->get('user');
        $data = json_decode($request->getContent(), true);

        if ($data === null) {
            return ApiResponse::error('Invalid JSON', 'INVALID_JSON', 400);
        }

        $resourceType = $data['resource_type'] ?? null;
        $resourceId = $data['resource_id'] ?? null;
        $permissionMode = $data['permission_mode'] ?? null;
        $defaultErrorMessage = $data['default_error_message'] ?? null;

        if (!in_array($resourceType, ['location', 'node', 'realm', 'spell'], true)) {
            return ApiResponse::error('Invalid resource_type', 'INVALID_RESOURCE_TYPE', 400);
        }

        if (!is_numeric($resourceId)) {
            return ApiResponse::error('Invalid resource_id', 'INVALID_RESOURCE_ID', 400);
        }

        if (!in_array($permissionMode, ['open', 'restricted'], true)) {
            return ApiResponse::error('permission_mode must be "open" or "restricted"', 'INVALID_PERMISSION_MODE', 400);
        }

        $success = ResourcePermission::setPermissionMode($resourceType, (int) $resourceId, $permissionMode, $defaultErrorMessage);

        if (!$success) {
            return ApiResponse::error('Failed to set permission', 'SET_FAILED', 400);
        }

        Activity::createActivity([
            'user_uuid' => $admin['uuid'] ?? null,
            'name' => 'billingresourcesnewservers_set_resource_permission',
            'context' => 'Set ' . $resourceType . ' ' . $resourceId . ' to ' . $permissionMode,
            'ip_address' => CloudFlareRealIP::getRealIP(),
        ]);

        return ApiResponse::success(null, 'Permission set successfully', 200);
    }

    #[OA\Post(
        path: '/api/admin/billingresourcesnewservers/resource-permissions/batch',
        summary: 'Batch set resource permissions',
        description: 'Set permission modes for multiple resources at once',
        tags: ['Admin - Billing Resources New Servers - Resource Permissions'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'permissions',
                        type: 'array',
                        description: 'Array of permission settings',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'resource_type', type: 'string', enum: ['location', 'node', 'realm', 'spell']),
                                new OA\Property(property: 'resource_id', type: 'integer'),
                                new OA\Property(property: 'permission_mode', type: 'string', enum: ['open', 'restricted']),
                                new OA\Property(property: 'default_error_message', type: 'string', nullable: true),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Permissions set successfully'),
            new OA\Response(response: 400, description: 'Invalid input'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function batchSetResourcePermissions(Request $request): Response
    {
        $admin = $request->get('user');
        $data = json_decode($request->getContent(), true);

        if ($data === null) {
            return ApiResponse::error('Invalid JSON', 'INVALID_JSON', 400);
        }

        $permissions = $data['permissions'] ?? [];
        if (!is_array($permissions)) {
            return ApiResponse::error('permissions must be an array', 'INVALID_TYPE', 400);
        }

        $success = ResourcePermission::batchSet($permissions);

        if (!$success) {
            return ApiResponse::error('Failed to set permissions', 'SET_FAILED', 400);
        }

        Activity::createActivity([
            'user_uuid' => $admin['uuid'] ?? null,
            'name' => 'billingresourcesnewservers_batch_set_resource_permissions',
            'context' => 'Batch set ' . count($permissions) . ' resource permissions',
            'ip_address' => CloudFlareRealIP::getRealIP(),
        ]);

        return ApiResponse::success(null, 'Permissions set successfully', 200);
    }

    #[OA\Delete(
        path: '/api/admin/billingresourcesnewservers/resource-permissions/{resourceType}/{resourceId}',
        summary: 'Delete resource permission',
        description: 'Delete permission setting for a resource (reverts to default open)',
        tags: ['Admin - Billing Resources New Servers - Resource Permissions'],
        parameters: [
            new OA\Parameter(name: 'resourceType', description: 'Type of resource', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['location', 'node', 'realm', 'spell'])),
            new OA\Parameter(name: 'resourceId', description: 'ID of the resource', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Permission deleted successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function deleteResourcePermission(Request $request, string $resourceType, int $resourceId): Response
    {
        $admin = $request->get('user');

        if (!in_array($resourceType, ['location', 'node', 'realm', 'spell'], true)) {
            return ApiResponse::error('Invalid resource type', 'INVALID_RESOURCE_TYPE', 400);
        }

        $success = ResourcePermission::delete($resourceType, $resourceId);

        if (!$success) {
            return ApiResponse::error('Failed to delete permission', 'DELETE_FAILED', 400);
        }

        Activity::createActivity([
            'user_uuid' => $admin['uuid'] ?? null,
            'name' => 'billingresourcesnewservers_delete_resource_permission',
            'context' => 'Deleted permission for ' . $resourceType . ' ' . $resourceId,
            'ip_address' => CloudFlareRealIP::getRealIP(),
        ]);

        return ApiResponse::success(null, 'Permission deleted successfully', 200);
    }
}
