<?php

namespace app\Core;

/**
 * Clase para crear contenedor de dependencias
 */
class Container {
    private array $bindings = [];

    public function set(string $key, callable $resolver): void {
        $this->bindings[$key] = $resolver;
    }

    public function get(string $key) {

        // // --- DEPURACIÓN ---
        // echo "Intentando obtener: {$key}<br>";
        // echo "Bindings disponibles: <pre>";
        // print_r(array_keys($this->bindings));
        // echo "</pre>";
        // // ------------------

        if (!isset($this->bindings[$key])) {
            throw new \Exception("No binding found for {$key}");
        }
        $resolver = $this->bindings[$key];
        return $resolver($this);
    }
}