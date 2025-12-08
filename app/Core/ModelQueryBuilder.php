<?php

namespace app\Core;

use app\Helpers\Uuid;

/**
 * Query Builder fluido para modelos con soporte de Eager Loading.
 */
class ModelQueryBuilder
{
    private $model;
    private array $relations = [];
    private array $wheres = [];
    private array $whereIns = [];
    private ?string $orderByColumn = null;
    private ?string $orderByDirection = null;
    private ?int $limitValue = null;
    private ?int $offsetValue = null;
    private array $whereHasConditions = [];

    public function __construct($model)
    {
        $this->model = $model;
    }

    /**
     * Especifica las relaciones a cargar (Eager Loading).
     */
    public function with(array $relations): self
    {
        $this->relations = array_merge($this->relations, $relations);
        return $this;
    }

    /**
     *  Agrega condición WHERE para UUID
     */
    public function whereUuid(string $uuid): self
    {
        if (!Uuid::isValid($uuid)) {
            // Si el UUID es inválido, agregamos condición imposible
            // para que no devuelva resultados
            return $this->where('uuid', '=', 'INVALID_UUID_WILL_RETURN_NOTHING');
        }
        
        return $this->where('uuid', '=', $uuid);
    }

    /**
     * Agrega una condición WHERE.
     */
    public function where(string $column, string $operator, $value): self
    {
        $this->wheres[] = compact('column', 'operator', 'value');
        return $this;
    }

    /**
     * Agrega una condición WHERE IN.
     */
    public function whereIn(string $column, array $values): self
    {
        $this->whereIns[] = compact('column', 'values');
        return $this;
    }

    /**
     * Filtra por la existencia de una relación.
     */
    public function whereHas(string $relationName, \Closure $callback): self
    {
        $this->whereHasConditions[] = compact('relationName', 'callback');
        return $this;
    }

    /**
     * Establece el ORDER BY.
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderByColumn = $column;
        $this->orderByDirection = strtoupper($direction);
        return $this;
    }

    /**
     * Establece el LIMIT.
     */
    public function limit(int $count): self
    {
        $this->limitValue = $count;
        return $this;
    }

    /**
     * Alias para limit().
     */
    public function take(int $count): self
    {
        return $this->limit($count);
    }

    /**
     * Establece el OFFSET.
     */
    public function offset(int $start): self
    {
        $this->offsetValue = $start;
        return $this;
    }

    /**
     * Ejecuta la consulta y devuelve una colección de modelos.
     */
    public function get(): array
    {
        $tableName = $this->model::getTable();
        $whereClause = '';
        $params = [];

        // Construir WHERE clauses
        if (!empty($this->wheres)) {
            $conditions = [];
            foreach ($this->wheres as $index => $where) {
                $paramName = "param_{$index}";
                $conditions[] = "{$where['column']} {$where['operator']} :{$paramName}";
                $params[$paramName] = $where['value'];
            }
            $whereClause = implode(' AND ', $conditions);
        }

        // Construir WHERE IN clauses
        if (!empty($this->whereIns)) {
            $inConditions = [];
            foreach ($this->whereIns as $index => $whereIn) {
                $column = $whereIn['column'];
                $values = $whereIn['values'];
                
                if (empty($values)) {
                    continue;
                }

                $placeholders = [];
                foreach ($values as $i => $val) {
                    $paramName = "in_{$index}_{$i}";
                    $placeholders[] = ":{$paramName}";
                    $params[$paramName] = $val;
                }

                $inConditions[] = "{$column} IN (" . implode(',', $placeholders) . ")";
            }

            if (!empty($inConditions)) {
                $whereClause = empty($whereClause) 
                    ? implode(' AND ', $inConditions)
                    : $whereClause . ' AND ' . implode(' AND ', $inConditions);
            }
        }

        // Construir extras (ORDER BY, LIMIT, OFFSET)
        $extras = '';
        if ($this->orderByColumn) {
            $extras .= " ORDER BY {$this->orderByColumn} {$this->orderByDirection}";
        }
        if ($this->limitValue !== null) {
            $extras .= " LIMIT {$this->limitValue}";
        }
        if ($this->offsetValue !== null) {
            $extras .= " OFFSET {$this->offsetValue}";
        }

        // Ejecutar query
        $queryBuilder = $this->model::queryBuilder();
        $response = $queryBuilder->select(
            table: $tableName,
            where: $whereClause ?: null,
            params: $params,
            extras: trim($extras)
        );

        $collection = [];
        if ($response->success && !empty($response->data)) {
            foreach ($response->data as $item) {
                $modelInstance = new ($this->model::class)();
                $modelInstance->hydrate($item);
                $collection[] = $modelInstance;
            }
        }

        // Aplicar Eager Loading si hay relaciones especificadas
        if (!empty($this->relations) && !empty($collection)) {
            $this->model->loadRelationsForCollection($collection, $this->relations);
        }

        // Aplicar whereHas después de cargar las relaciones
        if (!empty($this->whereHasConditions)) {
            $collection = $this->applyWhereHasFilters($collection);
        }

        return $collection;
    }

    /**
     * Devuelve el primer resultado o null.
     */
    public function first(): ?object
    {
        $results = $this->limit(1)->get();
        return $results[0] ?? null;
    }

    /**
     * Cuenta los resultados.
     */
    public function count(): int
    {
        return count($this->get());
    }

    /**
     * Filtra la colección según las condiciones whereHas.
     */
    private function applyWhereHasFilters(array $collection): array
    {
        foreach ($this->whereHasConditions as $condition) {
            $relationName = $condition['relationName'];
            $callback = $condition['callback'];

            $collection = array_filter($collection, function ($model) use ($relationName, $callback) {
                $relatedItems = $model->$relationName ?? null;

                if ($relatedItems === null) {
                    return false;
                }

                if (is_array($relatedItems)) {
                    foreach ($relatedItems as $relatedItem) {
                        $dummyBuilder = new self($relatedItem);
                        $callback($dummyBuilder);
                        
                        $filtered = $dummyBuilder->filterSingleModel($relatedItem);
                        if ($filtered) {
                            return true;
                        }
                    }
                    return false;
                } else {
                    $dummyBuilder = new self($relatedItems);
                    $callback($dummyBuilder);
                    return $dummyBuilder->filterSingleModel($relatedItems);
                }
            });

            $collection = array_values($collection);
        }

        return $collection;
    }

    /**
     * Filtra un modelo individual según las condiciones del builder.
     */
    private function filterSingleModel($model): bool
    {
        foreach ($this->wheres as $where) {
            $column = $where['column'];
            $operator = $where['operator'];
            $value = $where['value'];

            $property = $this->toCamelCase($column);

            if (!property_exists($model, $property)) {
                return false;
            }

            $modelValue = $model->$property;

            $match = match ($operator) {
                '=' => $modelValue == $value,
                '!=' => $modelValue != $value,
                '>' => $modelValue > $value,
                '<' => $modelValue < $value,
                '>=' => $modelValue >= $value,
                '<=' => $modelValue <= $value,
                default => false,
            };

            if (!$match) {
                return false;
            }
        }

        return true;
    }

    /**
     * Convierte snake_case a camelCase.
     */
    private function toCamelCase(string $snakeCaseString): string
    {
        return lcfirst(str_replace('_', '', ucwords($snakeCaseString, '_')));
    }
}