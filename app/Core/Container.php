<?php

namespace app\Core;

/**
 * Clase para crear contenedor de dependencias
 */
class Container {
    private array $bindings = [];
    private array $instances = []; // Cache de singletons

    public function singleton(string $key, callable $resolver): void {
        $this->bindings[$key] = ['resolver' => $resolver, 'singleton' => true];
    }

    public function set(string $key, callable $resolver): void {
        $this->bindings[$key] = ['resolver' => $resolver, 'singleton' => false];
    }

    public function get(string $key): mixed {
        if (!isset($this->bindings[$key])) {
            throw new \Exception("No binding found for {$key}");
        }

        $binding = $this->bindings[$key];

        // Si es singleton y ya existe, retornar instancia cacheada
        if ($binding['singleton'] && isset($this->instances[$key])) {
            return $this->instances[$key];
        }

        $instance = $binding['resolver']($this);

        // Cachear si es singleton
        if ($binding['singleton']) {
            $this->instances[$key] = $instance;
        }

        return $instance;
    }
}