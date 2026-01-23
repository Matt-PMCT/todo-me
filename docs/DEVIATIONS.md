# Intentional Deviations from Conventions

This document records intentional design decisions that differ from common API conventions. These are not bugs but deliberate choices made for specific reasons.

## 1. Meta Field Naming: `requestId` (camelCase)

**Standard Convention:** Many APIs use `request_id` (snake_case) in response metadata.

**Our Choice:** We use `requestId` (camelCase) for consistency with our JSON response format.

**Reason:** All JSON fields in our API use camelCase for consistency. Mixing snake_case for meta fields would be inconsistent.

**Example:**
```json
{
  "success": true,
  "data": {...},
  "meta": {
    "requestId": "550e8400-e29b-41d4-a716-446655440000",
    "timestamp": "2024-01-15T10:30:00Z"
  }
}
```

## 2. DELETE Returns 200 with Undo Token (not 204)

**Standard Convention:** RESTful DELETE typically returns `204 No Content`.

**Our Choice:** DELETE returns `200 OK` with response body containing:
- The archived/deleted resource
- An undo token for reversing the operation

**Reason:**
1. Our DELETE operation archives resources (soft delete) rather than permanently deleting
2. The undo system requires returning a token that the client can use to reverse the operation
3. Returning the archived resource confirms what was deleted and its final state

**Example:**
```json
{
  "success": true,
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "name": "Archived Project",
    "isArchived": true
  },
  "meta": {
    "undoToken": "abc123...",
    "undoExpiresIn": 60
  }
}
```

## 3. UUID Primary Keys (not BIGINT)

**Standard Convention:** Many APIs use auto-incrementing BIGINT primary keys.

**Our Choice:** All entities use UUID v4 primary keys.

**Reasons:**
1. **Security:** UUIDs don't expose entity counts or creation order
2. **Distributed Systems:** UUIDs can be generated client-side without coordination
3. **Merge Safety:** No ID conflicts when merging data from multiple sources
4. **URL Safety:** UUIDs work well in URLs without encoding

**Trade-offs:**
- Larger storage (16 bytes vs 8 bytes)
- Slightly slower index lookups
- Accepted as worthwhile for the benefits above

## 4. Ownership Interface: `getOwner()` (not `getUser()`)

**Standard Convention:** Some multi-tenant systems use `getUser()` for the owning user.

**Our Choice:** All user-owned entities implement `UserOwnedInterface` with `getOwner()` method.

**Reasons:**
1. **Semantic Clarity:** "Owner" clearly indicates possession/authorization semantics
2. **Distinction from "User":** Tasks could theoretically have an "assignee" user that differs from the owner
3. **Interface Naming:** `UserOwnedInterface` pairs naturally with `getOwner()`/`setOwner()`

**Example:**
```php
interface UserOwnedInterface
{
    public function getOwner(): ?User;
    public function setOwner(?User $owner): static;
}
```

## 5. Error Codes

Our error codes follow a consistent naming pattern:

| Code | When Used |
|------|-----------|
| `RESOURCE_NOT_FOUND` | Entity not found (404) |
| `PERMISSION_DENIED` | User lacks permission (403) |
| `RATE_LIMIT_EXCEEDED` | Too many requests (429) |
| `VALIDATION_ERROR` | Request validation failed (422) |
| `INVALID_RECURRENCE` | Invalid recurrence configuration (400) |
| `INVALID_STATUS` | Invalid task status (400) |
| `INVALID_PRIORITY` | Priority out of range (400) |

## 6. Task Priority Range: 0-4 (not 1-5)

**Standard Convention:** Priority scales often use 1-5 or 1-10.

**Our Choice:** Priority uses 0-4 range.

**Reasons:**
1. **Zero-indexed:** Consistent with array indexing and many programming conventions
2. **Default middle:** Priority 2 is the natural default (middle of 0-4)
3. **Database efficiency:** Allows efficient bitwise operations if needed

## 7. Pagination Parameter: `per_page` (not `limit`)

**Standard Convention:** Many APIs use `limit` for page size.

**Our Choice:** We use `per_page` as the query parameter for items per page.

**Reason:** More explicit naming that clearly indicates "items per page" rather than a generic limit.

**Example:**
```
GET /api/v1/tasks?page=2&per_page=20
```
