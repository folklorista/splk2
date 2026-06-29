#!/bin/bash
# SPLK2 API - cURL Examples
# Usage: Save token and reuse in other requests

# ============================================================================
# 1. AUTHENTICATION
# ============================================================================

# Register new user
curl -X POST "http://localhost:8000/register" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "SecurePassword123",
    "first_name": "John",
    "last_name": "Doe"
  }'

# Login and get token
TOKEN=$(curl -s -X POST "http://localhost:8000/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "SecurePassword123"
  }' | jq -r '.data.token')

echo "Token: $TOKEN"
export TOKEN  # Use in other requests

# ============================================================================
# 2. CRUD OPERATIONS
# ============================================================================

# List all users
curl -X GET "http://localhost:8000/users" \
  -H "Authorization: Bearer $TOKEN"

# List with pagination
curl -X GET "http://localhost:8000/users" \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Pagination-Limit: 10" \
  -H "X-Pagination-Offset: 0"

# List with sorting
curl -X GET "http://localhost:8000/users" \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Sort-By: created_at" \
  -H "X-Sort-Direction: DESC" \
  -H "X-Pagination-Limit: 20"

# Search users
curl -X GET "http://localhost:8000/users" \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Search-Query: john" \
  -H "X-Search-Columns: first_name,last_name"

# Get single user
curl -X GET "http://localhost:8000/users/1" \
  -H "Authorization: Bearer $TOKEN"

# Create user (demonstrates validation)
curl -X POST "http://localhost:8000/users" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "newuser@example.com",
    "password": "SecurePassword123",
    "first_name": "Jane",
    "last_name": "Smith"
  }'

# Create user with validation error (short password)
curl -X POST "http://localhost:8000/users" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "short",
    "first_name": "Test",
    "last_name": "User"
  }'
# Returns 400 "password must be at least 8 characters"

# Update user
curl -X PUT "http://localhost:8000/users/1" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "Jonathan",
    "last_name": "Doe"
  }'

# Delete user (will fail due to business rule: cannot delete self)
curl -X DELETE "http://localhost:8000/users/1" \
  -H "Authorization: Bearer $TOKEN"
# Returns 403 "You cannot delete your own account"

# ============================================================================
# 3. SCHEMA INTROSPECTION
# ============================================================================

# Get table schema
curl -X GET "http://localhost:8000/schema/users" \
  -H "Authorization: Bearer $TOKEN" | jq '.'

# Get schema for items table
curl -X GET "http://localhost:8000/schema/items" \
  -H "Authorization: Bearer $TOKEN"

# ============================================================================
# 4. FOREIGN KEY OPERATIONS
# ============================================================================

# Get options for foreign key dropdown (categories)
curl -X GET "http://localhost:8000/categories/options" \
  -H "Authorization: Bearer $TOKEN" | jq '.'

# Get items by category (foreign key filter)
curl -X GET "http://localhost:8000/items?foreignKeys=true&table=items&category_id=1" \
  -H "Authorization: Bearer $TOKEN"

# ============================================================================
# 5. TREE OPERATIONS (Hierarchical Data)
# ============================================================================

# Get category tree
curl -X GET "http://localhost:8000/categories" \
  -H "Authorization: Bearer $TOKEN" | jq '.'

# Create categories with hierarchy
curl -X POST "http://localhost:8000/categories" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Electronics",
    "parent_id": null
  }'

# Save entire tree structure
curl -X PUT "http://localhost:8000/categories" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '[
    {
      "id": "1",
      "name": "Electronics",
      "children": [
        {
          "id": "2",
          "name": "Computers",
          "children": [
            {
              "id": "3",
              "name": "Laptops",
              "children": []
            }
          ]
        }
      ]
    }
  ]'

# ============================================================================
# 6. COMPLEX SCENARIOS
# ============================================================================

# Create item (demonstrates required foreign key)
curl -X POST "http://localhost:8000/items" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Laptop",
    "description": "High-performance laptop",
    "category_id": 1
  }'

# Try to delete category with items (will fail due to business rule)
curl -X DELETE "http://localhost:8000/categories/1" \
  -H "Authorization: Bearer $TOKEN"
# Returns 409 "Cannot delete category with items. Remove items first."

# First delete items, then delete category
curl -X DELETE "http://localhost:8000/items/1" \
  -H "Authorization: Bearer $TOKEN"

