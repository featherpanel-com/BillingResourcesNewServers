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

namespace App\Addons\billingresourcesnewservers\Controllers\User;

use App\App;
use App\Chat\Location;
use App\Chat\Node;
use App\Chat\Realm;
use App\Chat\Spell;
use App\Chat\Allocation;
use App\Chat\Server;
use App\Chat\SpellVariable;
use App\Chat\ServerVariable;
use App\Services\Wings\Wings;
use App\Helpers\ApiResponse;
use App\Helpers\UUIDUtils;
use App\Addons\billingresourcesnewservers\Helpers\SettingsHelper;
use App\Addons\billingresourcesnewservers\Helpers\ServerCreationHelper;
use App\Addons\billingresources\Helpers\ResourcesHelper;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[OA\Tag(name: 'User - Billing Resources New Servers', description: 'User server creation endpoints')]
class ServerCreationController
{
    #[OA\Get(
        path: '/api/user/billingresourcesnewservers/options',
        summary: 'Get available options for server creation',
        description: 'Get filtered locations, nodes, realms, and spells based on plugin settings',
        tags: ['User - Billing Resources New Servers'],
        responses: [
            new OA\Response(response: 200, description: 'Options retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function getOptions(Request $request): Response
    {
        // Check if user creation is enabled
        if (!SettingsHelper::isUserCreationEnabled()) {
            return ApiResponse::error('User server creation is currently disabled', 'USER_CREATION_DISABLED', 403);
        }

        try {
            // Get all locations
            $allLocations = Location::getAll(null, 1000, 0);
            $locations = ServerCreationHelper::filterLocations($allLocations);

            // Get all nodes
            $allNodes = Node::getAllNodes();
            $nodes = ServerCreationHelper::filterNodes($allNodes);

            // Get all realms
            $allRealms = Realm::getAll(null, 1000, 0);
            $realms = ServerCreationHelper::filterRealms($allRealms);

            // Get all spells
            $allSpells = Spell::getAllSpells();
            $spells = ServerCreationHelper::filterSpells($allSpells);

            // Get user's available resources
            $user = $request->get('user');
            $userId = (int) $user['id'];
            $availableResources = ResourcesHelper::calculateAvailableResources($userId);

            return ApiResponse::success([
                'locations' => array_values($locations),
                'nodes' => array_values($nodes),
                'realms' => array_values($realms),
                'spells' => array_values($spells),
                'available_resources' => $availableResources,
            ], 'Options retrieved successfully', 200);
        } catch (\Exception $e) {
            App::getInstance(true)->getLogger()->error('Failed to get server creation options: ' . $e->getMessage());
            
            return ApiResponse::error('Failed to retrieve options: ' . $e->getMessage(), 'GET_OPTIONS_FAILED', 500);
        }
    }

    #[OA\Get(
        path: '/api/user/billingresourcesnewservers/spells/{id}',
        summary: 'Get spell details',
        description: 'Get spell details including docker images and startup command for user server creation',
        tags: ['User - Billing Resources New Servers'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Spell details retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Spell not found or not allowed'),
        ]
    )]
    public function getSpellDetails(Request $request, int $id): Response
    {
        // Check if user creation is enabled
        if (!SettingsHelper::isUserCreationEnabled()) {
            return ApiResponse::error('User server creation is currently disabled', 'USER_CREATION_DISABLED', 403);
        }

        try {
            $spell = Spell::getSpellById($id);
            if (!$spell) {
                return ApiResponse::error('Spell not found', 'SPELL_NOT_FOUND', 404);
            }

            // Check if spell is allowed
            if (!SettingsHelper::isSpellAllowed($id)) {
                return ApiResponse::error('This spell is not available for user server creation', 'SPELL_NOT_ALLOWED', 403);
            }

            // Return only necessary fields (no sensitive data)
            return ApiResponse::success([
                'spell' => [
                    'id' => $spell['id'],
                    'name' => $spell['name'],
                    'description' => $spell['description'] ?? null,
                    'startup' => $spell['startup'] ?? null,
                    'docker_images' => $spell['docker_images'] ?? null,
                    'docker_image' => $spell['docker_image'] ?? null,
                ],
            ], 'Spell details retrieved successfully', 200);
        } catch (\Exception $e) {
            App::getInstance(true)->getLogger()->error('Failed to get spell details: ' . $e->getMessage());
            
            return ApiResponse::error('Failed to retrieve spell details: ' . $e->getMessage(), 'GET_SPELL_DETAILS_FAILED', 500);
        }
    }

