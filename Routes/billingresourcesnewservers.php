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

use App\App;
use App\Permissions;
use App\Helpers\ApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouteCollection;
use App\Addons\billingresourcesnewservers\Controllers\Admin\GroupsController;
use App\Addons\billingresourcesnewservers\Controllers\Admin\UserPermissionsController;
use App\Addons\billingresourcesnewservers\Controllers\Admin\ResourcePermissionsController;
use App\Addons\billingresourcesnewservers\Controllers\Admin\SettingsController as AdminController;
use App\Addons\billingresourcesnewservers\Controllers\User\ServerCreationController as UserController;

return function (RouteCollection $routes): void {
    // User Routes (require authentication)
    // Get available options for server creation
    App::getInstance(true)->registerAuthRoute(
        $routes,
        'billingresourcesnewservers-user-options',
        '/api/user/billingresourcesnewservers/options',
        function (Request $request) {
            return (new UserController())->getOptions($request);
        },
        ['GET']
    );

    // Get spell details
    App::getInstance(true)->registerAuthRoute(
        $routes,
        'billingresourcesnewservers-user-spell-details',
        '/api/user/billingresourcesnewservers/spells/{id}',
        function (Request $request, array $args) {
            $id = $args['id'] ?? null;
            if (!$id || !is_numeric($id)) {
                return ApiResponse::error('Missing or invalid ID', 'INVALID_ID', 400);
            }

            return (new UserController())->getSpellDetails($request, (int) $id);
        },
        ['GET']
    );

    // Get available allocations for a node
    App::getInstance(true)->registerAuthRoute(
        $routes,
        'billingresourcesnewservers-user-allocations',
        '/api/user/billingresourcesnewservers/allocations',
        function (Request $request) {
            return (new UserController())->getAllocations($request);
        },
        ['GET']
    );

    // Create a new server
    App::getInstance(true)->registerAuthRoute(
        $routes,
        'billingresourcesnewservers-user-create-server',
        '/api/user/billingresourcesnewservers/servers',
        function (Request $request) {
            return (new UserController())->createServer($request);
        },
        ['POST']
    );

    // Admin Routes
    // Get plugin settings
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingresourcesnewservers-admin-settings',
        '/api/admin/billingresourcesnewservers/settings',
        function (Request $request) {
            return (new AdminController())->getSettings($request);
        },
        Permissions::ADMIN_USERS_VIEW,
        ['GET']
    );

    // Update plugin settings
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingresourcesnewservers-admin-settings-update',
        '/api/admin/billingresourcesnewservers/settings',
        function (Request $request) {
            return (new AdminController())->updateSettings($request);
        },
        Permissions::ADMIN_USERS_EDIT,
        ['PATCH', 'PUT']
    );

    // User Permissions Routes
    // Get user permissions
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingresourcesnewservers-admin-user-permissions',
        '/api/admin/billingresourcesnewservers/user-permissions/{userId}',
        function (Request $request, array $args) {
            return (new UserPermissionsController())->getUserPermissions($request, $args);
        },
        Permissions::ADMIN_USERS_VIEW,
        ['GET']
    );

    // Add user permission
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingresourcesnewservers-admin-user-permissions-add',
        '/api/admin/billingresourcesnewservers/user-permissions/{userId}',
        function (Request $request, array $args) {
            return (new UserPermissionsController())->addUserPermission($request, $args);
        },
        Permissions::ADMIN_USERS_EDIT,
        ['POST']
    );

    // Delete user permission
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingresourcesnewservers-admin-user-permissions-delete',
        '/api/admin/billingresourcesnewservers/user-permissions/{userId}/{permissionId}',
        function (Request $request, array $args) {
            return (new UserPermissionsController())->deleteUserPermission($request, $args);
        },
        Permissions::ADMIN_USERS_EDIT,
        ['DELETE']
    );

    // Groups Routes
    // Get all groups
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingresourcesnewservers-admin-groups-get-all',
        '/api/admin/billingresourcesnewservers/groups',
        function (Request $request) {
            return (new GroupsController())->getAllGroups($request);
        },
        Permissions::ADMIN_USERS_VIEW,
        ['GET']
    );

    // Create group
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingresourcesnewservers-admin-groups-create',
        '/api/admin/billingresourcesnewservers/groups',
        function (Request $request) {
            return (new GroupsController())->createGroup($request);
        },
        Permissions::ADMIN_USERS_EDIT,
        ['POST']
    );

    // Get single group
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingresourcesnewservers-admin-groups-get',
        '/api/admin/billingresourcesnewservers/groups/{groupId}',
        function (Request $request, array $args) {
            $groupId = $args['groupId'] ?? null;
            if (!$groupId || !is_numeric($groupId)) {
                return ApiResponse::error('Missing or invalid Group ID', 'INVALID_GROUP_ID', 400);
            }

            return (new GroupsController())->getGroup($request, (int) $groupId);
        },
        Permissions::ADMIN_USERS_VIEW,
        ['GET']
    );

    // Update group
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingresourcesnewservers-admin-groups-update',
        '/api/admin/billingresourcesnewservers/groups/{groupId}',
        function (Request $request, array $args) {
            $groupId = $args['groupId'] ?? null;
            if (!$groupId || !is_numeric($groupId)) {
                return ApiResponse::error('Missing or invalid Group ID', 'INVALID_GROUP_ID', 400);
            }

            return (new GroupsController())->updateGroup($request, (int) $groupId);
        },
        Permissions::ADMIN_USERS_EDIT,
        ['PATCH', 'PUT']
    );

    // Delete group
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingresourcesnewservers-admin-groups-delete',
        '/api/admin/billingresourcesnewservers/groups/{groupId}',
        function (Request $request, array $args) {
            $groupId = $args['groupId'] ?? null;
            if (!$groupId || !is_numeric($groupId)) {
                return ApiResponse::error('Missing or invalid Group ID', 'INVALID_GROUP_ID', 400);
            }

            return (new GroupsController())->deleteGroup($request, (int) $groupId);
        },
        Permissions::ADMIN_USERS_DELETE,
        ['DELETE']
    );

    // Get group permissions
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingresourcesnewservers-admin-groups-permissions-get',
        '/api/admin/billingresourcesnewservers/groups/{groupId}/permissions',
        function (Request $request, array $args) {
            $groupId = $args['groupId'] ?? null;
            if (!$groupId || !is_numeric($groupId)) {
                return ApiResponse::error('Missing or invalid Group ID', 'INVALID_GROUP_ID', 400);
            }

            return (new GroupsController())->getGroupPermissions($request, (int) $groupId);
        },
        Permissions::ADMIN_USERS_VIEW,
        ['GET']
    );

    // Add group permission
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingresourcesnewservers-admin-groups-permissions-add',
        '/api/admin/billingresourcesnewservers/groups/{groupId}/permissions',
        function (Request $request, array $args) {
            $groupId = $args['groupId'] ?? null;
            if (!$groupId || !is_numeric($groupId)) {
                return ApiResponse::error('Missing or invalid Group ID', 'INVALID_GROUP_ID', 400);
            }

            return (new GroupsController())->addGroupPermission($request, (int) $groupId);
        },
        Permissions::ADMIN_USERS_EDIT,
        ['POST']
    );

    // Update group permission
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingresourcesnewservers-admin-groups-permissions-update',
        '/api/admin/billingresourcesnewservers/groups/{groupId}/permissions/{resourceType}/{resourceId}',
        function (Request $request, array $args) {
            $groupId = $args['groupId'] ?? null;
            $resourceType = $args['resourceType'] ?? null;
            $resourceId = $args['resourceId'] ?? null;
            if (!$groupId || !is_numeric($groupId) || !$resourceType || !$resourceId || !is_numeric($resourceId)) {
                return ApiResponse::error('Missing or invalid parameters', 'INVALID_PARAMS', 400);
            }

            return (new GroupsController())->updateGroupPermission($request, (int) $groupId, $resourceType, (int) $resourceId);
        },
        Permissions::ADMIN_USERS_EDIT,
        ['PATCH', 'PUT']
    );

    // Delete group permission
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingresourcesnewservers-admin-groups-permissions-delete',
        '/api/admin/billingresourcesnewservers/groups/{groupId}/permissions/{resourceType}/{resourceId}',
        function (Request $request, array $args) {
            $groupId = $args['groupId'] ?? null;
            $resourceType = $args['resourceType'] ?? null;
            $resourceId = $args['resourceId'] ?? null;
            if (!$groupId || !is_numeric($groupId) || !$resourceType || !$resourceId || !is_numeric($resourceId)) {
                return ApiResponse::error('Missing or invalid parameters', 'INVALID_PARAMS', 400);
            }

            return (new GroupsController())->deleteGroupPermission($request, (int) $groupId, $resourceType, (int) $resourceId);
        },
        Permissions::ADMIN_USERS_DELETE,
        ['DELETE']
    );

    // Get group users
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingresourcesnewservers-admin-groups-users-get',
        '/api/admin/billingresourcesnewservers/groups/{groupId}/users',
        function (Request $request, array $args) {
            $groupId = $args['groupId'] ?? null;
            if (!$groupId || !is_numeric($groupId)) {
                return ApiResponse::error('Missing or invalid Group ID', 'INVALID_GROUP_ID', 400);
            }

            return (new GroupsController())->getGroupUsers($request, (int) $groupId);
        },
        Permissions::ADMIN_USERS_VIEW,
        ['GET']
    );

    // Assign user to group
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingresourcesnewservers-admin-groups-users-assign',
        '/api/admin/billingresourcesnewservers/groups/{groupId}/users/{userId}',
        function (Request $request, array $args) {
            $groupId = $args['groupId'] ?? null;
            $userId = $args['userId'] ?? null;
            if (!$groupId || !is_numeric($groupId) || !$userId || !is_numeric($userId)) {
                return ApiResponse::error('Missing or invalid Group ID or User ID', 'INVALID_ID', 400);
            }

            return (new GroupsController())->assignUserToGroup($request, (int) $groupId, (int) $userId);
        },
        Permissions::ADMIN_USERS_EDIT,
        ['POST']
    );

    // Remove user from group
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingresourcesnewservers-admin-groups-users-remove',
        '/api/admin/billingresourcesnewservers/groups/{groupId}/users/{userId}',
        function (Request $request, array $args) {
            $groupId = $args['groupId'] ?? null;
            $userId = $args['userId'] ?? null;
            if (!$groupId || !is_numeric($groupId) || !$userId || !is_numeric($userId)) {
                return ApiResponse::error('Missing or invalid Group ID or User ID', 'INVALID_ID', 400);
            }

            return (new GroupsController())->removeUserFromGroup($request, (int) $groupId, (int) $userId);
        },
        Permissions::ADMIN_USERS_DELETE,
        ['DELETE']
    );

    // Get user groups
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingresourcesnewservers-admin-users-groups-get',
        '/api/admin/billingresourcesnewservers/users/{userId}/groups',
        function (Request $request, array $args) {
            $userId = $args['userId'] ?? null;
            if (!$userId || !is_numeric($userId)) {
                return ApiResponse::error('Missing or invalid User ID', 'INVALID_USER_ID', 400);
            }

            return (new GroupsController())->getUserGroups($request, (int) $userId);
        },
        Permissions::ADMIN_USERS_VIEW,
        ['GET']
    );

    // Set groups for user
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingresourcesnewservers-admin-users-groups-set',
        '/api/admin/billingresourcesnewservers/users/{userId}/groups',
        function (Request $request, array $args) {
            $userId = $args['userId'] ?? null;
            if (!$userId || !is_numeric($userId)) {
                return ApiResponse::error('Missing or invalid User ID', 'INVALID_USER_ID', 400);
            }

            return (new GroupsController())->setUserGroups($request, (int) $userId);
        },
        Permissions::ADMIN_USERS_EDIT,
        ['POST']
    );

    // Resource Permissions Routes
    // Get resource permissions by type
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingresourcesnewservers-admin-resource-permissions-get',
        '/api/admin/billingresourcesnewservers/resource-permissions/{resourceType}',
        function (Request $request, array $args) {
            $resourceType = $args['resourceType'] ?? null;
            if (!$resourceType || !in_array($resourceType, ['location', 'node', 'realm', 'spell'], true)) {
                return ApiResponse::error('Invalid resource type', 'INVALID_RESOURCE_TYPE', 400);
            }

            return (new ResourcePermissionsController())->getResourcePermissions($request, $resourceType);
        },
        Permissions::ADMIN_USERS_VIEW,
        ['GET']
    );

    // Set resource permission
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingresourcesnewservers-admin-resource-permissions-set',
        '/api/admin/billingresourcesnewservers/resource-permissions',
        function (Request $request) {
            return (new ResourcePermissionsController())->setResourcePermission($request);
        },
        Permissions::ADMIN_USERS_EDIT,
        ['POST']
    );

    // Batch set resource permissions
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingresourcesnewservers-admin-resource-permissions-batch',
        '/api/admin/billingresourcesnewservers/resource-permissions/batch',
        function (Request $request) {
            return (new ResourcePermissionsController())->batchSetResourcePermissions($request);
        },
        Permissions::ADMIN_USERS_EDIT,
        ['POST']
    );

    // Delete resource permission
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingresourcesnewservers-admin-resource-permissions-delete',
        '/api/admin/billingresourcesnewservers/resource-permissions/{resourceType}/{resourceId}',
        function (Request $request, array $args) {
            $resourceType = $args['resourceType'] ?? null;
            $resourceId = $args['resourceId'] ?? null;
            if (!$resourceType || !in_array($resourceType, ['location', 'node', 'realm', 'spell'], true) || !$resourceId || !is_numeric($resourceId)) {
                return ApiResponse::error('Invalid resource type or ID', 'INVALID_PARAMS', 400);
            }

            return (new ResourcePermissionsController())->deleteResourcePermission($request, $resourceType, (int) $resourceId);
        },
        Permissions::ADMIN_USERS_DELETE,
        ['DELETE']
    );
};
