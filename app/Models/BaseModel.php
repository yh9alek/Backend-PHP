<?php

namespace app\Models;

use app\Core\ModelQueryBuilder;
use app\Core\QueryBuilder;
use app\Helpers\Uuid;
use Closure;

abstract class BaseModel
{
    // Propiedades que cada modelo hijo debe definir
    protected static string $table;
    protected static string $primaryKey = 'id';
    protected static bool $useUuid = true; // Habilitar UUIDs por defecto
    public ?string $uuid = null;

    protected array $fillable = [];

    // Una única instancia del QueryBuilder para todos los modelos
    protected static ?QueryBuilder $queryBuilder = null;

    /**
     *  Encuentra un registro por UUID
     */
    public static function findByUuid(string $uuid): ?static
    {
        if (!Uuid::isValid($uuid)) {
            return null;
        }

        $response = static::db()->select(
            table: static::$table,
            where: 'uuid = :uuid',
            params: ['uuid' => $uuid],
            extras: 'LIMIT 1'
        );

        if ($response->success && !empty($response->data)) {
            $model = new static();
            $model->hydrate($response->data[0]);
            return $model;
        }
        return null;
    }

    /**
     *  Inicia una query filtrando por UUID
     */
    public static function whereUuid(string $uuid): ModelQueryBuilder
    {
        return static::query()->where('uuid', '=', $uuid);
    }

    /**
     *  Elimina un registro por UUID
     */
    public static function deleteByUuid(string $uuid): bool
    {
        if (!Uuid::isValid($uuid)) {
            return false;
        }

        $response = static::db()->delete(
            static::$table, 
            'uuid = :uuid', 
            ['uuid' => $uuid]
        );
        return $response->success;
    }

    /**
     * Encuentra un registro por su PK o UUID
     */
    public static function find(int|string $identifier): ?static
    {
        // Si es UUID, usar findByUuid()
        if (is_string($identifier) && Uuid::isValid($identifier)) {
            return static::findByUuid($identifier);
        }

        // Si es ID numérico, usar búsqueda tradicional
        $response = static::db()->select(
            table: static::$table,
            where: static::$primaryKey . ' = :id',
            params: ['id' => $identifier],
            extras: 'LIMIT 1'
        );

        if ($response->success && !empty($response->data)) {
            $model = new static();
            $model->hydrate($response->data[0]);
            return $model;
        }
        return null;
    }

    /**
     * Obtiene todos los registros de la tabla.
     */
    public static function all(): array
    {
        $response = static::db()->select(static::$table);
        $collection = [];
        if ($response->success && !empty($response->data)) {
            foreach ($response->data as $item) {
                $model = new static();
                $model->hydrate($item);
                $collection[] = $model;
            }
        }
        return $collection;
    }

    /**
     * Inicia una nueva consulta con una condición WHERE IN.
     */
    public static function whereIn(string $column, array $values): ModelQueryBuilder
    {
        return static::query()->whereIn($column, $values);
    }

    /**
     *  Genera UUID automáticamente al guardar cambios
     */
    public function save(): bool
    {
        $attributes = $this->getAttributes();
        $primaryKeyProperty = $this->toCamelCase(static::$primaryKey);

        // AUTO-GENERAR UUID si no existe
        if (static::$useUuid && empty($this->uuid)) {
            $this->uuid = Uuid::generate();
            $attributes['uuid'] = $this->uuid;
        }

        if (isset($this->$primaryKeyProperty)) {
            // --- Lógica de UPDATE ---
            $id = $this->$primaryKeyProperty;
            unset($attributes[static::$primaryKey]);

            $response = self::db()->update(
                static::$table,
                $attributes,
                static::$primaryKey . ' = :id',
                ['id' => $id]
            );
        } else {
            // --- Lógica de INSERT ---
            $response = self::db()->insert(static::$table, $attributes);
            if ($response->success) {
                $this->$primaryKeyProperty = (int)$response->data;
            }
        }

        return $response->success;
    }

    /**
     * Elimina un registro de la BBDD por su ID.
     */
    public static function delete(int|string $id): bool
    {
        $response = static::db()->delete(
            static::$table, 
            static::$primaryKey . ' = :id', 
            ['id' => $id]
        );
        return $response->success;
    }

    # --- LÓGICA DE RELACIONES (EAGER LOADING) --- #

    /**
     * Inicia una nueva consulta para el modelo con Eager Loading.
     */
    public static function with(array $relations): ModelQueryBuilder
    {
        return (new ModelQueryBuilder(new static()))->with($relations);
    }

    /**
     * Inicia una consulta para filtrar por la existencia de una relación.
     */
    public static function whereHas(string $relationName, Closure $callback): ModelQueryBuilder
    {
        return static::query()->whereHas($relationName, $callback);
    }

