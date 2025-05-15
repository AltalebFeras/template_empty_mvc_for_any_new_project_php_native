<?php

namespace src\Services;

/**
 * Trait Hydration
 *
 * Provides automatic hydration of object properties using setter methods.
 * Also supports custom serialization and unserialization.
 *
 * - On construction, hydrates properties from an array using matching setters.
 * - Uses htmlspecialchars to sanitize values before setting.
 * - Supports dynamic property setting via __set().
 * - Serializes object by calling all public getters.
 * - Unserializes object by hydrating from an array.
 */

trait Hydration
{


    public function __construct(array $data = [])
    {
        $this->hydrate($data);
    }

    private function hydrate(array $data)
    {
        foreach ($data as $key => $value) {
            $method = "set" . ucfirst($key);
            if (method_exists($this, $method)) {
                $this->$method(htmlspecialchars($value));
            }
        }
    }
    public function __set($name, $value)
    {
        $this->hydrate([$name => $value]);
    }

    public function __serialize(): array
    {
        $class = new \ReflectionClass(get_class($this));
        $data = [];

        foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $methodName = $method->getName();
            if (str_starts_with($methodName, 'get')) {
                $data[$methodName] = $this->$methodName();
            }
        }

        return $data;
    }

    public function __unserialize(array $data): void
    {
        $this->hydrate($data);
    }
}
