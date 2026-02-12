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
use App\Addons\billingresourcesnewservers\Chat\Group;
use App\Addons\billingresourcesnewservers\Chat\UserGroup;
use App\Addons\billingresourcesnewservers\Chat\GroupPermission;

#[OA\Tag(name: 'Admin - Billing Resources New Servers - Groups', description: 'Manage groups/ranks for server creation permissions')]
class GroupsController
{
    #[OA\Get(
        path: '/api/admin/billingresourcesnewservers/groups',
        summary: 'Get all groups',
        description: 'Get all groups/ranks',
        tags: ['Admin - Billing Resources New Servers - Groups'],
        responses: [
            new OA\Response(response: 200, description: 'Groups retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function getAllGroups(Request $request): Response
    {
        $groups = Group::getAll();

        return ApiResponse::success($groups, 'Groups retrieved successfully', 200);
    }

    #[OA\Post(
        path: '/api/admin/billingresourcesnewservers/groups',
        summary: 'Create a new group',
        description: 'Create a new group/rank',
        tags: ['Admin - Billing Resources New Servers - Groups'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', description: 'Group name'),
                    new OA\Property(property: 'description', type: 'string', nullable: true, description: 'Group description'),
                    new OA\Property(property: 'color', type: 'string', nullable: true, description: 'Group color (hex)', example: '#3B82F6'),
                    new OA\Property(property: 'priority', type: 'integer', nullable: true, description: 'Priority (higher = more important)', example: 0),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Group created successfully'),
            new OA\Response(response: 400, description: 'Invalid input'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function createGroup(Request $request): Response
    {
        $admin = $request->get('user');
        $data = json_decode($request->getContent(), true);

        if ($data === null) {
            return ApiResponse::error('Invalid JSON', 'INVALID_JSON', 400);
        }

        $name = $data['name'] ?? null;
        if (!$name || trim($name) === '') {
            return ApiResponse::error('Group name is required', 'MISSING_NAME', 400);
        }

        $groupId = Group::create(
            trim($name),
            $data['description'] ?? null,
            $data['color'] ?? null,
            (int) ($data['priority'] ?? 0)
        );

        if ($groupId === false) {
            return ApiResponse::error('Failed to create group. Name may already exist.', 'CREATE_FAILED', 400);
        }

        Activity::createActivity([
            'user_uuid' => $admin['uuid'] ?? null,
            'name' => 'billingresourcesnewservers_create_group',
            'context' => 'Created group: ' . $name,
            'ip_address' => CloudFlareRealIP::getRealIP(),
        ]);

        $group = Group::getById($groupId);

        return ApiResponse::success($group, 'Group created successfully', 201);
    }

    #[OA\Patch(
        path: '/api/admin/billingresourcesnewservers/groups/{groupId}',
        summary: 'Update a group',
        description: 'Update a group/rank',
        tags: ['Admin - Billing Resources New Servers - Groups'],
        parameters: [
            new OA\Parameter(name: 'groupId', description: 'ID of the group', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', nullable: true, description: 'Group name'),
                    new OA\Property(property: 'description', type: 'string', nullable: true, description: 'Group description'),
                    new OA\Property(property: 'color', type: 'string', nullable: true, description: 'Group color (hex)'),
                    new OA\Property(property: 'priority', type: 'integer', nullable: true, description: 'Priority'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Group updated successfully'),
            new OA\Response(response: 400, description: 'Invalid input'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Group not found'),
        ]
    )]
    public function updateGroup(Request $request, int $groupId): Response
    {
        $admin = $request->get('user');
        $data = json_decode($request->getContent(), true);

        if ($data === null) {
            return ApiResponse::error('Invalid JSON', 'INVALID_JSON', 400);
        }

        $group = Group::getById($groupId);
        if (!$group) {
            return ApiResponse::error('Group not found', 'GROUP_NOT_FOUND', 404);
        }

        $updateData = [];
        if (isset($data['name'])) {
            $updateData['name'] = trim($data['name']);
        }
        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }
        if (isset($data['color'])) {
            $updateData['color'] = $data['color'];
        }
        if (isset($data['priority'])) {
            $updateData['priority'] = (int) $data['priority'];
        }

        if (empty($updateData)) {
            return ApiResponse::error('No fields to update', 'NO_FIELDS', 400);
        }

        $success = Group::update($groupId, $updateData);
        if (!$success) {
            return ApiResponse::error('Failed to update group', 'UPDATE_FAILED', 400);
        }

        Activity::createActivity([
            'user_uuid' => $admin['uuid'] ?? null,
            'name' => 'billingresourcesnewservers_update_group',
            'context' => 'Updated group ID ' . $groupId,
            'ip_address' => CloudFlareRealIP::getRealIP(),
        ]);

        $updatedGroup = Group::getById($groupId);

        return ApiResponse::success($updatedGroup, 'Group updated successfully', 200);
    }

    #[OA\Delete(
        path: '/api/admin/billingresourcesnewservers/groups/{groupId}',
        summary: 'Delete a group',
        description: 'Delete a group/rank',
        tags: ['Admin - Billing Resources New Servers - Groups'],
        parameters: [
            new OA\Parameter(name: 'groupId', description: 'ID of the group', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Group deleted successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Group not found'),
        ]
    )]
    public function deleteGroup(Request $request, int $groupId): Response
    {
        $admin = $request->get('user');

        $group = Group::getById($groupId);
        if (!$group) {
            return ApiResponse::error('Group not found', 'GROUP_NOT_FOUND', 404);
        }

        $success = Group::delete($groupId);
        if (!$success) {
            return ApiResponse::error('Failed to delete group', 'DELETE_FAILED', 400);
        }

        Activity::createActivity([
            'user_uuid' => $admin['uuid'] ?? null,
            'name' => 'billingresourcesnewservers_delete_group',
            'context' => 'Deleted group: ' . $group['name'],
            'ip_address' => CloudFlareRealIP::getRealIP(),
        ]);

        return ApiResponse::success(null, 'Group deleted successfully', 200);
    }

    #[OA\Get(
        path: '/api/admin/billingresourcesnewservers/groups/{groupId}',
        summary: 'Get a single group',
        description: 'Get a group with its permissions',
        tags: ['Admin - Billing Resources New Servers - Groups'],
        parameters: [
            new OA\Parameter(name: 'groupId', description: 'ID of the group', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Group retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Group not found'),
        ]
    )]
    public function getGroup(Request $request, int $groupId): Response
    {
        $group = Group::getById($groupId);
        if (!$group) {
            return ApiResponse::error('Group not found', 'GROUP_NOT_FOUND', 404);
        }

        $permissions = GroupPermission::getByGroupId($groupId);

        // Organize permissions by type
        $organizedPermissions = [
            'locations' => [],
            'nodes' => [],
            'realms' => [],
            'spells' => [],
        ];

        foreach ($permissions as $permission) {
            $type = $permission['resource_type'];
            if (isset($organizedPermissions[$type . 's'])) {
                $organizedPermissions[$type . 's'][] = $permission;
            }
        }

        return ApiResponse::success([
            'id' => $group['id'],
            'name' => $group['name'],
            'description' => $group['description'] ?? null,
            'color' => $group['color'] ?? null,
            'priority' => (int) ($group['priority'] ?? 0),
            'created_at' => $group['created_at'] ?? null,
            'updated_at' => $group['updated_at'] ?? null,
            'permissions' => $organizedPermissions,
        ], 'Group retrieved successfully', 200);
    }

    #[OA\Get(
        path: '/api/admin/billingresourcesnewservers/groups/{groupId}/permissions',
        summary: 'Get group permissions',
        description: 'Get all permissions for a group',
        tags: ['Admin - Billing Resources New Servers - Groups'],
        parameters: [
            new OA\Parameter(name: 'groupId', description: 'ID of the group', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Permissions retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Group not found'),
        ]
    )]
    public function getGroupPermissions(Request $request, int $groupId): Response
    {
        $group = Group::getById($groupId);
        if (!$group) {
            return ApiResponse::error('Group not found', 'GROUP_NOT_FOUND', 404);
        }

        $permissions = GroupPermission::getByGroupId($groupId);

        return ApiResponse::success([
            'group' => $group,
            'permissions' => $permissions,
        ], 'Permissions retrieved successfully', 200);
    }

    #[OA\Post(
        path: '/api/admin/billingresourcesnewservers/groups/{groupId}/permissions',
        summary: 'Add group permission',
        description: 'Add a permission to a group',
        tags: ['Admin - Billing Resources New Servers - Groups'],
        parameters: [
            new OA\Parameter(name: 'groupId', description: 'ID of the group', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'resource_type', type: 'string', enum: ['location', 'node', 'realm', 'spell'], description: 'Type of resource'),
                    new OA\Property(property: 'resource_id', type: 'integer', description: 'ID of the resource'),
                    new OA\Property(property: 'custom_error_message', type: 'string', nullable: true, description: 'Custom error message'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Permission added successfully'),
            new OA\Response(response: 400, description: 'Invalid input or resource not found'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Group not found'),
        ]
    )]
    public function addGroupPermission(Request $request, int $groupId): Response
    {
        $admin = $request->get('user');
        $data = json_decode($request->getContent(), true);

        if ($data === null) {
            return ApiResponse::error('Invalid JSON', 'INVALID_JSON', 400);
        }

        $group = Group::getById($groupId);
        if (!$group) {
            return ApiResponse::error('Group not found', 'GROUP_NOT_FOUND', 404);
        }

        $resourceType = $data['resource_type'] ?? null;
        $resourceId = $data['resource_id'] ?? null;
        $customErrorMessage = $data['custom_error_message'] ?? null;

        if (!in_array($resourceType, ['location', 'node', 'realm', 'spell'], true) || !is_numeric($resourceId)) {
            return ApiResponse::error('Invalid resource_type or resource_id', 'INVALID_INPUT', 400);
        }

        // Validate resource exists
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
            return ApiResponse::error('Resource not found', 'RESOURCE_NOT_FOUND', 400);
        }

        $success = GroupPermission::createOrUpdate($groupId, $resourceType, (int) $resourceId, $customErrorMessage);

        if (!$success) {
            return ApiResponse::error('Failed to add permission', 'ADD_FAILED', 400);
        }

        Activity::createActivity([
            'user_uuid' => $admin['uuid'] ?? null,
            'name' => 'billingresourcesnewservers_add_group_permission',
            'context' => 'Added ' . $resourceType . ' permission to group ' . $group['name'],
            'ip_address' => CloudFlareRealIP::getRealIP(),
        ]);

        return ApiResponse::success(null, 'Permission added successfully', 201);
    }