    /**
     * Carga las relaciones especificadas para este objeto de modelo.
     */
    public function load($relations): self
    {
        $relations = is_array($relations) ? $relations : [$relations];
        $collection = [$this];
        $this->loadRelationsForCollection($collection, $relations);
        return $this;
    }

    /**
     * Carga las relaciones para una colección de modelos.
     */
    public function loadRelationsForCollection(array &$collection, array $relations)
    {
        if (empty($collection) || empty($relations)) {
            return;
        }

        $groupedRelations = $this->parseRelations($relations);
        $this->loadGroupedRelations($collection, $groupedRelations);
    }

    private function loadGroupedRelations(array &$collection, array $groupedRelations)
    {
        if (empty($collection)) return;

        foreach ($groupedRelations as $relationName => $nested) {
            $relationDetails = $this->$relationName();
            $relatedModelClass = $relationDetails['model'];
            $relatedCollection = [];

            if ($relationDetails['type'] === 'belongsTo') {
                $foreignKey = $relationDetails['foreignKey'];
                $ownerKey = $relationDetails['ownerKey'];
                $foreignKeyProperty = $this->toCamelCase($foreignKey);
                $foreignKeys = array_unique(array_filter(array_map(fn($model) => $model->$foreignKeyProperty, $collection)));

                if (!empty($foreignKeys)) {
                    $relatedCollection = $relatedModelClass::whereIn($ownerKey, $foreignKeys)->get();
                }

                $relatedMap = [];
                foreach ($relatedCollection as $related) {
                    $relatedMap[$related->$ownerKey] = $related;
                }
                foreach ($collection as $model) {
                    $model->$relationName = $relatedMap[$model->$foreignKeyProperty] ?? null;
                }
            }

            if ($relationDetails['type'] === 'hasMany') {
                $foreignKey = $relationDetails['foreignKey'];
                $localKey = $relationDetails['localKey'];
                $localKeyProperty = $this->toCamelCase($localKey);
                $localKeys = array_unique(array_map(fn($model) => $model->$localKeyProperty, $collection));

                if (!empty($localKeys)) {
                    $relatedCollection = $relatedModelClass::whereIn($foreignKey, $localKeys)->get();
                }

                $relatedMap = [];
                foreach ($relatedCollection as $related) {
                    $relatedMap[$related->$foreignKey][] = $related;
                }

                foreach ($collection as $model) {
                    $model->$relationName = $relatedMap[$model->$localKeyProperty] ?? [];
                }
            }

            if ($relationDetails['type'] === 'belongsToMany') {
                $relatedModelClass = $relationDetails['model'];
                $pivotTable = $relationDetails['pivotTable'];
                $foreignPivotKey = $relationDetails['foreignPivotKey'];
                $relatedPivotKey = $relationDetails['relatedPivotKey'];
                $localKey = $relationDetails['localKey'];
                $relatedKey = $relationDetails['relatedKey'];
                $localKeyProperty = $this->toCamelCase($localKey);
                $localKeys = array_unique(array_map(fn($model) => $model->$localKeyProperty, $collection));

                if (!empty($localKeys)) {
                    $placeholders = implode(',', array_fill(0, count($localKeys), '?'));
                    $sql = "SELECT {$relatedModelClass::$table}.*, {$pivotTable}.{$foreignPivotKey} AS pivot_{$foreignPivotKey}
                    FROM {$relatedModelClass::$table}
                    INNER JOIN {$pivotTable} ON {$relatedModelClass::$table}.{$relatedKey} = {$pivotTable}.{$relatedPivotKey}
                    WHERE {$pivotTable}.{$foreignPivotKey} IN ($placeholders)";

                    $stmt = static::db()->getPdo()->prepare($sql);
                    $stmt->execute($localKeys);
                    $relatedItemsRaw = $stmt->fetchAll(\PDO::FETCH_OBJ);

                    $relatedMap = [];
                    foreach ($relatedItemsRaw as $item) {
                        $relatedModel = new $relatedModelClass();
                        $relatedModel->hydrate($item);
                        $relatedMap[$item->{"pivot_{$foreignPivotKey}"}][] = $relatedModel;
                    }

                    foreach ($collection as $model) {
                        $model->$relationName = $relatedMap[$model->$localKeyProperty] ?? [];
                    }
                } else {
                    foreach ($collection as $model) {
                        $model->$relationName = [];
                    }
                }
            }

            if (!empty($nested)) {
                $subCollection = [];
                foreach ($collection as $model) {
                    if (isset($model->$relationName) && $model->$relationName !== null) {
                        $subCollection = array_merge($subCollection, is_array($model->$relationName) ? $model->$relationName : [$model->$relationName]);
                    }
                }

                $subCollection = array_unique($subCollection, SORT_REGULAR);

                if (!empty($subCollection)) {
                    (new $relatedModelClass())->loadRelationsForCollection($subCollection, array_keys($nested));
                }
            }
        }
    }

