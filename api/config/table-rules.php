<?php
/**
 * Table-Specific Rules & Validation Configuration
 *
 * Define validation constraints and business logic hooks for each table.
 *
 * Structure:
 * 'table_name' => [
 *     'validation' => [
 *         'field_name' => [
 *             'required' => bool,
 *             'type' => 'email|integer|string|float|boolean|array',
 *             'minLength' => int,
 *             'maxLength' => int,
 *             'min' => number,
 *             'max' => number,
 *             'enum' => [allowed, values],
 *             'unique' => bool,
 *             'unique_with' => ['field1', 'field2'],
 *         ]
 *     ],
 *     'hooks' => [
 *         'beforeCreate' => function($data, $user, $logger) { },
 *         'afterCreate' => function($id, $user, $logger, $db) { },
 *         'beforeUpdate' => function($id, $data, $user, $logger, $db) { },
 *         'afterUpdate' => function($id, $user, $logger, $db) { },
 *         'beforeDelete' => function($id, $user, $logger, $db) { },
 *         'afterDelete' => function($id, $user, $logger, $db) { },
 *     ]
 * ]
 */

return [

    // ==================== USERS TABLE ====================
    'users' => [
        'validation' => [
            'email' => [
                'type' => 'email',
                'required' => true,
                'unique' => true,
            ],
            'password' => [
                'minLength' => 8,
                'required' => true,
            ],
            'first_name' => [
                'minLength' => 2,
                'maxLength' => 64,
                'required' => true,
            ],
            'last_name' => [
                'minLength' => 2,
                'maxLength' => 64,
                'required' => true,
            ],
        ],
        'hooks' => [
            'beforeDelete' => function($id, $user, $logger, $db) {
                // Admin cannot delete themselves
                if ($user->id === $id) {
                    throw new \App\RuleException(
                        'You cannot delete your own account',
                        403,
                        'users',
                        'beforeDelete'
                    );
                }

                // Check if this is the last admin
                try {
                    // This is a placeholder - would need proper role checking
                    $userRecord = $db->get('users', $id);
                    if (!$userRecord || $userRecord['status'] !== 200) {
                        throw new \App\RuleException(
                            'User not found',
                            404,
                            'users',
                            'beforeDelete'
                        );
                    }
                } catch (\Exception $e) {
                    throw new \App\RuleException(
                        $e->getMessage(),
                        $e->getCode() ?: 400,
                        'users',
                        'beforeDelete'
                    );
                }
            },

            'afterDelete' => function($id, $user, $logger, $db) {
                // Remove user from all groups
                try {
                    $db->execute(
                        "DELETE FROM users_groups WHERE user_id = ?",
                        [$id]
                    );

                    // Remove user roles
                    $db->execute(
                        "DELETE FROM users_roles WHERE user_id = ?",
                        [$id]
                    );

                    $logger->info("User deleted and removed from groups", ['user_id' => $id]);
                } catch (\Exception $e) {
                    $logger->error("Cleanup after user delete failed", [
                        'user_id' => $id,
                        'error' => $e->getMessage(),
                    ]);
                }
            },
        ],
    ],

    // ==================== CATEGORIES TABLE ====================
    'categories' => [
        'validation' => [
            'name' => [
                'minLength' => 2,
                'maxLength' => 255,
                'required' => true,
            ],
            'parent_id' => [
                'type' => 'integer',
                'required' => false,
            ],
        ],
        'hooks' => [
            'beforeDelete' => function($id, $user, $logger, $db) {
                // Cannot delete category that has items
                try {
                    $items = $db->getAllWhere('items', "category_id = ?", [$id]);

                    if ($items['status'] === 200 && !empty($items['data'])) {
                        throw new \App\RuleException(
                            'Cannot delete category with items. Remove items first.',
                            409,
                            'categories',
                            'beforeDelete'
                        );
                    }
                } catch (\App\RuleException $e) {
                    throw $e;
                } catch (\Exception $e) {
                    throw new \App\RuleException(
                        'Error checking for items: ' . $e->getMessage(),
                        400,
                        'categories',
                        'beforeDelete'
                    );
                }
            },

            'beforeUpdate' => function($id, $data, $user, $logger, $db) {
                // Prevent circular parent_id (category cannot be parent of itself)
                if (isset($data['parent_id']) && $data['parent_id'] == $id) {
                    throw new \App\RuleException(
                        'Category cannot be its own parent',
                        400,
                        'categories',
                        'beforeUpdate'
                    );
                }
            },
        ],
    ],

    // ==================== ITEMS TABLE ====================
    'items' => [
        'validation' => [
            'category_id' => [
                'type' => 'integer',
                'required' => true,
            ],
            'inventory_number' => [
                'minLength' => 2,
                'maxLength' => 255,
                'required' => true,
            ],
            'status' => [
                'enum' => ['active', 'repair', 'retired', 'storage'],
                'required' => false,
            ],
        ],
        'hooks' => [
            'beforeCreate' => function($data, $user, $logger) {
                // Validate category exists
                if (!isset($data['category_id'])) {
                    throw new \App\RuleException(
                        'category_id is required',
                        400,
                        'items',
                        'beforeCreate'
                    );
                }

                $logger->info("Creating item", [
                    'user_id' => $user->id,
                    'inventory_number' => $data['inventory_number'] ?? null,
                ]);
            },

            'afterUpdate' => function($id, $user, $logger, $db) {
                // Log item status changes
                try {
                    $item = $db->get('items', $id);
                    if ($item['status'] === 200) {
                        $logger->info("Item updated", [
                            'item_id' => $id,
                            'user_id' => $user->id,
                            'status' => $item['data']['status'] ?? null,
                        ]);
                    }
                } catch (\Exception $e) {
                    $logger->error("Error logging item update", ['error' => $e->getMessage()]);
                }
            },
        ],
    ],

    // ==================== GROUPS TABLE ====================
    'groups' => [
        'validation' => [
            'name' => [
                'minLength' => 2,
                'maxLength' => 64,
                'required' => true,
            ],
            'description' => [
                'maxLength' => 1024,
                'required' => false,
            ],
        ],
        'hooks' => [
            'beforeDelete' => function($id, $user, $logger, $db) {
                // Cannot delete group with members
                try {
                    $members = $db->getAllWhere('users_groups', "group_id = ?", [$id]);

                    if ($members['status'] === 200 && !empty($members['data'])) {
                        throw new \App\RuleException(
                            'Cannot delete group with members. Remove members first.',
                            409,
                            'groups',
                            'beforeDelete'
                        );
                    }
                } catch (\App\RuleException $e) {
                    throw $e;
                } catch (\Exception $e) {
                    throw new \App\RuleException(
                        'Error checking for members: ' . $e->getMessage(),
                        400,
                        'groups',
                        'beforeDelete'
                    );
                }
            },
        ],
    ],

    // ==================== LOANS TABLE ====================
    'loans' => [
        'validation' => [
            'person_id' => [
                'type' => 'integer',
                'required' => true,
            ],
            'start_date' => [
                'required' => true,
            ],
            'end_date' => [
                'required' => false,
            ],
        ],
        'hooks' => [
            'beforeCreate' => function($data, $user, $logger) {
                // Validate dates
                if (isset($data['end_date']) && isset($data['start_date'])) {
                    if (strtotime($data['end_date']) < strtotime($data['start_date'])) {
                        throw new \App\RuleException(
                            'end_date cannot be before start_date',
                            400,
                            'loans',
                            'beforeCreate'
                        );
                    }
                }
            },
        ],
    ],

    // ==================== PERSONS TABLE ====================
    'persons' => [
        'validation' => [
            'first_name' => [
                'minLength' => 2,
                'maxLength' => 255,
                'required' => true,
            ],
            'last_name' => [
                'minLength' => 2,
                'maxLength' => 255,
                'required' => true,
            ],
            'email' => [
                'type' => 'email',
                'required' => false,
            ],
            'phone' => [
                'minLength' => 9,
                'maxLength' => 20,
                'required' => false,
            ],
        ],
    ],

    // ==================== ROLES TABLE ====================
    'roles' => [
        'validation' => [
            'name' => [
                'minLength' => 2,
                'maxLength' => 64,
                'required' => true,
                'unique' => true,
            ],
        ],
        'hooks' => [
            'beforeDelete' => function($id, $user, $logger, $db) {
                // Prevent deletion of built-in roles
                try {
                    $role = $db->get('roles', $id);
                    if ($role['status'] === 200) {
                        $builtInRoles = ['admin', 'user', 'guest'];
                        if (in_array(strtolower($role['data']['name']), $builtInRoles)) {
                            throw new \App\RuleException(
                                'Cannot delete built-in roles',
                                403,
                                'roles',
                                'beforeDelete'
                            );
                        }
                    }
                } catch (\App\RuleException $e) {
                    throw $e;
                } catch (\Exception $e) {
                    throw new \App\RuleException(
                        'Error checking role: ' . $e->getMessage(),
                        400,
                        'roles',
                        'beforeDelete'
                    );
                }
            },
        ],
    ],

    // ==================== PLACES TABLE ====================
    'places' => [
        'validation' => [
            'name' => [
                'minLength' => 2,
                'maxLength' => 64,
                'required' => true,
                'unique' => true,
            ],
            'gps_lat' => [
                'type' => 'float',
                'min' => -90,
                'max' => 90,
                'required' => false,
            ],
            'gps_lon' => [
                'type' => 'float',
                'min' => -180,
                'max' => 180,
                'required' => false,
            ],
        ],
    ],

    // ==================== EVENTS TABLE ====================
    'events' => [
        'validation' => [
            'name' => [
                'minLength' => 2,
                'maxLength' => 64,
                'required' => true,
            ],
            'starts_at' => [
                'required' => false,
            ],
            'ends_at' => [
                'required' => false,
            ],
        ],
        'hooks' => [
            'beforeCreate' => function($data, $user, $logger) {
                // Validate date range
                if (isset($data['ends_at']) && isset($data['starts_at'])) {
                    if (strtotime($data['ends_at']) < strtotime($data['starts_at'])) {
                        throw new \App\RuleException(
                            'ends_at cannot be before starts_at',
                            400,
                            'events',
                            'beforeCreate'
                        );
                    }
                }
            },
        ],
    ],

];
