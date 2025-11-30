<?php

namespace app\Models;

use app\Core\ModelQueryBuilder;
use app\Core\QueryBuilder;

use Closure;

abstract class BaseModel
{
    // Propiedades que cada modelo hijo debe definir
    protected static string $table;
    protected static string $primaryKey = 'id';

    protected array $fillable = [];

    // Una única instancia del QueryBuilder para todos los modelos
    protected static ?QueryBuilder $queryBuilder = null;

    /**
     * Inicia una consulta para filtrar por la existencia de una relación.
     *
     * @param string  $relationName El nombre del método que define la relación.
     * @param Closure $callback     Una función que recibe un ModelQueryBuilder para añadir condiciones a la subconsulta.
     * @return ModelQueryBuilder
     */
    public static function whereHas(string $relationName, Closure $callback): ModelQueryBuilder
    {
        // Llama al método query() para obtener un nuevo builder y le aplica la condición whereHas.
        return static::query()->whereHas($relationName, $callback);
    }

    /**
     * Inicia una nueva consulta para el modelo.
     * Este es el nuevo punto de entrada para el Eager Loading.
     *
     * @param array $relations
     * @return ModelQueryBuilder
     */
    public static function with(array $relations): ModelQueryBuilder
    {
        return (new ModelQueryBuilder(new static()))->with($relations);
    }

