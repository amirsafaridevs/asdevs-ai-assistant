<?php

declare(strict_types=1);

namespace ASDevs\AIAssistant;

use Closure;
use Exception;
use ReflectionClass;
use ReflectionParameter;

class Container
{
    /**
     * Registered bindings.
     */
    private array $bindings = [];

    /**
     * Shared instances (singletons).
     */
    private array $instances = [];

    /**
     * Bind a concrete implementation to an abstract.
     */
    public function bind(string $abstract, string|callable $concrete, bool $shared = false): void
    {
        $this->bindings[$abstract] = compact('concrete', 'shared');
    }

    /**
     * Register a singleton binding.
     */
    public function singleton(string $abstract, string|callable $concrete): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Register an existing instance as a singleton.
     */
    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * Resolve the given abstract from the container.
     */
    public function make(string $abstract): mixed
    {
        // Return existing singleton instance
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Check bindings
        if (isset($this->bindings[$abstract])) {
            $binding = $this->bindings[$abstract];
            $concrete = $binding['concrete'];
            $shared   = $binding['shared'];

            if ($concrete instanceof Closure) {
                $object = $concrete($this);
            } elseif (is_string($concrete)) {
                $object = $this->build($concrete);
            } else {
                throw new Exception(esc_html("Invalid binding for: {$abstract}"));
            }

            if ($shared) {
                $this->instances[$abstract] = $object;
            }

            return $object;
        }

        // Auto-resolve if no binding exists
        return $this->build($abstract);
    }

    /**
     * Build a concrete class with dependency injection.
     */
    private function build(string $concrete): object
    {
        try {
            $reflector = new ReflectionClass($concrete);
        } catch (\ReflectionException $e) {
            throw new Exception(esc_html("Class not found: {$concrete}"));
        }

        if (!$reflector->isInstantiable()) {
            throw new Exception(esc_html("Class is not instantiable: {$concrete}"));
        }

        $constructor = $reflector->getConstructor();

        if (is_null($constructor)) {
            return $reflector->newInstance();
        }

        $dependencies = [];

        foreach ($constructor->getParameters() as $parameter) {
            $dependency = $this->resolveParameter($parameter);
            $dependencies[] = $dependency;
        }

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Resolve a single parameter.
     */
    private function resolveParameter(ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();

        if ($type && !$type->isBuiltin()) {
            $typeName = $type->getName();
            return $this->make($typeName);
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw new Exception(esc_html("Cannot resolve parameter: \${$parameter->getName()}"));
    }

    /**
     * Check if the container has a binding or instance for the given abstract.
     */
    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }
}