curl -X DELETE "http://localhost:8000/categories/1" \
  -H "Authorization: Bearer $TOKEN"
# Now succeeds

# ============================================================================
# 7. ERROR HANDLING
# ============================================================================

# Missing authorization header
curl -X GET "http://localhost:8000/users"
# Returns 401 "You must be logged in to access this resource"

# Invalid token
curl -X GET "http://localhost:8000/users" \
  -H "Authorization: Bearer invalid.token.here"
# Returns 401 "Unauthorized access"

# Invalid table name
curl -X GET "http://localhost:8000/nonexistent" \
  -H "Authorization: Bearer $TOKEN"
# Returns 404 "Table not found"

# Duplicate email (unique constraint)
curl -X POST "http://localhost:8000/users" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "existing@example.com",
    "password": "SecurePassword123",
    "first_name": "John",
    "last_name": "Doe"
  }'
# Returns 400 "email already exists" (if email already registered)

# ============================================================================
# 8. ADVANCED: PIPELINES & DATA FLOW
# ============================================================================

# Complete workflow: Register → Login → Create item → Update → Delete

# 1. Register
RESPONSE=$(curl -s -X POST "http://localhost:8000/register" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "workflow@example.com",
    "password": "WorkflowPass123",
    "first_name": "Workflow",
    "last_name": "User"
  }')
echo "Register: $RESPONSE"

# 2. Login
TOKEN=$(curl -s -X POST "http://localhost:8000/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "workflow@example.com",
    "password": "WorkflowPass123"
  }' | jq -r '.data.token')
echo "Token: $TOKEN"

# 3. Create category
CATEGORY=$(curl -s -X POST "http://localhost:8000/categories" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Workflow Category"
  }')
CATEGORY_ID=$(echo $CATEGORY | jq -r '.data.id')
echo "Created category: $CATEGORY_ID"

# 4. Create item in category
ITEM=$(curl -s -X POST "http://localhost:8000/items" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"name\": \"Test Item\",
    \"category_id\": $CATEGORY_ID
  }")
ITEM_ID=$(echo $ITEM | jq -r '.data.id')
echo "Created item: $ITEM_ID"

# 5. Update item
curl -s -X PUT "http://localhost:8000/items/$ITEM_ID" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "description": "Updated via workflow"
  }' | jq '.'

# 6. Get updated item
curl -s -X GET "http://localhost:8000/items/$ITEM_ID" \
  -H "Authorization: Bearer $TOKEN" | jq '.'

# 7. Cleanup
curl -s -X DELETE "http://localhost:8000/items/$ITEM_ID" \
  -H "Authorization: Bearer $TOKEN" | jq '.message'

curl -s -X DELETE "http://localhost:8000/categories/$CATEGORY_ID" \
  -H "Authorization: Bearer $TOKEN" | jq '.message'

echo "Workflow complete!"

# ============================================================================
# 9. PRETTY OUTPUT
# ============================================================================

# Use jq for pretty JSON output
curl -s -X GET "http://localhost:8000/users/1" \
  -H "Authorization: Bearer $TOKEN" | jq '.'

# Extract specific field
curl -s -X GET "http://localhost:8000/users/1" \
  -H "Authorization: Bearer $TOKEN" | jq '.data.email'

# Filter array results
curl -s -X GET "http://localhost:8000/users" \
  -H "Authorization: Bearer $TOKEN" | jq '.data[] | {id, email, created_at}'

# ============================================================================
# 10. DEBUGGING
# ============================================================================

# Show request and response headers
curl -v -X GET "http://localhost:8000/users" \
  -H "Authorization: Bearer $TOKEN"

# Save response to file
curl -s -X GET "http://localhost:8000/users" \
  -H "Authorization: Bearer $TOKEN" > users.json

# Show only response headers
curl -i -X GET "http://localhost:8000/users" \
  -H "Authorization: Bearer $TOKEN"

# Measure request time
curl -w "@curl-format.txt" -o /dev/null -s \
  -X GET "http://localhost:8000/users" \
  -H "Authorization: Bearer $TOKEN"

# ============================================================================
# TIPS
# ============================================================================

# Store token in variable for reuse
export TOKEN="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."

# Create .curlrc for default headers (optional)
# cat > ~/.curlrc
# -H "Authorization: Bearer $TOKEN"

# Use HTTP auth if needed (not recommended - use Bearer tokens instead)
# curl -u username:password http://localhost:8000/...
