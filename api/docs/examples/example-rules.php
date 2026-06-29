<?php
/**
 * SPLK2 API - Table Rules Examples
 *
 * This file shows practical examples of validation rules and business logic hooks.
 */

// =============================================================================
// EXAMPLE 1: Preventing Admin Self-Deletion
// =============================================================================
/*
Rule: Admin user cannot delete their own account

Configuration:
*/
'users' => [
    'hooks' => [
        'beforeDelete' => function($id, $user, $logger, $db) {
            // Check if user is trying to delete themselves
            if ($user->id === $id) {
                throw new \App\RuleException(
                    'You cannot delete your own account',
                    403,
                    'users',
                    'beforeDelete'
                );
            }

            $logger->info("Attempting to delete user {$id} by user {$user->id}");
        }
    ]
]

/*
Test:
DELETE /users/1 → returns 403 "You cannot delete your own account"
*/


// =============================================================================
// EXAMPLE 2: Cascading Actions (Delete Category Only If Empty)
// =============================================================================
/*
Rule: Cannot delete a category if it has items

Configuration:
*/
'categories' => [
    'hooks' => [
        'beforeDelete' => function($id, $user, $logger, $db) {
            // Check if category has any items
            $items = $db->getAllWhere('items', 'category_id = :id', [':id' => $id]);

            if ($items['status'] === 200 && !empty($items['data'])) {
                throw new \App\RuleException(
                    'Cannot delete category with items. Remove items first.',
                    409,  // Conflict status code
                    'categories',
                    'beforeDelete'
                );
            }
        },

        'afterDelete' => function($id, $user, $logger, $db) {
            // Clean up any associated labels
            $db->execute(
                "DELETE FROM category_labels WHERE category_id = ?",
                [$id]
            );

            $logger->info("Category {$id} and its labels deleted");
        }
    ]
]

/*
Test:
1. Create category: POST /categories {"name": "Electronics"}
2. Try to delete it: DELETE /categories/1
   → returns 200 (success, no items)

3. Create item in category: POST /items {"category_id": 1, "inventory_number": "ITEM-001"}
4. Try to delete category: DELETE /categories/1
   → returns 409 "Cannot delete category with items"
*/


// =============================================================================
// EXAMPLE 3: Custom Validation - Date Range
// =============================================================================
/*
Rule: Event end_date cannot be before start_date

Configuration:
*/
'events' => [
    'validation' => [
        'starts_at' => ['required' => false],
        'ends_at' => ['required' => false],
    ],
    'hooks' => [
        'beforeCreate' => function($data, $user, $logger) {
            // Validate dates are in correct order
            if (isset($data['ends_at']) && isset($data['starts_at'])) {
                $startTime = strtotime($data['starts_at']);
                $endTime = strtotime($data['ends_at']);

                if ($endTime < $startTime) {
                    throw new \App\RuleException(
                        'Event end date must be after start date',
                        400,
                        'events',
                        'beforeCreate'
                    );
                }
            }
        }
    ]
]

/*
Test:
POST /events {
    "name": "Conference",
    "starts_at": "2025-06-01T09:00:00Z",
    "ends_at": "2025-05-31T17:00:00Z"  // Before start date!
}
→ returns 400 "Event end date must be after start date"

POST /events {
    "name": "Conference",
    "starts_at": "2025-06-01T09:00:00Z",
    "ends_at": "2025-06-02T17:00:00Z"   // Correct order
}
→ returns 201 (created)
*/


// =============================================================================
// EXAMPLE 4: Preventing Circular References
// =============================================================================
/*
Rule: Category cannot be its own parent (preventing circular hierarchy)

Configuration:
*/
'categories' => [
    'hooks' => [
        'beforeUpdate' => function($id, $data, $user, $logger, $db) {
            // Check for self-reference
            if (isset($data['parent_id']) && $data['parent_id'] == $id) {
                throw new \App\RuleException(
                    'Category cannot be its own parent',
                    400,
                    'categories',
                    'beforeUpdate'
                );
            }

            // Optional: Check for indirect circular references
            $currentParentId = $data['parent_id'];
            $depth = 0;
            $maxDepth = 100;  // Prevent infinite loops

            while ($currentParentId && $depth < $maxDepth) {
                if ($currentParentId == $id) {
                    throw new \App\RuleException(
                        'This would create a circular reference',
                        400,
                        'categories',
                        'beforeUpdate'
                    );
                }

                $parent = $db->get('categories', $currentParentId);
                $currentParentId = $parent['data']['parent_id'] ?? null;
                $depth++;
            }
        }
    ]
]

/*
Test:
1. Category tree: Electronics (id=1) > Computers (id=2)
2. Try: PUT /categories/1 {"parent_id": 1}
   → returns 400 "Category cannot be its own parent"

3. Try: PUT /categories/1 {"parent_id": 2}
   → returns 400 "This would create a circular reference"
   (because 1 > 2, and now 2 > 1)
*/


// =============================================================================
// EXAMPLE 5: Logging & Audit Trails
// =============================================================================
/*
Rule: Log detailed information when important items are modified

Configuration:
*/
'items' => [
    'hooks' => [
        'afterCreate' => function($id, $user, $logger, $db) {
            $item = $db->get('items', $id);

            $logger->info("Item created", [
                'item_id' => $id,
                'user_id' => $user->id,
                'user_name' => $user->firstName . ' ' . $user->lastName,
                'inventory_number' => $item['data']['inventory_number'],
                'timestamp' => date('Y-m-d H:i:s'),
            ]);
        },

        'afterUpdate' => function($id, $user, $logger, $db) {
            $item = $db->get('items', $id);

            $logger->info("Item updated", [
                'item_id' => $id,
                'user_id' => $user->id,
                'status' => $item['data']['status'] ?? null,
                'timestamp' => date('Y-m-d H:i:s'),
            ]);
        },

        'afterDelete' => function($id, $user, $logger, $db) {
            $logger->warning("Item deleted", [
                'item_id' => $id,
                'user_id' => $user->id,
                'user_name' => $user->firstName . ' ' . $user->lastName,
                'timestamp' => date('Y-m-d H:i:s'),
            ]);
        }
    ]
]