    #[OA\Patch(
        path: '/api/admin/billingresourcesnewservers/groups/{groupId}/permissions/{resourceType}/{resourceId}',
        summary: 'Update group permission',
        description: 'Update a permission for a group (e.g., custom error message)',
        tags: ['Admin - Billing Resources New Servers - Groups'],
        parameters: [
            new OA\Parameter(name: 'groupId', description: 'ID of the group', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'resourceType', description: 'Type of resource', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['location', 'node', 'realm', 'spell'])),
            new OA\Parameter(name: 'resourceId', description: 'ID of the resource', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'custom_error_message', type: 'string', nullable: true, description: 'Custom error message'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Permission updated successfully'),
            new OA\Response(response: 400, description: 'Invalid input'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Group or permission not found'),
        ]
    )]
    #[OA\Put(
        path: '/api/admin/billingresourcesnewservers/groups/{groupId}/permissions/{resourceType}/{resourceId}',
        summary: 'Update group permission',
        description: 'Update a permission for a group (e.g., custom error message)',
        tags: ['Admin - Billing Resources New Servers - Groups'],
        parameters: [
            new OA\Parameter(name: 'groupId', description: 'ID of the group', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'resourceType', description: 'Type of resource', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['location', 'node', 'realm', 'spell'])),
            new OA\Parameter(name: 'resourceId', description: 'ID of the resource', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'custom_error_message', type: 'string', nullable: true, description: 'Custom error message'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Permission updated successfully'),
            new OA\Response(response: 400, description: 'Invalid input'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Group or permission not found'),
        ]
    )]
    public function updateGroupPermission(Request $request, int $groupId, string $resourceType, int $resourceId): Response
    {
        $admin = $request->get('user');
        $data = json_decode($request->getContent(), true);

        if ($data === null) {
            return ApiResponse::error('Invalid JSON', 'INVALID_JSON', 400);
        }

        $group = Group::getById($groupId);
        if (!$group) {
            return ApiResponse::error('Group not found', 'GROUP_NOT_FOUND', 404);
        }

        if (!in_array($resourceType, ['location', 'node', 'realm', 'spell'], true)) {
            return ApiResponse::error('Invalid resource type', 'INVALID_RESOURCE_TYPE', 400);
        }

        // Check if permission exists
        $permission = GroupPermission::getGroupPermission($groupId, $resourceType, $resourceId);
        if (!$permission) {
            return ApiResponse::error('Permission not found', 'PERMISSION_NOT_FOUND', 404);
        }

        $customErrorMessage = $data['custom_error_message'] ?? null;

        $success = GroupPermission::createOrUpdate($groupId, $resourceType, $resourceId, $customErrorMessage);

        if (!$success) {
            return ApiResponse::error('Failed to update permission', 'UPDATE_FAILED', 400);
        }

        Activity::createActivity([
            'user_uuid' => $admin['uuid'] ?? null,
            'name' => 'billingresourcesnewservers_update_group_permission',
            'context' => 'Updated ' . $resourceType . ' permission for group ' . $group['name'],
            'ip_address' => CloudFlareRealIP::getRealIP(),
        ]);

        return ApiResponse::success(null, 'Permission updated successfully', 200);
    }

    #[OA\Delete(
        path: '/api/admin/billingresourcesnewservers/groups/{groupId}/permissions/{resourceType}/{resourceId}',
        summary: 'Delete group permission',
        description: 'Delete a permission from a group',
        tags: ['Admin - Billing Resources New Servers - Groups'],
        parameters: [
            new OA\Parameter(name: 'groupId', description: 'ID of the group', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'resourceType', description: 'Type of resource', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['location', 'node', 'realm', 'spell'])),
            new OA\Parameter(name: 'resourceId', description: 'ID of the resource', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Permission deleted successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Group or permission not found'),
        ]
    )]
    public function deleteGroupPermission(Request $request, int $groupId, string $resourceType, int $resourceId): Response
    {
        $admin = $request->get('user');

        $group = Group::getById($groupId);
        if (!$group) {
            return ApiResponse::error('Group not found', 'GROUP_NOT_FOUND', 404);
        }

        if (!in_array($resourceType, ['location', 'node', 'realm', 'spell'], true)) {
            return ApiResponse::error('Invalid resource type', 'INVALID_RESOURCE_TYPE', 400);
        }

        $success = GroupPermission::delete($groupId, $resourceType, $resourceId);

        if (!$success) {
            return ApiResponse::error('Permission not found or failed to delete', 'DELETE_FAILED', 404);
        }

        Activity::createActivity([
            'user_uuid' => $admin['uuid'] ?? null,
            'name' => 'billingresourcesnewservers_delete_group_permission',
            'context' => 'Deleted ' . $resourceType . ' permission from group ' . $group['name'],
            'ip_address' => CloudFlareRealIP::getRealIP(),
        ]);

        return ApiResponse::success(null, 'Permission deleted successfully', 200);
    }

    #[OA\Get(
        path: '/api/admin/billingresourcesnewservers/groups/{groupId}/users',
        summary: 'Get users in group',
        description: 'Get all users assigned to a group',
        tags: ['Admin - Billing Resources New Servers - Groups'],
        parameters: [
            new OA\Parameter(name: 'groupId', description: 'ID of the group', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Users retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Group not found'),
        ]
    )]
    public function getGroupUsers(Request $request, int $groupId): Response
    {
        $group = Group::getById($groupId);
        if (!$group) {
            return ApiResponse::error('Group not found', 'GROUP_NOT_FOUND', 404);
        }

        $userIds = UserGroup::getUserIdsByGroupId($groupId);
        $users = [];
        foreach ($userIds as $userId) {
            $user = User::getUserById($userId);
            if ($user) {
                $users[] = [
                    'id' => $user['id'],
                    'uuid' => $user['uuid'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                ];
            }
        }

        return ApiResponse::success([
            'group' => $group,
            'users' => $users,
        ], 'Users retrieved successfully', 200);
    }

    #[OA\Post(
        path: '/api/admin/billingresourcesnewservers/groups/{groupId}/users/{userId}',
        summary: 'Assign user to group',
        description: 'Assign a user to a group',
        tags: ['Admin - Billing Resources New Servers - Groups'],
        parameters: [
            new OA\Parameter(name: 'groupId', description: 'ID of the group', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'userId', description: 'ID of the user', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'User assigned successfully'),
            new OA\Response(response: 400, description: 'Invalid input'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Group or user not found'),
        ]
    )]
    public function assignUserToGroup(Request $request, int $groupId, int $userId): Response
    {
        $admin = $request->get('user');

        $group = Group::getById($groupId);
        if (!$group) {
            return ApiResponse::error('Group not found', 'GROUP_NOT_FOUND', 404);
        }

        $user = User::getUserById($userId);
        if (!$user) {
            return ApiResponse::error('User not found', 'USER_NOT_FOUND', 404);
        }

        $success = UserGroup::assign($userId, $groupId);
        if (!$success) {
            return ApiResponse::error('Failed to assign user to group', 'ASSIGN_FAILED', 400);
        }

        Activity::createActivity([
            'user_uuid' => $admin['uuid'] ?? null,
            'name' => 'billingresourcesnewservers_assign_user_group',
            'context' => 'Assigned user ' . $user['username'] . ' to group ' . $group['name'],
            'ip_address' => CloudFlareRealIP::getRealIP(),
        ]);

        return ApiResponse::success(null, 'User assigned successfully', 200);
    }

    #[OA\Delete(
        path: '/api/admin/billingresourcesnewservers/groups/{groupId}/users/{userId}',
        summary: 'Remove user from group',
        description: 'Remove a user from a group',
        tags: ['Admin - Billing Resources New Servers - Groups'],
        parameters: [
            new OA\Parameter(name: 'groupId', description: 'ID of the group', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'userId', description: 'ID of the user', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'User removed successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Group or user not found'),
        ]
    )]
    public function removeUserFromGroup(Request $request, int $groupId, int $userId): Response
    {
        $admin = $request->get('user');

        $group = Group::getById($groupId);
        if (!$group) {
            return ApiResponse::error('Group not found', 'GROUP_NOT_FOUND', 404);
        }

        $user = User::getUserById($userId);
        if (!$user) {
            return ApiResponse::error('User not found', 'USER_NOT_FOUND', 404);
        }

        $success = UserGroup::remove($userId, $groupId);
        if (!$success) {
            return ApiResponse::error('Failed to remove user from group', 'REMOVE_FAILED', 400);
        }

        Activity::createActivity([
            'user_uuid' => $admin['uuid'] ?? null,
            'name' => 'billingresourcesnewservers_remove_user_group',
            'context' => 'Removed user ' . $user['username'] . ' from group ' . $group['name'],
            'ip_address' => CloudFlareRealIP::getRealIP(),
        ]);

        return ApiResponse::success(null, 'User removed successfully', 200);
    }

    #[OA\Post(
        path: '/api/admin/billingresourcesnewservers/users/{userId}/groups',
        summary: 'Set groups for user',
        description: 'Set all groups for a user (replaces existing groups)',
        tags: ['Admin - Billing Resources New Servers - Groups'],
        parameters: [
            new OA\Parameter(name: 'userId', description: 'ID of the user', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'group_ids',
                        type: 'array',
                        description: 'Array of group IDs',
                        items: new OA\Items(type: 'integer')
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Groups set successfully'),
            new OA\Response(response: 400, description: 'Invalid input'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    public function setUserGroups(Request $request, int $userId): Response
    {
        $admin = $request->get('user');
        $data = json_decode($request->getContent(), true);

        if ($data === null) {
            return ApiResponse::error('Invalid JSON', 'INVALID_JSON', 400);
        }

        $user = User::getUserById($userId);
        if (!$user) {
            return ApiResponse::error('User not found', 'USER_NOT_FOUND', 404);
        }

        $groupIds = $data['group_ids'] ?? [];
        if (!is_array($groupIds)) {
            return ApiResponse::error('group_ids must be an array', 'INVALID_TYPE', 400);
        }

        // Validate all groups exist
        foreach ($groupIds as $groupId) {
            if (!Group::getById((int) $groupId)) {
                return ApiResponse::error('Group not found: ' . $groupId, 'GROUP_NOT_FOUND', 404);
            }
        }

        $success = UserGroup::setGroupsForUser($userId, array_map('intval', $groupIds));
        if (!$success) {
            return ApiResponse::error('Failed to set groups for user', 'SET_FAILED', 400);
        }

        Activity::createActivity([
            'user_uuid' => $admin['uuid'] ?? null,
            'name' => 'billingresourcesnewservers_set_user_groups',
            'context' => 'Set groups for user ' . $user['username'],
            'ip_address' => CloudFlareRealIP::getRealIP(),
        ]);

        return ApiResponse::success(null, 'Groups set successfully', 200);
    }
}
