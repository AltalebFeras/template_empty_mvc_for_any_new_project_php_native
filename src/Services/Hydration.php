<?php

namespace App\Services;

/**
 * Trait Hydration
 *
 * Provides automatic hydration of object properties using setter methods.
 * Also supports custom serialization and unserialization.
 *
 * Note: values are NOT sanitized here — sanitize output in views using
 * Validator::escape() or htmlspecialchars() when rendering user data.
 */

trait Hydration
{


    public function __construct(array $data = [])
    {
        $this->hydrate($data);
    }

     private function hydrate(array $data): void
    {
        foreach ($data as $key => $value) {
            $parts = explode('_', $key);
            $Parts = array_map('ucfirst', $parts);
            $setter = "set" . implode('', $Parts);

            if (method_exists($this, $setter)) {
                $this->$setter($value);
            }
        }
    }
    public function __set(string $name, mixed $value): void
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