    # --- MÉTODOS INTERNOS Y HELPERS --- #

    /**
     * Inicia una nueva consulta con una condición WHERE.
     */
    public static function where(string $column, string $operator, $value): ModelQueryBuilder
    {
        return (new ModelQueryBuilder(new static()))->where($column, $operator, $value);
    }

    /**
     * Inicia una nueva consulta con una condición ORDER BY.
     */
    public static function orderBy(string $column, string $direction = 'ASC'): ModelQueryBuilder
    {
        return (new ModelQueryBuilder(new static()))->orderBy($column, $direction);
    }

    /**
     * Inicia una consulta estableciendo un límite de resultados.
     */
    public static function limit(int $count): ModelQueryBuilder
    {
        return static::query()->limit($count);
    }

    /**
     * Alias para limit().
     */
    public static function take(int $count): ModelQueryBuilder
    {
        return static::query()->take($count);
    }

    /**
     * Inicia una consulta estableciendo un punto de inicio (offset).
     */
    public static function offset(int $start): ModelQueryBuilder
    {
        return static::query()->offset($start);
    }

    /**
     * Convierte una cadena de snake_case a camelCase.
     */
    private function toCamelCase(string $snakeCaseString): string
    {
        return lcfirst(str_replace('_', '', ucwords($snakeCaseString, '_')));
    }

    /**
     * Inicializa el QueryBuilder si aún no existe.
     */
    protected static function db(): QueryBuilder
    {
        if (static::$queryBuilder === null) {
            static::$queryBuilder = new QueryBuilder();
        }
        return static::$queryBuilder;
    }

    /**
     * Rellena las propiedades públicas del objeto con datos.
     */
    public function hydrate(object|array $data): void
    {
        foreach ($data as $key => $value) {
            $camelCaseKey = lcfirst(str_replace('_', '', ucwords($key, '_')));
            if (property_exists($this, $camelCaseKey)) {
                $this->{$camelCaseKey} = $value;
            }
        }
    }

    /**
     * Obtiene un array asociativo con las propiedades públicas del modelo.
     */
    protected function getAttributes(): array
    {
        $attributes = [];

        foreach ($this->fillable as $fillableAttribute) {
            $property = $this->toCamelCase($fillableAttribute);

            if (property_exists($this, $property) && isset($this->$property)) {
                $attributes[$fillableAttribute] = $this->$property;
            }
        }

        return $attributes;
    }

    /**
     * Wrapper público para obtener los atributos.
     */
    public function getAttributesForInsert(): array
    {
        return $this->getAttributes();
    }

    /**
     * Asignación masiva de atributos (Mass Assignment)
     * 
     * @param array $attributes Atributos en snake_case
     * @return self
     */
    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            // Convertir snake_case a camelCase
            $property = $this->toCamelCase($key);
            
            // Solo asignar si la propiedad existe y el atributo está en fillable
            if (in_array($key, $this->fillable) && property_exists($this, $property)) {
                $this->$property = $value;
            }
        }
        
        return $this;
    }

    /**
     * Actualiza solo los atributos proporcionados (merge)
     * Similar a fill() pero respeta valores existentes si no se proporcionan nuevos
     * 
     * @param array $attributes Atributos en snake_case
     * @return self
     */
    public function merge(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            $property = $this->toCamelCase($key);
            
            // Solo asignar si:
            // 1. El atributo está en fillable
            // 2. La propiedad existe
            // 3. El valor no es null
            if (in_array($key, $this->fillable) && 
                property_exists($this, $property) && 
                $value) {

                if(intval($value)) {
                    $value = (int) $value;
                }

                $this->$property = $value;
            }
        }
        
        return $this;
    }

    /**
     * Crea o actualiza un modelo (upsert simplificado)
     * 
     * @param array $attributes Atributos del modelo
     * @return static
     */
    public static function createOrUpdate(array $attributes): static
    {
        $model = new static();
        $model->fill($attributes);
        $model->save();
        return $model;
    }

    /**
     * Helper para convertir array de relaciones con notación de punto a array anidado.
     */
    private function parseRelations(array $relations): array
    {
        $parsed = [];
        foreach ($relations as $relation) {
            $keys = explode('.', $relation);
            $temp = &$parsed;
            foreach ($keys as $key) {
                if (!isset($temp[$key])) {
                    $temp[$key] = [];
                }
                $temp = &$temp[$key];
            }
        }
        return $parsed;
    }

    public static function getTable(): string
    {
        return static::$table;
    }

    /**
     * Inicia una consulta fluida para el modelo.
     */
    public static function query(): ModelQueryBuilder
    {
        return new ModelQueryBuilder(new static());
    }

    /**
     * Obtiene el constructor de consultas de bajo nivel (QueryBuilder).
     */
    public static function queryBuilder(): QueryBuilder
    {
        return static::db();
    }
}