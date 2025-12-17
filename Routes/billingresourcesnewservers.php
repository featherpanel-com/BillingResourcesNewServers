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

use App\App;
use App\Permissions;
use App\Helpers\ApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouteCollection;
use App\Addons\billingresourcesnewservers\Controllers\User\ServerCreationController as UserController;
use App\Addons\billingresourcesnewservers\Controllers\Admin\SettingsController as AdminController;

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
        function (Request $request, int $id) {
            return (new UserController())->getSpellDetails($request, $id);
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
};

