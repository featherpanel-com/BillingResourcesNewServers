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

namespace App\Addons\billingresourcesnewservers\Controllers\User;

use App\App;
use App\Chat\Node;
use App\Chat\Realm;
use App\Chat\Spell;
use App\Chat\Server;
use App\Chat\Location;
use App\Chat\Allocation;
use App\Helpers\UUIDUtils;
use App\Chat\SpellVariable;
use App\Chat\ServerVariable;
use App\Helpers\ApiResponse;
use App\Services\Wings\Wings;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Addons\billingresources\Helpers\ResourcesHelper;
use App\Addons\billingresourcesnewservers\Helpers\SettingsHelper;
use App\Addons\billingresourcesnewservers\Helpers\ServerCreationHelper;

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
        $user = $request->get('user');
        $userId = (int) $user['id'];

        // Check if user creation is enabled and user is allowed
        if (!SettingsHelper::isUserAllowed($userId)) {
            $mode = SettingsHelper::getUserRestrictionMode();
            if ($mode === 'specific') {
                return ApiResponse::error('You do not have permission to create servers', 'USER_NOT_ALLOWED', 403);
            }

            return ApiResponse::error('User server creation is currently disabled', 'USER_CREATION_DISABLED', 403);
        }

        try {
            // Get user ID for filtering
            $user = $request->get('user');
            $userId = (int) $user['id'];

            // Get all locations
            $allLocations = Location::getAll(null, 1000, 0);
            $locations = ServerCreationHelper::filterLocations($allLocations, $userId);
            // Sanitize locations (remove sensitive data if any)
            $locations = array_map([$this, 'sanitizeLocation'], $locations);

            // Get all nodes
            $allNodes = Node::getAllNodes();
            $nodes = ServerCreationHelper::filterNodes($allNodes, $userId);
            // Sanitize nodes (remove sensitive data)
            $nodes = array_map([$this, 'sanitizeNode'], $nodes);

            // Get all realms
            $allRealms = Realm::getAll(null, 1000, 0);
            $realms = ServerCreationHelper::filterRealms($allRealms, $userId);
            // Sanitize realms (remove sensitive data if any)
            $realms = array_map([$this, 'sanitizeRealm'], $realms);

            // Get all spells
            $allSpells = Spell::getAllSpells();
            $spells = ServerCreationHelper::filterSpells($allSpells, $userId);
            // Sanitize spells (remove sensitive data)
            $spells = array_map([$this, 'sanitizeSpell'], $spells);

            // Get user's available resources (userId already set above)
            $availableResources = ResourcesHelper::calculateAvailableResources($userId);

            // Get minimum resource requirements
            $minimumResources = [
                'memory' => SettingsHelper::getMinimumMemory(),
                'cpu' => SettingsHelper::getMinimumCpu(),
                'disk' => SettingsHelper::getMinimumDisk(),
            ];

            return ApiResponse::success([
                'locations' => array_values($locations),
                'nodes' => array_values($nodes),
                'realms' => array_values($realms),
                'spells' => array_values($spells),
                'available_resources' => $availableResources,
                'minimum_resources' => $minimumResources,
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
        $user = $request->get('user');
        $userId = (int) $user['id'];

        // Check if user creation is enabled and user is allowed
        if (!SettingsHelper::isUserAllowed($userId)) {
            $mode = SettingsHelper::getUserRestrictionMode();
            if ($mode === 'specific') {
                return ApiResponse::error('You do not have permission to create servers', 'USER_NOT_ALLOWED', 403);
            }

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
        $user = $request->get('user');
        $userId = (int) $user['id'];

        // Check if user creation is enabled and user is allowed
        if (!SettingsHelper::isUserAllowed($userId)) {
            $mode = SettingsHelper::getUserRestrictionMode();
            if ($mode === 'specific') {
                return ApiResponse::error('You do not have permission to create servers', 'USER_NOT_ALLOWED', 403);
            }

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
            // Sanitize allocations (only return necessary fields)
            $allocations = array_map([$this, 'sanitizeAllocation'], $allocations);

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
                    new OA\Property(property: 'allocation_id', type: 'integer', nullable: true, description: 'Allocation ID (optional - will be auto-selected if not provided)'),
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
            $nodeId = (int) $data['node_id'];

            // Auto-select a random free allocation (like ServerAllocationController::autoAllocate)
            $availableAllocations = Allocation::getAll(
                search: null,
                nodeId: $nodeId,
                serverId: null,
                limit: 100,
                offset: 0,
                notUsed: true
            );

            if (empty($availableAllocations)) {
                return ApiResponse::error('No free allocations available on this node', 'NO_FREE_ALLOCATIONS', 400);
            }

            // Randomly select one allocation
            shuffle($availableAllocations);
            $selectedAllocation = $availableAllocations[0];
            $allocationId = (int) $selectedAllocation['id'];

            // Prepare server data
            $serverData = [
                'uuid' => UUIDUtils::generateV4(),
                'uuidShort' => substr(str_replace('-', '', UUIDUtils::generateV4()), 0, 8),
                'node_id' => $nodeId,
                'name' => $data['name'],
                'owner_id' => $userId,
                'memory' => (int) $data['memory'],
                'swap' => isset($data['swap']) ? (int) $data['swap'] : 0,
                'disk' => (int) $data['disk'],
                'io' => isset($data['io']) ? (int) $data['io'] : 500,
                'cpu' => (int) $data['cpu'],
                'allocation_id' => $allocationId,
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
            $allocationClaimed = Allocation::assignToServer($allocationId, $serverId);
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
                $providedValue = $data['variables'][$envVariable] ?? null;

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

    /**
     * Sanitize node data by removing sensitive fields.
     *
     * @param array<string,mixed> $node Node data
     *
     * @return array<string,mixed> Sanitized node data
     */
    private function sanitizeNode(array $node): array
    {
        // Remove sensitive fields
        unset(
            $node['daemon_token'],
            $node['daemon_token_id'],
            $node['daemonBase'],
            $node['public_ip_v4'],
            $node['public_ip_v6'],
            $node['memory'],
            $node['memory_overallocate'],
            $node['disk'],
            $node['disk_overallocate'],
            $node['upload_size'],
            $node['daemonListen'],
            $node['daemonSFTP']
        );

        // Only return safe fields for user selection
        return [
            'id' => $node['id'] ?? null,
            'name' => $node['name'] ?? null,
            'description' => $node['description'] ?? null,
            'location_id' => $node['location_id'] ?? null,
            'public' => $node['public'] ?? null,
            'scheme' => $node['scheme'] ?? null,
            'fqdn' => $node['fqdn'] ?? null,
            'behind_proxy' => $node['behind_proxy'] ?? null,
            'maintenance_mode' => $node['maintenance_mode'] ?? null,
            'created_at' => $node['created_at'] ?? null,
            'updated_at' => $node['updated_at'] ?? null,
        ];
    }

    /**
     * Sanitize location data by removing sensitive fields.
     *
     * @param array<string,mixed> $location Location data
     *
     * @return array<string,mixed> Sanitized location data
     */
    private function sanitizeLocation(array $location): array
    {
        // Locations are generally safe, but only return necessary fields
        return [
            'id' => $location['id'] ?? null,
            'name' => $location['name'] ?? null,
            'flag_code' => $location['flag_code'] ?? null,
            'description' => $location['description'] ?? null,
            'created_at' => $location['created_at'] ?? null,
            'updated_at' => $location['updated_at'] ?? null,
        ];
    }

    /**
     * Sanitize realm data by removing sensitive fields.
     *
     * @param array<string,mixed> $realm Realm data
     *
     * @return array<string,mixed> Sanitized realm data
     */
    private function sanitizeRealm(array $realm): array
    {
        // Realms are generally safe, but only return necessary fields
        return [
            'id' => $realm['id'] ?? null,
            'name' => $realm['name'] ?? null,
            'description' => $realm['description'] ?? null,
            'created_at' => $realm['created_at'] ?? null,
            'updated_at' => $realm['updated_at'] ?? null,
        ];
    }

    /**
     * Sanitize spell data by removing sensitive fields.
     *
     * @param array<string,mixed> $spell Spell data
     *
     * @return array<string,mixed> Sanitized spell data
     */
    private function sanitizeSpell(array $spell): array
    {
        // Remove sensitive/internal fields
        unset(
            $spell['script_install'],
            $spell['script_container'],
            $spell['script_entry'],
            $spell['script_is_privileged'],
            $spell['copy_script_from'],
            $spell['config_files'],
            $spell['config_startup'],
            $spell['config_logs'],
            $spell['config_stop'],
            $spell['config_from'],
            $spell['update_url'],
            $spell['file_denylist']
        );

        // Only return safe fields for user selection
        return [
            'id' => $spell['id'] ?? null,
            'uuid' => $spell['uuid'] ?? null,
            'realm_id' => $spell['realm_id'] ?? null,
            'name' => $spell['name'] ?? null,
            'description' => $spell['description'] ?? null,
            'author' => $spell['author'] ?? null,
            'features' => $spell['features'] ?? null,
            'docker_images' => $spell['docker_images'] ?? null,
            'docker_image' => $spell['docker_image'] ?? null,
            'startup' => $spell['startup'] ?? null,
            'force_outgoing_ip' => $spell['force_outgoing_ip'] ?? null,
            'banner' => $spell['banner'] ?? null,
            'created_at' => $spell['created_at'] ?? null,
            'updated_at' => $spell['updated_at'] ?? null,
        ];
    }

    /**
     * Sanitize allocation data by keeping only necessary fields.
     *
     * @param array<string,mixed> $allocation Allocation data
     *
     * @return array<string,mixed> Sanitized allocation data
     */
    private function sanitizeAllocation(array $allocation): array
    {
        // Only return fields needed for user selection
        return [
            'id' => $allocation['id'] ?? null,
            'node_id' => $allocation['node_id'] ?? null,
            'ip' => $allocation['ip'] ?? null,
            'ip_alias' => $allocation['ip_alias'] ?? null,
            'port' => $allocation['port'] ?? null,
            'server_id' => $allocation['server_id'] ?? null,
            'notes' => $allocation['notes'] ?? null,
            'created_at' => $allocation['created_at'] ?? null,
            'updated_at' => $allocation['updated_at'] ?? null,
        ];
    }
}
