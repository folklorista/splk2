<?php
/**
 * Authorization & Permission Matrix
 *
 * Defines role-based access control for each table and operation.
 *
 * Structure:
 * 'table_name' => [
 *     'owner_field' => 'user_id',  // Field that identifies record owner
 *     'permissions' => [
 *         'role_name' => [
 *             'read' => true,
 *             'create' => true,
 *             'update' => true,
 *             'delete' => true,
 *             'read_own_only' => false,  // Can only read own records
 *             'update_own_only' => false,
 *             'delete_own_only' => false,
 *         ]
 *     ]
 * ]
 */

return [
    // ==================== USERS TABLE ====================
    'users' => [
        'owner_field' => 'id',
        'permissions' => [
            'admin' => [
                'read' => true,
                'create' => true,
                'update' => true,
                'delete' => true,
                'read_own_only' => false,
                'update_own_only' => false,
                'delete_own_only' => false,
            ],
            'user' => [
                'read' => true,
                'create' => false,
                'update' => true,
                'delete' => false,
                'read_own_only' => true,   // Can only read own profile
                'update_own_only' => true, // Can only update own profile
                'delete_own_only' => false,
            ],
            'guest' => [
                'read' => true,
                'create' => false,
                'update' => false,
                'delete' => false,
                'read_own_only' => true,
                'update_own_only' => false,
                'delete_own_only' => false,
            ],
        ]
    ],

    // ==================== CATEGORIES TABLE ====================
    'categories' => [
        'owner_field' => 'created_by',
        'permissions' => [
            'admin' => [
                'read' => true,
                'create' => true,
                'update' => true,
                'delete' => true,
                'read_own_only' => false,
                'update_own_only' => false,
                'delete_own_only' => false,
            ],
            'user' => [
                'read' => true,
                'create' => true,
                'update' => true,
                'delete' => true,
                'read_own_only' => true,   // Can only see own categories
                'update_own_only' => true, // Can only update own categories
                'delete_own_only' => true, // Can only delete own categories
            ],
            'guest' => [
                'read' => true,
                'create' => false,
                'update' => false,
                'delete' => false,
                'read_own_only' => false,
                'update_own_only' => false,
                'delete_own_only' => false,
            ],
        ]
    ],

    // ==================== ITEMS TABLE ====================
    'items' => [
        'owner_field' => 'created_by',
        'permissions' => [
            'admin' => [
                'read' => true,
                'create' => true,
                'update' => true,
                'delete' => true,
                'read_own_only' => false,
                'update_own_only' => false,
                'delete_own_only' => false,
            ],
            'user' => [
                'read' => true,
                'create' => true,
                'update' => true,
                'delete' => true,
                'read_own_only' => true,   // Can only see own items
                'update_own_only' => true, // Can only update own items
                'delete_own_only' => true, // Can only delete own items
            ],
            'guest' => [
                'read' => true,
                'create' => false,
                'update' => false,
                'delete' => false,
                'read_own_only' => false,
                'update_own_only' => false,
                'delete_own_only' => false,
            ],
        ]
    ],

    // ==================== DEFAULT PERMISSIONS ====================
    // For tables not explicitly defined, use these defaults
    'default' => [
        'owner_field' => 'created_by',
        'permissions' => [
            'admin' => [
                'read' => true,
                'create' => true,
                'update' => true,
                'delete' => true,
                'read_own_only' => false,
                'update_own_only' => false,
                'delete_own_only' => false,
            ],
            'user' => [
                'read' => true,
                'create' => true,
                'update' => true,
                'delete' => true,
                'read_own_only' => true,
                'update_own_only' => true,
                'delete_own_only' => true,
            ],
            'guest' => [
                'read' => true,
                'create' => false,
                'update' => false,
                'delete' => false,
                'read_own_only' => false,
                'update_own_only' => false,
                'delete_own_only' => false,
            ],
        ]
    ],
];
