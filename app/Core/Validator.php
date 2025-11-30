<?php

namespace app\Core;

class Validator
{
    /**
     * @var array Los datos que se están validando.
     */
    private array $data;

    /**
     * @var array Las reglas de validación.
     */
    private array $rules;

    /**
     * @var array Almacena los resultados de la validación (true/false para cada campo).
     */
    private array $results = [];

    /**
     * @var bool Indica si la validación ha fallado.
     */
    private bool $failed = false;

    private function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->validate();
    }

    /**
     * Método estático para crear una nueva instancia y ejecutar la validación.
     *
     * @param array $data Los datos del request (ej. $_POST).
     * @param array $rules Un array de nombres de campo que son requeridos.
     * @return self
     */
    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    /**
     * Ejecuta el proceso de validación.
     */
    private function validate(): void
    {
        // Por ahora, solo tenemos la regla 'required'.
        // Iteramos sobre las reglas que nos pasaron.
        foreach ($this->rules as $field) {
            // Verificamos si el campo existe en los datos y no está vacío.
            // trim() elimina espacios en blanco al principio y al final.
            if (!isset($this->data[$field]) || trim((string) $this->data[$field]) === '') {
                // Si falla, lo marcamos como false.
                $this->results[$field] = false;
                $this->failed = true;
            }
        }
    }

    /**
     * Comprueba si la validación ha fallado.
     *
     * @return bool
     */
    public function fails(): bool
    {
        return $this->failed;
    }

    /**
     * Genera el array de respuesta de error en el formato que necesitas.
     *
     * @return array
     */
    public function getErrorResponse(): array
    {
        return [
            'msg'      => 'Datos incompletos o inválidos.',
            'inputs'   => $this->results
        ];
    }
}