    /**
     * Encuentra un registro por su PK.
     *
     * @param int|string $id
     * @return static|null
     */
    public static function find(int|string $id): ?static
    {
        $response = static::db()->select(
            table: static::$table,
            where: static::$primaryKey . ' = :id',
            params: ['id' => $id],
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
     * Devuelve un array de instancias del modelo.
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
     * Guarda el registro actual en la BBDD.
     * Decide si debe ser un INSERT (si no tiene ID) o un UPDATE (si tiene ID).
     */
    public function save(): bool
    {
        $attributes = $this->getAttributes();

        $primaryKeyProperty = $this->toCamelCase(static::$primaryKey);

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
                // Asignamos el nuevo ID a la propiedad camelCase correcta.
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
        $response = static::db()->delete(static::$table, static::$primaryKey . ' = :id', ['id' => $id]);
        return $response->success;
    }

    # --- LÓGICA DE RELACIONES (EAGER LOADING) --- #

    /**
     * Carga las relaciones especificadas para este objeto de modelo.
     *
     * @param array|string $relations
     * @return $this
     */
    public function load($relations): self
    {
        $relations = is_array($relations) ? $relations : [$relations];

        // 1. Creamos una variable que contenga el array.
        $collection = [$this];

        // 2. Pasamos la variable por referencia.
        $this->loadRelationsForCollection($collection, $relations);

        return $this;
    }

    /**
     * Carga las relaciones para una colección de modelos.
     * Este es el método más importante para evitar el problema N+1.
     *
     * @param array $collection La colección de modelos.
     * @param array $relations  Las relaciones a cargar.
     */
    public function loadRelationsForCollection(array &$collection, array $relations)
    {
        if (empty($collection) || empty($relations)) {
            return;
        }

        // Agrupar relaciones por nivel para procesarlas (ej. 'perfil', 'perfil.permisos')
        $groupedRelations = $this->parseRelations($relations);

        $this->loadGroupedRelations($collection, $groupedRelations);
    }

    private function loadGroupedRelations(array &$collection, array $groupedRelations)
    {
        if (empty($collection)) return;

        foreach ($groupedRelations as $relationName => $nested) {
            // Obtiene la definición de la relación del modelo.
            $relationDetails = $this->$relationName();
            $relatedModelClass = $relationDetails['model'];
            $relatedCollection = [];

            // --- LÓGICA PARA CADA TIPO DE RELACIÓN ---

            if ($relationDetails['type'] === 'belongsTo') {
                $foreignKey = $relationDetails['foreignKey'];
                $ownerKey = $relationDetails['ownerKey'];

                $foreignKeyProperty = $this->toCamelCase($foreignKey);
                $foreignKeys = array_unique(array_filter(array_map(fn($model) => $model->$foreignKeyProperty, $collection)));

                if (!empty($foreignKeys)) {
                    // 2. Ejecuta UNA SOLA consulta para traer todos los modelos relacionados.
                    $relatedCollection = $relatedModelClass::whereIn($ownerKey, $foreignKeys)->get();
                }

                // 3. Mapea los modelos relacionados de vuelta a sus "padres".
                $relatedMap = [];
                foreach ($relatedCollection as $related) {
                    $relatedMap[$related->$ownerKey] = $related;
                }
                foreach ($collection as $model) {
                    $model->$relationName = $relatedMap[$model->$foreignKeyProperty] ?? null;
                }
            }

            # --- hasMany --- #
            if ($relationDetails['type'] === 'hasMany') {
                $foreignKey = $relationDetails['foreignKey'];
                $localKey = $relationDetails['localKey'];

                $localKeyProperty = $this->toCamelCase($localKey);

                // 1. Recolecta las claves locales usando la propiedad camelCase correcta.
                $localKeys = array_unique(array_map(fn($model) => $model->$localKeyProperty, $collection));

                if (!empty($localKeys)) {
                    // 2. Ejecuta UNA SOLA consulta para traer todos los modelos relacionados
                    //    que coincidan con cualquiera de las claves locales.
                    $relatedCollection = $relatedModelClass::whereIn($foreignKey, $localKeys)->get();
                }

                // 3. Mapea los modelos relacionados de vuelta a sus "padres".
                //    Como es una relación "muchos", agrupamos los hijos por la clave foránea.
                $relatedMap = [];
                foreach ($relatedCollection as $related) {
                    $relatedMap[$related->$foreignKey][] = $related;
                }

                foreach ($collection as $model) {
                    $model->$relationName = $relatedMap[$model->$localKeyProperty] ?? [];
                }
            }

            # -- belongsToMany -- #
            if ($relationDetails['type'] === 'belongsToMany') {
                $relatedModelClass = $relationDetails['model'];
                $pivotTable = $relationDetails['pivotTable'];
                $foreignPivotKey = $relationDetails['foreignPivotKey']; // Clave en la pivote que apunta al modelo actual
                $relatedPivotKey = $relationDetails['relatedPivotKey']; // Clave en la pivote que apunta al modelo relacionado
                $localKey = $relationDetails['localKey']; // PK del modelo actual
                $relatedKey = $relationDetails['relatedKey']; // PK del modelo relacionado

                $localKeyProperty = $this->toCamelCase($localKey);

                // 1. Recolecta las claves locales usando la propiedad camelCase correcta.
                $localKeys = array_unique(array_map(fn($model) => $model->$localKeyProperty, $collection));

                if (!empty($localKeys)) {
                    // 2. Ejecuta UNA SOLA consulta para traer todos los modelos relacionados,
                    //    uniendo la tabla pivote para saber a quién pertenecen.
                    $placeholders = implode(',', array_fill(0, count($localKeys), '?'));
                    $sql = "SELECT {$relatedModelClass::$table}.*, {$pivotTable}.{$foreignPivotKey} AS pivot_{$foreignPivotKey}
                    FROM {$relatedModelClass::$table}
                    INNER JOIN {$pivotTable} ON {$relatedModelClass::$table}.{$relatedKey} = {$pivotTable}.{$relatedPivotKey}
                    WHERE {$pivotTable}.{$foreignPivotKey} IN ($placeholders)";

                    $stmt = static::db()->getPdo()->prepare($sql);
                    $stmt->execute($localKeys);
                    $relatedItemsRaw = $stmt->fetchAll(\PDO::FETCH_OBJ);

                    // 3. Mapea los modelos relacionados de vuelta a sus "padres".
                    $relatedMap = [];
                    foreach ($relatedItemsRaw as $item) {
                        $relatedModel = new $relatedModelClass();
                        $relatedModel->hydrate($item);
                        // Usamos la columna 'pivot_...' para saber a qué padre pertenece
                        $relatedMap[$item->{"pivot_{$foreignPivotKey}"}][] = $relatedModel;
                    }

                    foreach ($collection as $model) {
                        // Usamos la propiedad en camelCase ($localKeyProperty) para buscar en el mapa.
                        $model->$relationName = $relatedMap[$model->$localKeyProperty] ?? [];
                    }
                } else {
                    foreach ($collection as $model) {
                        $model->$relationName = [];
                    }
                }
            }

            // --- RECURSIÓN PARA RELACIONES ANIDADAS ---
            if (!empty($nested)) {
                // 1. Aplanamos la colección de sub-modelos.
                $subCollection = [];
                foreach ($collection as $model) {
                    if (isset($model->$relationName) && $model->$relationName !== null) {
                        // Si la relación es un array (hasMany/belongsToMany), lo fusionamos.
                        // Si es un objeto (belongsTo), lo añadimos al array.
                        $subCollection = array_merge($subCollection, is_array($model->$relationName) ? $model->$relationName : [$model->$relationName]);
                    }
                }

                // 2. Quitamos duplicados si los hubiera.
                $subCollection = array_unique($subCollection, SORT_REGULAR);

                if (!empty($subCollection)) {
                    // 3. Creamos una instancia del modelo relacionado para llamar al método.
                    //    Pasamos las claves del siguiente nivel de anidación (ej. 'permisos' de ['perfil' => ['permisos' => []]])
                    (new $relatedModelClass())->loadRelationsForCollection($subCollection, array_keys($nested));
                }
            }
        }
    }

    # --- MÉTODOS INTERNOS Y HELPERS --- #

    /**
     * Inicia una nueva consulta con una condición WHERE.
     *
     * @param string $column
     * @param string $operator
     * @param mixed $value
     * @return ModelQueryBuilder
     */
    public static function where(string $column, string $operator, $value): ModelQueryBuilder
    {
        // 1. Crea una nueva instancia del Query Builder.
        // 2. Llama al método 'where' del builder y devuelve el builder.
        return (new ModelQueryBuilder(new static()))->where($column, $operator, $value);
    }

    /**
     * Inicia una nueva consulta con una condición ORDER BY.
     *
     * @param string $column
     * @param string $direction
     * @return ModelQueryBuilder
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
     *
     * @param string $snakeCaseString
     * @return string
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
     * @param object|array $data
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
     * Útil para los métodos insert y update.
     * @return array
     */
    protected function getAttributes(): array
    {
        $attributes = [];

        // Iteramos sobre la lista 'fillable' en lugar de todas las propiedades públicas.
        foreach ($this->fillable as $fillableAttribute) {
            // Convertimos el nombre de la columna (snake_case) a propiedad (camelCase)
            $property = $this->toCamelCase($fillableAttribute);

            // Comprobamos si la propiedad existe y está inicializada en el objeto
            if (property_exists($this, $property) && isset($this->$property)) {
                $attributes[$fillableAttribute] = $this->$property;
            }
        }

        return $attributes;
    }

    /**
     * Wrapper público para obtener los atributos.
     * Útil para las fábricas y el bulk insert.
     */
    public function getAttributesForInsert(): array
    {
        return $this->getAttributes();
    }

    /**
     * Helper para convertir un array plano de relaciones con notación de punto
     * en un array anidado.
     * Ejemplo: ['perfil', 'perfil.permisos'] -> ['perfil' => ['permisos' => []]]
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
     *
     * @return ModelQueryBuilder
     */
    public static function query(): ModelQueryBuilder
    {
        return new ModelQueryBuilder(new static());
    }

    /**
     * Obtiene el constructor de consultas de bajo nivel (QueryBuilder).
     *
     * @return QueryBuilder
     */
    public static function queryBuilder(): QueryBuilder
    {
        return static::db();
    }
}
