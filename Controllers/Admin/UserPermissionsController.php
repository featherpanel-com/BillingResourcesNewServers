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

namespace App\Addons\billingresourcesnewservers\Controllers\Admin;

use App\Chat\Node;
use App\Chat\User;
use App\Chat\Realm;
use App\Chat\Spell;
use App\Chat\Activity;
use App\Chat\Location;
use App\Helpers\ApiResponse;
use OpenApi\Attributes as OA;
use App\CloudFlare\CloudFlareRealIP;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Addons\billingresourcesnewservers\Chat\UserPermission;

#[OA\Tag(name: 'Admin - Billing Resources New Servers', description: 'User permissions management for server creation')]
class UserPermissionsController
{
    #[OA\Get(
        path: '/api/admin/billingresourcesnewservers/user-permissions/{userId}',
        summary: 'Get user permissions',
        description: 'Get all permissions for a specific user',
        tags: ['Admin - Billing Resources New Servers'],
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Permissions retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    public function getUserPermissions(Request $request, array $args): Response
    {
        $userId = $args['userId'] ?? null;
        if (!$userId || !is_numeric($userId)) {
            return ApiResponse::error('Missing or invalid user ID', 'INVALID_USER_ID', 400);
        }

        $userId = (int) $userId;

        // Verify user exists
        $user = User::getUserById($userId);
        if (!$user) {
            return ApiResponse::error('User not found', 'USER_NOT_FOUND', 404);
        }

        $permissions = UserPermission::getByUserId($userId);

        // Group permissions by type
        $grouped = [
            'locations' => [],
            'nodes' => [],
            'realms' => [],
            'spells' => [],
        ];

        foreach ($permissions as $perm) {
            $type = $perm['resource_type'];
            if (isset($grouped[$type . 's'])) {
                $grouped[$type . 's'][] = [
                    'id' => (int) $perm['id'],
                    'resource_id' => (int) $perm['resource_id'],
                    'custom_error_message' => $perm['custom_error_message'],
                ];
            }
        }

        return ApiResponse::success([
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
            ],
            'permissions' => $grouped,
        ], 'User permissions retrieved successfully', 200);
    }

    #[OA\Post(
        path: '/api/admin/billingresourcesnewservers/user-permissions/{userId}',
        summary: 'Add user permission',
        description: 'Add a permission for a user to access a specific resource',
        tags: ['Admin - Billing Resources New Servers'],
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'resource_type', type: 'string', enum: ['location', 'node', 'realm', 'spell'], description: 'Resource type'),
                    new OA\Property(property: 'resource_id', type: 'integer', description: 'Resource ID'),
                    new OA\Property(property: 'custom_error_message', type: 'string', nullable: true, description: 'Custom error message (optional)'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Permission added successfully'),
            new OA\Response(response: 400, description: 'Invalid input'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'User or resource not found'),
        ]
    )]
    public function addUserPermission(Request $request, array $args): Response
    {
        $admin = $request->get('user');
        $userId = $args['userId'] ?? null;
        if (!$userId || !is_numeric($userId)) {
            return ApiResponse::error('Missing or invalid user ID', 'INVALID_USER_ID', 400);
        }

        $userId = (int) $userId;

        // Verify user exists
        $user = User::getUserById($userId);
        if (!$user) {
            return ApiResponse::error('User not found', 'USER_NOT_FOUND', 404);
        }

        $data = json_decode($request->getContent(), true);
        if ($data === null) {
            return ApiResponse::error('Invalid JSON', 'INVALID_JSON', 400);
        }

        $resourceType = $data['resource_type'] ?? null;
        $resourceId = $data['resource_id'] ?? null;
        $customErrorMessage = $data['custom_error_message'] ?? null;

        if (!$resourceType || !in_array($resourceType, ['location', 'node', 'realm', 'spell'], true)) {
            return ApiResponse::error('Invalid resource_type. Must be: location, node, realm, or spell', 'INVALID_RESOURCE_TYPE', 400);
        }

        if (!$resourceId || !is_numeric($resourceId)) {
            return ApiResponse::error('Invalid resource_id', 'INVALID_RESOURCE_ID', 400);
        }

        $resourceId = (int) $resourceId;

        // Verify resource exists
        $resourceExists = false;
        switch ($resourceType) {
            case 'location':
                $resourceExists = Location::getById($resourceId) !== null;
                break;
            case 'node':
                $resourceExists = Node::getNodeById($resourceId) !== null;
                break;
            case 'realm':
                $resourceExists = Realm::getById($resourceId) !== null;
                break;
            case 'spell':
                $resourceExists = Spell::getSpellById($resourceId) !== null;
                break;
        }

        if (!$resourceExists) {
            return ApiResponse::error('Resource not found', 'RESOURCE_NOT_FOUND', 404);
        }

        // Create or update permission
        $success = UserPermission::createOrUpdate($userId, $resourceType, $resourceId, $customErrorMessage);
        if (!$success) {
            return ApiResponse::error('Failed to add permission', 'ADD_PERMISSION_FAILED', 500);
        }

        // Log activity
        Activity::createActivity([
            'user_uuid' => $admin['uuid'] ?? null,
            'name' => 'billingresourcesnewservers_add_user_permission',
            'context' => "Added {$resourceType} permission (ID: {$resourceId}) for user {$user['username']}",
            'ip_address' => CloudFlareRealIP::getRealIP(),
        ]);

        return ApiResponse::success([
            'message' => 'Permission added successfully',
        ], 'Permission added successfully', 200);
    }

    #[OA\Delete(
        path: '/api/admin/billingresourcesnewservers/user-permissions/{userId}/{permissionId}',
        summary: 'Delete user permission',
        description: 'Remove a permission for a user',
        tags: ['Admin - Billing Resources New Servers'],
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'permissionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Permission deleted successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Permission not found'),
        ]
    )]
    public function deleteUserPermission(Request $request, array $args): Response
    {
        $admin = $request->get('user');
        $userId = $args['userId'] ?? null;
        $permissionId = $args['permissionId'] ?? null;

        if (!$userId || !is_numeric($userId)) {
            return ApiResponse::error('Missing or invalid user ID', 'INVALID_USER_ID', 400);
        }

        if (!$permissionId || !is_numeric($permissionId)) {
            return ApiResponse::error('Missing or invalid permission ID', 'INVALID_PERMISSION_ID', 400);
        }

        $userId = (int) $userId;
        $permissionId = (int) $permissionId;

        // Get permission to verify it belongs to the user
        $permissions = UserPermission::getByUserId($userId);
        $permission = null;
        foreach ($permissions as $perm) {
            if ((int) $perm['id'] === $permissionId) {
                $permission = $perm;
                break;
            }
        }

        if (!$permission) {
            return ApiResponse::error('Permission not found', 'PERMISSION_NOT_FOUND', 404);
        }

        // Delete permission
        $success = UserPermission::delete($userId, $permission['resource_type'], $permission['resource_id']);
        if (!$success) {
            return ApiResponse::error('Failed to delete permission', 'DELETE_PERMISSION_FAILED', 500);
        }

        // Log activity
        $user = User::getUserById($userId);
        Activity::createActivity([
            'user_uuid' => $admin['uuid'] ?? null,
            'name' => 'billingresourcesnewservers_delete_user_permission',
            'context' => "Deleted {$permission['resource_type']} permission (ID: {$permission['resource_id']}) for user " . ($user['username'] ?? 'unknown'),
            'ip_address' => CloudFlareRealIP::getRealIP(),
        ]);

        return ApiResponse::success([
            'message' => 'Permission deleted successfully',
        ], 'Permission deleted successfully', 200);
    }
}
