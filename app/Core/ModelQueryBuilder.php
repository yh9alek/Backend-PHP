<?php

namespace app\Core;
use app\Models\BaseModel;
use Closure;

class ModelQueryBuilder
{
    protected BaseModel $model;
    protected array $with = [];
    protected array $where = [];
    protected array $orderBy = [];
    protected array $whereHas = [];

    protected ?int $limit = null;
    protected ?int $offset = null;

    public function __construct(BaseModel $model)
    {
        $this->model = $model;
    }

    /**
     * Establece el número máximo de registros a devolver (LIMIT).
     */
    public function limit(int $count): self
    {
        $this->limit = $count;
        return $this;
    }

    /**
     * Un alias para el método limit().
     */
    public function take(int $count): self
    {
        return $this->limit($count);
    }

    /**
     * Establece el número de registros a saltar (OFFSET).
     */
    public function offset(int $start): self
    {
        $this->offset = $start;
        return $this;
    }

    public function where(string $column, string $operator, $value): self
    {
        $this->where[] = ['type' => 'basic', 'column' => $column, 'operator' => $operator, 'value' => $value];
        return $this;
    }

    /**
     * Añade una condición de existencia de relación (subconsulta EXISTS).
     */
    public function whereHas(string $relationName, Closure $callback): self
    {
        $this->whereHas[] = compact('relationName', 'callback');
        return $this;
    }

    /**
     * Añade una condición WHERE IN a la consulta.
     */
    public function whereIn(string $column, array $values): self
    {
        $this->where[] = ['type' => 'in', 'column' => $column, 'values' => $values];
        return $this;
    }

    public function with(array $relations): self
    {
        $this->with = $relations;
        return $this;
    }

    public function find(int|string $id): ?BaseModel
    {
        // Llama al find simple y luego carga relaciones.
        $model = $this->model::find($id);

        if ($model) {
            $model->load($this->with);
        }

        return $model;
    }

    /**
     * Añade una condición ORDER BY a la consulta.
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy[] = "{$column} " . strtoupper($direction);
        return $this;
    }

    public function get(): array
    {
        $mainQueryBuilder = $this->model::queryBuilder();
        $mainTable = $this->model::getTable();

        $params = [];
        $whereClauses = [];

        // 1. Construir WHERE básicos
        foreach ($this->where as $condition) {
            if ($condition['type'] === 'basic') {
                $placeholder = $condition['column'] . '_' . count($params);
                $whereClauses[] = "{$mainTable}.{$condition['column']} {$condition['operator']} :{$placeholder}";
                $params[$placeholder] = $condition['value'];
            }

            if ($condition['type'] === 'in') {
                if (empty($condition['values'])) {
                    // Si el array de 'in' está vacío, la condición debe ser siempre falsa.
                    $whereClauses[] = '0=1';
                    continue;
                }
                // Creamos un placeholder único para cada valor del IN
                $placeholders = [];
                foreach ($condition['values'] as $i => $value) {
                    $placeholder = $condition['column'] . '_' . $i;
                    $placeholders[] = ':' . $placeholder;
                    $params[$placeholder] = $value;
                }
                $whereClauses[] = "{$mainTable}.{$condition['column']} IN (" . implode(', ', $placeholders) . ")";
            }
        }

        // 2. Construir subconsultas WHERE EXISTS
        foreach ($this->whereHas as $hasCondition) {
            $whereClauses[] = $this->buildExistsSubQuery(
                $hasCondition['relationName'],
                $hasCondition['callback'],
                $params // Pasado por referencia para que se actualice
            );
        }

        $extras = '';
        if (!empty($this->orderBy)) {
            $extras .= 'ORDER BY ' . implode(', ', $this->orderBy);
        }

        // Añadimos LIMIT y OFFSET a la cláusula de extras.
        // Importante castear a (int) para prevenir inyección SQL.
        if ($this->limit !== null) {
            $extras .= ' LIMIT ' . (int)$this->limit;
        }

        if ($this->offset !== null) {
            $extras .= ' OFFSET ' . (int)$this->offset;
        }

        $response = $mainQueryBuilder->select(
            table: $mainTable,
            where: implode(' AND ', $whereClauses),
            params: $params,
            extras: $extras
        );

        $models = [];
        if ($response->success) {
            foreach ($response->data as $item) {
                $modelClass = get_class($this->model);
                $model = new $modelClass();
                $model->hydrate($item);
                $models[] = $model;
            }
        }

        if (!empty($models) && !empty($this->with)) {
            $models[0]->loadRelationsForCollection($models, $this->with);
        }

        return $models;
    }

    /**
     * Helper que construye la subconsulta SQL para una condición whereHas.
     */
    private function buildExistsSubQuery(string $relationName, Closure $callback, array &$params): string
    {
        // 1. Obtener detalles
        $relation = $this->model->$relationName();
        /** @var BaseModel $relatedModelClass */
        $relatedModelClass = $relation['model'];
        $relatedTable = $relatedModelClass::getTable();

        // 2. Crear y popular el sub-builder
        $subQueryBuilder = $relatedModelClass::query();
        $callback($subQueryBuilder);

        // 3. --- CONSTRUCCIÓN DE WHERE ---
        $subWhereClauses = [];
        foreach ($subQueryBuilder->where as $condition) {
            $placeholder = 'sub_' . str_replace('.', '_', $condition['column']) . '_' . count($params);

            // Comprueba si el nombre de la columna ya contiene un punto (ej. 'tabla.columna')
            if (str_contains($condition['column'], '.')) {
                // Si ya lo tiene, usarlo directamente.
                $subWhereClauses[] = "{$condition['column']} {$condition['operator']} :{$placeholder}";
            } else {
                // Si no, se añade el prefijo de la tabla relacionada por defecto.
                $subWhereClauses[] = "{$relatedTable}.{$condition['column']} {$condition['operator']} :{$placeholder}";
            }
            $params[$placeholder] = $condition['value'];
        }

        // 4. Construir la correlación y la consulta final
        $mainTable = $this->model::getTable();
        $correlation = '';
        $subQueryJoins = ''; // Inicializar la variable

        switch ($relation['type']) {
            case 'belongsTo':
                $correlation = "{$mainTable}.{$relation['foreignKey']} = {$relatedTable}.{$relation['ownerKey']}";
                break;
            case 'hasMany':
                $correlation = "{$mainTable}.{$relation['localKey']} = {$relatedTable}.{$relation['foreignKey']}";
                break;
            case 'belongsToMany':
                $pivotTable = $relation['pivotTable'];
                $correlation = "{$pivotTable}.{$relation['foreignPivotKey']} = {$mainTable}.{$relation['localKey']}";
                $subQueryJoins = "INNER JOIN {$pivotTable} ON {$pivotTable}.{$relation['relatedPivotKey']} = {$relatedTable}.{$relation['relatedKey']}";
                break;
            default:
                return "1=0";
        }

        if ($correlation) {
            $subWhereClauses[] = $correlation;
        }

        // Si no hay ninguna cláusula WHERE (ni del callback ni de la correlación), la subconsulta no es válida.
        if (empty($subWhereClauses)) {
            return "1=0";
        }

        $sql = "EXISTS (SELECT 1 FROM {$relatedTable} {$subQueryJoins} WHERE " . implode(' AND ', $subWhereClauses) . ")";

        return $sql;
    }
}
