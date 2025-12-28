<?php

namespace app\Core;

class Validator
{
    private array $data;
    private array $rules;
    private array $errors = [];
    private array $resolvedIds = [];
    private ?QueryBuilder $db = null;

    public static function make(array $data, array $rules, ?QueryBuilder $db = null): self
    {
        return new self($data, $rules, $db);
    }

    private function __construct(array $data, array $rules, ?QueryBuilder $db = null)
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->db = $db ?? new QueryBuilder();
        $this->validate();
    }

    private function validate(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = explode('|', $ruleString);
            
            // Verificar si el campo es requerido
            $isRequired = in_array('required', $rules);
            
            // Si el campo no existe y no es requerido, saltar validaciones
            if (!$isRequired && !$this->fieldExists($field)) {
                continue;
            }
            
            // Si el campo existe pero está vacío y no es requerido, saltar otras validaciones
            if (!$isRequired && $this->isEmpty($field)) {
                continue;
            }
            
            foreach ($rules as $rule) {
                $this->applyRule($field, $rule);
            }
        }
    }

    private function fieldExists(string $field): bool
    {
        return array_key_exists($field, $this->data);
    }

    private function isEmpty(string $field): bool
    {
        if (!$this->fieldExists($field)) {
            return true;
        }
        
        $value = $this->data[$field];
        return $value === null || $value === '';
    }

    private function applyRule(string $field, string $rule): void
    {
        $value = $this->data[$field] ?? null;
        
        // Parsear regla con parámetros (ej: min:3, max:255, exists:profiles)
        [$ruleName, $param] = array_pad(explode(':', $rule, 2), 2, null);

        match ($ruleName) {
            'required' => $this->validateRequired($field, $value),
            'email'    => $this->validateEmail($field, $value),
            'min'      => $this->validateMin($field, $value, (int) $param),
            'max'      => $this->validateMax($field, $value, (int) $param),
            'numeric'  => $this->validateNumeric($field, $value),
            'uuid'     => $this->validateUuid($field, $value),
            'in'       => $this->validateIn($field, $value, explode(',', $param ?? '')),
            'exists'   => $this->validateExists($field, $value, $param),
            default    => null,
        };
    }

    private function validateRequired(string $field, $value): void
    {
        if ($value === null || $value === '' || (is_string($value) && trim($value) === '')) {
            $this->errors[$field][] = "El campo {$field} es requerido.";
        }
    }

    private function validateEmail(string $field, $value): void
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = "El campo {$field} debe ser un email válido.";
        }
    }

    private function validateMin(string $field, $value, int $min): void
    {
        if ($value !== null && $value !== '') {
            $length = mb_strlen((string) $value);
            if ($length < $min) {
                $this->errors[$field][] = "El campo {$field} debe tener al menos {$min} caracteres.";
            }
        }
    }

    private function validateMax(string $field, $value, int $max): void
    {
        if ($value !== null && $value !== '') {
            $length = mb_strlen((string) $value);
            if ($length > $max) {
                $this->errors[$field][] = "El campo {$field} no debe exceder {$max} caracteres.";
            }
        }
    }

    private function validateNumeric(string $field, $value): void
    {
        if ($value !== null && $value !== '' && !is_numeric($value)) {
            $this->errors[$field][] = "El campo {$field} debe ser numérico.";
        }
    }

    private function validateIn(string $field, $value, array $allowedValues): void
    {
        if ($value !== null && $value !== '' && !in_array($value, $allowedValues, true)) {
            $allowed = implode(', ', $allowedValues);
            $this->errors[$field][] = "El campo {$field} debe ser uno de los siguientes valores: {$allowed}.";
        }
    }

    private function validateUuid(string $field, $value): void
    {
        if ($value !== null && $value !== '') {
            $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
            if (!preg_match($pattern, $value)) {
                $this->errors[$field][] = "El campo {$field} debe ser un UUID válido.";
            }
        }
    }

    /**
     * Validar que el UUID existe en una tabla y resolver el ID entero
     * Uso: 'profile_id' => 'required|uuid|exists:profile'
     */
    private function validateExists(string $field, $value, ?string $table): void
    {
        if (!$table || $value === null || $value === '') {
            return;
        }

        try {
            $result = $this->db->select(
                table: $table,
                columns: ['id'],
                where: 'uuid = :uuid',
                params: ['uuid' => $value]
            );

            if (!$result->success || empty($result->data)) {
                $this->errors[$field][] = "El registro especificado en {$field} no existe.";
            } else {
                // Guardar el ID resuelto para usarlo después
                $this->resolvedIds[$field] = (int) $result->data[0]['id'];
            }
        } catch (\Exception $e) {
            $this->errors[$field][] = "Error al validar {$field}: " . $e->getMessage();
        }
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function getErrorResponse(): array
    {
        return [
            'success' => false,
            'message' => 'Campos Inválidos',
            'campos' => $this->errors
        ];
    }

    /**
     * Obtener los IDs resueltos desde los UUIDs validados con exists
     */
    public function getResolvedIds(): array
    {
        return $this->resolvedIds;
    }

    /**
     * Obtener un ID resuelto específico
     */
    public function getResolvedId(string $field): ?int
    {
        return $this->resolvedIds[$field] ?? null;
    }
}