    #[OA\Get(
        path: '/api/user/billingresourcesnewservers/allocations',
        summary: 'Get available allocations for a node',
        description: 'Get available allocations for a specific node',
        tags: ['User - Billing Resources New Servers'],
        parameters: [
            new OA\Parameter(name: 'node_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Allocations retrieved successfully'),
            new OA\Response(response: 400, description: 'Invalid node_id'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function getAllocations(Request $request): Response
    {
        // Check if user creation is enabled
        if (!SettingsHelper::isUserCreationEnabled()) {
            return ApiResponse::error('User server creation is currently disabled', 'USER_CREATION_DISABLED', 403);
        }

        $nodeId = $request->query->get('node_id');
        if (!$nodeId) {
            return ApiResponse::error('node_id parameter is required', 'MISSING_NODE_ID', 400);
        }

        $nodeId = (int) $nodeId;

        // Validate node exists and is allowed
        $node = Node::getNodeById($nodeId);
        if (!$node) {
            return ApiResponse::error('Node not found', 'NODE_NOT_FOUND', 404);
        }

        if (!SettingsHelper::isNodeAllowed($nodeId)) {
            return ApiResponse::error('This node is not available for user server creation', 'NODE_NOT_ALLOWED', 403);
        }

        try {
            // Get unused allocations for this node
            $allocations = Allocation::getAll(null, $nodeId, null, 1000, 0, true);

            return ApiResponse::success([
                'allocations' => $allocations,
            ], 'Allocations retrieved successfully', 200);
        } catch (\Exception $e) {
            App::getInstance(true)->getLogger()->error('Failed to get allocations: ' . $e->getMessage());
            
            return ApiResponse::error('Failed to retrieve allocations: ' . $e->getMessage(), 'GET_ALLOCATIONS_FAILED', 500);
        }
    }

    #[OA\Post(
        path: '/api/user/billingresourcesnewservers/servers',
        summary: 'Create a new server',
        description: 'Create a new server using user resources',
        tags: ['User - Billing Resources New Servers'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', description: 'Server name'),
                    new OA\Property(property: 'node_id', type: 'integer', description: 'Node ID'),
                    new OA\Property(property: 'realms_id', type: 'integer', description: 'Realm ID'),
                    new OA\Property(property: 'spell_id', type: 'integer', description: 'Spell ID'),
                    new OA\Property(property: 'allocation_id', type: 'integer', description: 'Allocation ID'),
                    new OA\Property(property: 'memory', type: 'integer', description: 'Memory in MB'),
                    new OA\Property(property: 'cpu', type: 'integer', description: 'CPU limit in percentage'),
                    new OA\Property(property: 'disk', type: 'integer', description: 'Disk space in MB'),
                    new OA\Property(property: 'swap', type: 'integer', description: 'Swap space in MB (optional)'),
                    new OA\Property(property: 'io', type: 'integer', description: 'IO limit (optional, default: 500)'),
                    new OA\Property(property: 'description', type: 'string', description: 'Server description (optional)'),
                    new OA\Property(property: 'startup', type: 'string', description: 'Startup command'),
                    new OA\Property(property: 'image', type: 'string', description: 'Docker image'),
                    new OA\Property(property: 'database_limit', type: 'integer', description: 'Database limit (optional, default: 0)'),
                    new OA\Property(property: 'allocation_limit', type: 'integer', description: 'Allocation limit (optional, default: 0)'),
                    new OA\Property(property: 'backup_limit', type: 'integer', description: 'Backup limit (optional, default: 0)'),
                    new OA\Property(property: 'variables', type: 'object', description: 'Spell variables (optional)'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Server created successfully'),
            new OA\Response(response: 400, description: 'Invalid input or insufficient resources'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function createServer(Request $request): Response
    {
        $user = $request->get('user');
        $userId = (int) $user['id'];
        $data = json_decode($request->getContent(), true);

        if ($data === null) {
            return ApiResponse::error('Invalid JSON', 'INVALID_JSON', 400);
        }

        // Validate server creation
        $validation = ServerCreationHelper::validateServerCreation($userId, $data);
        if (!$validation['valid']) {
            return ApiResponse::error($validation['error'], $validation['error_code'] ?? 'VALIDATION_FAILED', 400);
        }

        try {
            // Prepare server data
            $serverData = [
                'uuid' => UUIDUtils::generateV4(),
                'uuidShort' => substr(str_replace('-', '', UUIDUtils::generateV4()), 0, 8),
                'node_id' => (int) $data['node_id'],
                'name' => $data['name'],
                'owner_id' => $userId,
                'memory' => (int) $data['memory'],
                'swap' => isset($data['swap']) ? (int) $data['swap'] : 0,
                'disk' => (int) $data['disk'],
                'io' => isset($data['io']) ? (int) $data['io'] : 500,
                'cpu' => (int) $data['cpu'],
                'allocation_id' => (int) $data['allocation_id'],
                'realms_id' => (int) $data['realms_id'],
                'spell_id' => (int) $data['spell_id'],
                'startup' => $data['startup'] ?? '',
                'image' => $data['image'] ?? '',
                'description' => $data['description'] ?? null,
                'status' => 'installing',
                'skip_scripts' => 0,
                'oom_disabled' => 0,
                'allocation_limit' => isset($data['allocation_limit']) ? ((int) $data['allocation_limit'] > 0 ? (int) $data['allocation_limit'] : null) : null,
                'database_limit' => isset($data['database_limit']) ? (int) $data['database_limit'] : 0,
                'backup_limit' => isset($data['backup_limit']) ? (int) $data['backup_limit'] : 0,
            ];

            // Create server
            $serverId = Server::createServer($serverData);
            if (!$serverId) {
                App::getInstance(true)->getLogger()->error('Failed to create server for user ID: ' . $userId);
                
                return ApiResponse::error('Failed to create server', 'CREATE_SERVER_FAILED', 500);
            }

            // Claim the allocation
            $allocationClaimed = Allocation::assignToServer($serverData['allocation_id'], $serverId);
            if (!$allocationClaimed) {
                App::getInstance(true)->getLogger()->error('Failed to claim allocation for server ID: ' . $serverId);
            }

            // Handle spell variables - always fetch and use defaults (like admin API)
            $spellVariables = SpellVariable::getVariablesBySpellId($serverData['spell_id']);
            $requiredVariables = [];
            $variablesToCreate = [];

            // Build list of required variables
            foreach ($spellVariables as $spellVariable) {
                if (strpos($spellVariable['rules'], 'required') !== false) {
                    $requiredVariables[] = $spellVariable['env_variable'];
                }
            }

            // Process variables: use provided values or defaults
            foreach ($spellVariables as $spellVariable) {
                $envVariable = $spellVariable['env_variable'];
                $providedValue = isset($data['variables'][$envVariable]) ? $data['variables'][$envVariable] : null;
                
                // Use provided value if present and non-empty, otherwise use default
                $effectiveValue = ($providedValue !== null && $providedValue !== '' && trim($providedValue) !== '') 
                    ? $providedValue 
                    : ($spellVariable['default_value'] ?? '');

                // Validate required variables have non-empty values
                if (strpos($spellVariable['rules'], 'required') !== false) {
                    if ($effectiveValue === null || $effectiveValue === '' || trim($effectiveValue) === '') {
                        // Delete server if required variable has no value
                        Server::hardDeleteServer($serverId);
                        
                        return ApiResponse::error(
                            'Required spell variable "' . $spellVariable['name'] . '" (' . $envVariable . ') has no default value and was not provided',
                            'MISSING_REQUIRED_VARIABLE',
                            400
                        );
                    }
                }

                // Add variable to create list (only if we have a value or it's optional)
                if ($effectiveValue !== null && $effectiveValue !== '') {
                    $variablesToCreate[] = [
                        'variable_id' => (int) $spellVariable['id'],
                        'variable_value' => (string) $effectiveValue,
                    ];
                }
            }

            // Create server variables using defaults (like admin API)
            if (!empty($variablesToCreate)) {
                $variablesCreated = ServerVariable::createOrUpdateServerVariables($serverId, $variablesToCreate);
                if (!$variablesCreated) {
                    App::getInstance(true)->getLogger()->error('Failed to create server variables for server ID: ' . $serverId);
                    // Don't fail server creation, just log the error
                }
            }

            // Create server in Wings (like admin API)
            $nodeInfo = Node::getNodeById($serverData['node_id']);
            if (!$nodeInfo) {
                Server::hardDeleteServer($serverId);
                return ApiResponse::error('Node not found', 'NODE_NOT_FOUND', 404);
            }

            $scheme = $nodeInfo['scheme'];
            $host = $nodeInfo['fqdn'];
            $port = $nodeInfo['daemonListen'];
            $token = $nodeInfo['daemon_token'];
            $timeout = 30;

            try {
                $wings = new Wings($host, $port, $scheme, $token, $timeout);

                $wingsData = [
                    'uuid' => $serverData['uuid'],
                    'start_on_completion' => true,
                ];

                $response = $wings->getServer()->createServer($wingsData);
                if (!$response->isSuccessful()) {
                    $error = $response->getError();
                    Server::hardDeleteServer($serverId);

                    if ($response->getStatusCode() === 400) {
                        return ApiResponse::error('Invalid server configuration: ' . $error, 'INVALID_SERVER_CONFIG', 400);
                    } elseif ($response->getStatusCode() === 401) {
                        return ApiResponse::error('Unauthorized access to Wings daemon', 'WINGS_UNAUTHORIZED', 401);
                    } elseif ($response->getStatusCode() === 403) {
                        return ApiResponse::error('Forbidden access to Wings daemon', 'WINGS_FORBIDDEN', 403);
                    } elseif ($response->getStatusCode() === 422) {
                        return ApiResponse::error('Invalid server data: ' . $error, 'INVALID_SERVER_DATA', 422);
                    }

                    return ApiResponse::error('Failed to create server in Wings: ' . $error, 'WINGS_ERROR', $response->getStatusCode());
                }
            } catch (\Exception $e) {
                App::getInstance(true)->getLogger()->error('Failed to create server in Wings: ' . $e->getMessage());
                Server::hardDeleteServer($serverId);

                return ApiResponse::error('Failed to create server in Wings: ' . $e->getMessage(), 'FAILED_TO_CREATE_SERVER_IN_WINGS', 500);
            }

            // Get created server
            $server = Server::getServerById($serverId);

            App::getInstance(true)->getLogger()->info('User ' . $user['username'] . ' (ID: ' . $userId . ') created server: ' . $serverData['name'] . ' (ID: ' . $serverId . ')');

            return ApiResponse::success([
                'server' => $server,
            ], 'Server created successfully', 201);
        } catch (\Exception $e) {
            App::getInstance(true)->getLogger()->error('Failed to create server: ' . $e->getMessage());
            
            return ApiResponse::error('Failed to create server: ' . $e->getMessage(), 'CREATE_SERVER_FAILED', 500);
        }
    }
}

