<?php

namespace src\Services;

/**
 * Route attribute — define a route directly on a controller method.
 *
 * Usage:
 *   #[Route('/path', methods: ['GET'])]
 *   #[Route('/path', methods: ['GET', 'POST'], name: 'route_name', authRequired: true)]
 *
 * @param string       $path         URL path to match (e.g. '/login')
 * @param string|array $methods      Allowed HTTP methods (default: ['GET'])
 * @param string       $name         Optional route name
 * @param bool         $authRequired Redirect to home if user is not authenticated
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Route
{
    public readonly array $methods;

    public function __construct(
        public readonly string $path,
        string|array $methods = ['GET'],
        public readonly string $name = '',
        public readonly bool $authRequired = false,
    ) {
        $this->methods = array_map('strtoupper', (array) $methods);
    }
}
