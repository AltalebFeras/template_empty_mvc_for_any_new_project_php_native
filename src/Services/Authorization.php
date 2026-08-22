<?php

namespace App\Services;

/**
 * Role-Based & Attribute-Based Access Control (RBAC/ABAC).
 *
 * Provides a lightweight authorization layer that checks the current
 * session user's role and permissions before allowing access.
 *
 * Roles are hierarchical: admin > editor > user > guest.
 * Each role inherits all permissions from lower roles.
 *
 * Usage:
 *   if (Authorization::can('users.delete')) { ... }
 *   if (Authorization::hasRole('admin')) { ... }
 *   Authorization::requirePermission('posts.write'); // throws 403
 */
final class Authorization
{
    /**
     * Role hierarchy — higher roles include all lower-role permissions.
     * The key is the role name, the value is the hierarchy level.
     */
    private const ROLE_HIERARCHY = [
        'guest'  => 0,
        'user'   => 1,
        'editor' => 2,
        'admin'  => 3,
    ];

    /**
     * Permission map — which permissions each role has.
     * Lower roles inherit nothing; higher roles accumulate.
     *
     * Customize this per project. Wildcards (e.g., 'users.*') are supported.
     */
    private const ROLE_PERMISSIONS = [
        'guest' => [
            'pages.read',
        ],
        'user' => [
            'pages.read',
            'profile.read',
            'profile.write',
            'files.upload',
        ],
        'editor' => [
            'pages.read',
            'profile.read',
            'profile.write',
            'files.upload',
            'posts.read',
            'posts.write',
            'posts.delete',
            'users.read',
        ],
        'admin' => [
            '*', // admin has all permissions
        ],
    ];

    /**
     * Returns the current user's role from the session.
     */
    public static function currentRole(): string
    {
        return $_SESSION['role'] ?? 'guest';
    }

    /**
     * Returns the current user's ID from the session.
     */
    public static function currentUserId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    /**
     * Checks if the current user has the specified role or higher.
     *
     * @param string $requiredRole The minimum required role.
     */
    public static function hasRole(string $requiredRole): bool
    {
        $currentLevel  = self::ROLE_HIERARCHY[self::currentRole()] ?? 0;
        $requiredLevel = self::ROLE_HIERARCHY[$requiredRole] ?? PHP_INT_MAX;

        return $currentLevel >= $requiredLevel;
    }

    /**
     * Checks if the current user has a specific permission.
     *
     * Supports wildcard permissions: an admin with '*' has all permissions.
     *
     * @param string $permission Permission string (e.g., 'users.delete').
     */
    public static function can(string $permission): bool
    {
        $role        = self::currentRole();
        $permissions = self::ROLE_PERMISSIONS[$role] ?? [];

        // Wildcard check.
        if (in_array('*', $permissions, true)) {
            return true;
        }

        // Exact match.
        if (in_array($permission, $permissions, true)) {
            return true;
        }

        // Category wildcard: 'users.*' matches 'users.read', 'users.delete', etc.
        $category = explode('.', $permission)[0];
        if (in_array($category . '.*', $permissions, true)) {
            return true;
        }

        return false;
    }

    /**
     * Checks if the current user owns a specific resource.
     *
     * Used for ABAC (Attribute-Based Access Control) to prevent IDOR.
     *
     * @param int $resourceOwnerId The user ID of the resource owner.
     */
    public static function owns(int $resourceOwnerId): bool
    {
        $currentUserId = self::currentUserId();
        if ($currentUserId === null) {
            return false;
        }

        return $currentUserId === $resourceOwnerId;
    }

    /**
     * Checks if the user can access a resource (owns it OR has admin role).
     */
    public static function canAccess(int $resourceOwnerId): bool
    {
        return self::hasRole('admin') || self::owns($resourceOwnerId);
    }

    /**
     * Requires a specific permission or aborts with 403.
     *
     * @param string $permission The required permission.
     * @throws \RuntimeException If the user lacks the permission (caught by error handler → 403).
     */
    public static function requirePermission(string $permission): void
    {
        if (!self::can($permission)) {
            http_response_code(403);
            throw new \RuntimeException("Forbidden: missing permission '{$permission}'");
        }
    }

    /**
     * Requires a specific role or aborts with 403.
     *
     * @param string $role The minimum required role.
     */
    public static function requireRole(string $role): void
    {
        if (!self::hasRole($role)) {
            http_response_code(403);
            throw new \RuntimeException("Forbidden: requires role '{$role}'");
        }
    }

    /**
     * Checks if a given set of role names includes the current user's role.
     *
     * @param array<string> $allowedRoles List of allowed role names.
     */
    public static function hasAnyRole(array $allowedRoles): bool
    {
        return in_array(self::currentRole(), $allowedRoles, true);
    }
}