/*
Logs in /api/log/api/app.log:
[2025-01-15 10:30:45] INFO: Item created {"item_id":42,"user_id":1,"user_name":"John Doe","inventory_number":"COMP-001","timestamp":"2025-01-15 10:30:45"}
[2025-01-15 10:35:20] INFO: Item updated {"item_id":42,"user_id":1,"status":"repair","timestamp":"2025-01-15 10:35:20"}
[2025-01-15 11:00:15] WARNING: Item deleted {"item_id":42,"user_id":1,"user_name":"John Doe","timestamp":"2025-01-15 11:00:15"}
*/


// =============================================================================
// EXAMPLE 6: Complex Validation with Multiple Constraints
// =============================================================================
/*
Rule: User email must be unique, password strong, names valid length

Configuration:
*/
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
    ]
]

/*
Test cases:
1. Missing email:
   POST /users {"password":"pass123456","first_name":"John","last_name":"Doe"}
   → 400 "Validation failed: email is required"

2. Invalid email:
   POST /users {"email":"not-an-email","password":"pass123456",...}
   → 400 "Validation failed: email must be of type email"

3. Duplicate email:
   (Assuming john@example.com exists)
   POST /users {"email":"john@example.com",...}
   → 400 "Validation failed: email already exists"

4. Short password:
   POST /users {"email":"jane@example.com","password":"short",...}
   → 400 "Validation failed: password must be at least 8 characters"

5. Valid request:
   POST /users {"email":"jane@example.com","password":"Secure123","first_name":"Jane","last_name":"Doe"}
   → 201 (created)
*/


// =============================================================================
// EXAMPLE 7: Cleanup After Delete (Group Members)
// =============================================================================
/*
Rule: When deleting a user, remove them from all groups

Configuration:
*/
'users' => [
    'hooks' => [
        'afterDelete' => function($id, $user, $logger, $db) {
            // Remove user from all groups
            $db->execute(
                "DELETE FROM users_groups WHERE user_id = ?",
                [$id]
            );

            // Remove user roles
            $db->execute(
                "DELETE FROM users_roles WHERE user_id = ?",
                [$id]
            );

            // Remove user events
            $db->execute(
                "DELETE FROM users_events WHERE user_id = ?",
                [$id]
            );

            $logger->info("User {$id} removed from all groups and roles");
        }
    ]
]

/*
Sequence:
1. GET /users/42 → User in 3 groups, has 2 roles
2. DELETE /users/42 → Success
3. SELECT * FROM users_groups WHERE user_id = 42 → Empty result
4. SELECT * FROM users_roles WHERE user_id = 42 → Empty result
*/


// =============================================================================
// EXAMPLE 8: Notification/Event Hooks
// =============================================================================
/*
Rule: Send email when new user registers

Configuration:
*/
'users' => [
    'hooks' => [
        'afterCreate' => function($id, $user, $logger, $db) {
            $newUser = $db->get('users', $id);

            if ($newUser['status'] !== 200) {
                return;
            }

            // Send welcome email
            try {
                $email = $newUser['data']['email'];
                $name = $newUser['data']['first_name'];

                // Example: Call email service
                // EmailService::sendWelcome($email, $name);

                $logger->info("Welcome email queued", ['user_id' => $id, 'email' => $email]);
            } catch (\Exception $e) {
                // Don't fail the whole operation if email fails
                $logger->error("Email send failed", [
                    'user_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    ]
]

/*
Typical implementation:
1. User registers via POST /register
2. Account created in database
3. Hook fires and queues welcome email
4. Returns 201 to user immediately
5. Email sent asynchronously
*/


// =============================================================================
// EXAMPLE 9: Conditional Validation
// =============================================================================
/*
Rule: If payment status is "completed", receipt_id is required

Configuration:
*/
'orders' => [
    'hooks' => [
        'beforeCreate' => function($data, $user, $logger) {
            if (isset($data['status']) && $data['status'] === 'completed') {
                if (empty($data['receipt_id'])) {
                    throw new \App\RuleException(
                        'receipt_id is required when status is "completed"',
                        400,
                        'orders',
                        'beforeCreate'
                    );
                }
            }
        },

        'beforeUpdate' => function($id, $data, $user, $logger, $db) {
            // If changing status to "completed", require receipt_id
            if (isset($data['status']) && $data['status'] === 'completed') {
                if (empty($data['receipt_id'])) {
                    throw new \App\RuleException(
                        'receipt_id is required when status is "completed"',
                        400,
                        'orders',
                        'beforeUpdate'
                    );
                }
            }
        }
    ]
]

/*
Test:
POST /orders {
    "status": "pending",
    "amount": 100.00
}
→ 201 (OK, no receipt needed)

POST /orders {
    "status": "completed",
    "amount": 100.00,
    "receipt_id": ""
}
→ 400 "receipt_id is required when status is 'completed'"

POST /orders {
    "status": "completed",
    "amount": 100.00,
    "receipt_id": "REC-12345"
}
→ 201 (OK)
*/
