<?php

namespace App\Services;

/**
 * Route attribute — define a route directly on a controller method.
 *
 * Usage:
 *   #[Route('/path', methods: ['GET'])]
 *   #[Route('/admin', methods: ['GET'], roles: ['admin'])]
 *   #[Route('/posts', methods: ['POST'], permissions: ['posts.write'], authRequired: true)]
 *
 * @param string       $path         URL path to match (e.g. '/login')
 * @param string|array $methods      Allowed HTTP methods (default: ['GET'])
 * @param string       $name         Optional route name
 * @param bool         $authRequired Redirect to login if user is not authenticated
 * @param array        $roles        Required roles (any match grants access)
 * @param array        $permissions  Required permissions (all must be satisfied)
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Route
{
    public readonly array $methods;

    public function __construct(
        public readonly string $path,
        string|array           $methods = ['GET'],
        public readonly string $name = '',
        public readonly bool   $authRequired = false,
        public readonly array  $roles = [],
        public readonly array  $permissions = [],
    ) {
        $this->methods = array_map('strtoupper', (array) $methods);
    }
}
