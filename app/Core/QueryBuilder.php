<?php

namespace app\Core;

use Exception;
use PDO;
use PDOException;

/**
 * Clase de respuesta estándar para operaciones SQL.
 */
class DBResponse
{
    public function __construct(
        public bool $success  = false,
        public mixed $data    = null,
        public ?string $msg   = null
    ) {}
}

/**
 * Clase constructora de consultas SQL
 */
class QueryBuilder
{

    private ?PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    /**
     * @param $joins Ejemplo: ['type' => 'INNER', 'table' => 'ca_perfil', 'on' => 'ca_usuario.idperfil = ca_perfil.idperfil']
     */
    public function select(
        string $table,
        array  $columns = ['*'],
        string $where   = '',
        array  $params  = [],
        string $extras  = '',
        array  $joins   = []
    ): DBResponse {

        $response = new DBResponse();

        if (!$this->pdo) {
            $response->msg = "Error de conexión a la base de datos";
            return $response;
        }

        $cols     = implode(', ', $columns);
        $sql      = "SELECT $cols FROM $table";

        # --- CONSTRUIR JOINS ---
        if (!empty($joins)) {
            foreach ($joins as $join) {

                // Esperamos un array como ['type' => 'INNER', 'table' => 'ca_perfil', 'on' => 'ca_usuario.idperfil = ca_perfil.idperfil']
                $joinType  = $join['type'] ?? 'INNER';
                $joinTable = $join['table'];
                $joinOn    = $join['on'];
                $sql .= " $joinType JOIN $joinTable ON $joinOn";
            }
        }

        if ($where)  $sql .= " WHERE $where";
        if ($extras) $sql .= " $extras";

        try {
            $stmt = $this->pdo->prepare($sql);

            // Los parámetros de la cláusula WHERE y los de la cláusula JOIN deben unirse si se usan
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }

            $stmt->execute();
            $response->success = true;
            $response->data    = $stmt->fetchAll();
        } catch (PDOException $e) {
            $response->msg = "Error al consultar datos";
            ob_start();
            print_r($params);
            $params = ob_get_clean();
            file_put_contents(__DIR__ . '/../../logs/db.log', "[ " . date('d/m/Y H:i:s A') . " ] {$e->getMessage()} \n\nSQL: $sql \n\nPARAMS: $params \n\n", FILE_APPEND);
        }

        return $response;
    }

    /**
     * Realiza una consulta SELECT con paginación.
     *
     * @param string $table
     * @param int    $page La página actual (empezando en 1).
     * @param int    $perPage El número de registros por página.
     * @param array  $columns
     * @param string $where
     * @param array  $params
     * @param string $extras (para ORDER BY, etc.)
     * @param array  $joins
     * @return DBResponse Contiene 'data' (los registros de la página) y 'meta' (información de paginación).
     */
    public function paginate(
        string $table,
        int $page = 1,
        int $perPage = 15,
        array $columns = ['*'],
        string $where = '',
        array $params = [],
        string $extras = '',
        array $joins = []
    ): DBResponse {

        $response = new DBResponse();

        if (!$this->pdo) {
            $response->msg = "Error de conexión a la base de datos";
            return $response;
        }

        // --- 1. Calcular el total de registros para la paginación ---
        $totalSql = "SELECT COUNT(*) as total FROM $table";
        $joinSql = '';
        if (!empty($joins)) {
            foreach ($joins as $join) {
                $joinType  = $join['type'] ?? 'INNER';
                $joinTable = $join['table'];
                $joinOn    = $join['on'];
                $joinSql .= " $joinType JOIN $joinTable ON $joinOn";
            }
        }
        $totalSql .= $joinSql;
        if ($where) {
            $totalSql .= " WHERE $where";
        }

        try {
            $totalStmt = $this->pdo->prepare($totalSql);
            // Usamos los mismos parámetros del WHERE para el conteo
            foreach ($params as $key => $value) {
                $totalStmt->bindValue(":$key", $value);
            }
            $totalStmt->execute();
            $totalRows = (int) $totalStmt->fetch(PDO::FETCH_OBJ)->total;
        } catch (PDOException $e) {
            $response->msg = "Error al contar registros";
            file_put_contents(__DIR__ . '/../../logs/db.log', "[ " . date('d/m/Y H:i:s A') . " ] {$e->getMessage()} \n\n", FILE_APPEND);
            return $response;
        }

        // --- 2. Construir y ejecutar la consulta principal con LIMIT y OFFSET ---
        $cols = implode(', ', $columns);
        $sql = "SELECT $cols FROM $table";
        $sql .= $joinSql;

        if ($where)  $sql .= " WHERE $where";
        if ($extras) $sql .= " $extras";

        // Calcular el OFFSET para la consulta
        $offset = ($page - 1) * $perPage;
        $sql .= " LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;

        try {
            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            $stmt->execute();

            // LLAMA A fetchAll() SÓLO UNA VEZ Y GUARDA EL RESULTADO
            $itemsOnThisPage = $stmt->fetchAll();

            $response->success = true;

            // Construye la respuesta usando la variable guardada.
            $response->data = [
                'items' => $itemsOnThisPage,
                'meta' => [
                    'total' => $totalRows,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => (int) ceil($totalRows / $perPage),
                    'from' => $totalRows > 0 ? $offset + 1 : 0,
                    'to' => $totalRows > 0 ? $offset + count($itemsOnThisPage) : 0,
                ]
            ];
        } catch (PDOException $e) {
            $response->msg = "Error al consultar datos";
            ob_start();
            print_r($params);
            $params = ob_get_clean();
            file_put_contents(__DIR__ . '/../../logs/db.log', "[ " . date('d/m/Y H:i:s A') . " ] {$e->getMessage()} \n\nSQL: $sql \n\nPARAMS: $params \n\n", FILE_APPEND);
        }

        return $response;
    }

    public function insert(string $table, array $data): DBResponse
    {
        $response = new DBResponse();

        if (!$this->pdo) {
            $response->msg = "Error de conexión a la base de datos";
            return $response;
        }

        $columns      = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_map(fn($k) => ":$k", array_keys($data)));

        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";

        try {
            $stmt = $this->pdo->prepare($sql);

            foreach ($data as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }

            $stmt->execute();
            $response->success = true;
            $response->data    = $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            $response->msg = "Error al insertar datos";
            ob_start();
            print_r($data);
            $data = ob_get_clean();
            file_put_contents(__DIR__ . '/../../logs/db.log', "[ " . date('d/m/Y H:i:s A') . " ] {$e->getMessage()} \n\nSQL: $sql \n\nPARAMS: $data \n\n", FILE_APPEND);
        }

        return $response;
    }

    /**
     * Inserta múltiples registros en la base de datos con una sola consulta.
     * @param string $table La tabla donde insertar.
     * @param array  $data Un array de arrays asociativos. ej: [['name' => 'A'], ['name' => 'B']]
     * @return DBResponse
     */
    public function bulkInsert(string $table, array $data): DBResponse
    {
        $response = new DBResponse();

        if (!$this->pdo) {
            $response->msg = "Error de conexión a la base de datos";
            return $response;
        }

        if (empty($data)) {
            $response->msg = "No hay datos para insertar.";
            return $response;
        }

        // 1. Obtenemos las columnas del primer registro. Asumimos que todos son iguales.
        $columns = array_keys($data[0]);
        $columnSql = implode(', ', $columns);

        // 2. Creamos los placeholders para cada fila: (?, ?, ?)
        $rowPlaceholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';

        // 3. Repetimos los placeholders para cada fila: (?,?), (?,?), (?,?)
        $allPlaceholders = implode(', ', array_fill(0, count($data), $rowPlaceholders));

        // 4. Aplanamos el array de datos en un solo array de valores
        $flatValues = [];
        foreach ($data as $row) {
            foreach ($columns as $column) {
                $flatValues[] = $row[$column];
            }
        }

        $sql = "INSERT INTO $table ($columnSql) VALUES $allPlaceholders";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($flatValues);
            $response->success = true;
            $response->data = $stmt->rowCount();
        } catch (PDOException $e) {
            $response->msg = "Error en la inserción masiva";
            file_put_contents(__DIR__ . '/../../logs/db.log', "[ " . date('d/m/Y H:i:s A') . " ] {$e->getMessage()} \n\n", FILE_APPEND);
        }

        return $response;
    }

    public function update(string $table, array $data, string $where, array $params): DBResponse
    {
        // $data['update_user'] = $this->jwtService->validateToken()->user_id;
        // $data['updated_at']  = date('Y-m-d H:i:s');

        $response = new DBResponse();

        if (!$this->pdo) {
            $response->msg = "Error de conexión a la base de datos";
            return $response;
        }

        $set = implode(', ', array_map(
            fn($k) => "$k = :$k",
            array_keys($data)
        ));

        $sql = "UPDATE $table SET $set WHERE $where";

        try {
            $stmt = $this->pdo->prepare($sql);

            foreach ($data as $k => $v)
                $stmt->bindValue(":$k", $v);

            foreach ($params as $k => $v)
                $stmt->bindValue(":$k", $v);

            $stmt->execute();
            $response->success = true;
            $response->data    = $stmt->rowCount();
        } catch (PDOException $e) {
            $response->msg = "Error al actualizar datos";
            ob_start();
            print_r($params);
            $params = ob_get_clean();
            file_put_contents(__DIR__ . '/../../logs/db.log', "[ " . date('d/m/Y H:i:s A') . " ] {$e->getMessage()} \n\nSQL: $sql \n\nPARAMS: $params \n\n", FILE_APPEND);
        }

        return $response;
    }

    public function delete(string $table, string $where, array $params = []): DBResponse
    {
        $response = new DBResponse();

        if (!$this->pdo) {
            $response->msg = "Error de conexión a la base de datos";
            return $response;
        }

        $sql = "DELETE FROM $table WHERE $where";

        try {

            $stmt = $this->pdo->prepare($sql);

            foreach ($params as $k => $v)
                $stmt->bindValue(":$k", $v);

            $stmt->execute();
            $response->success = true;
            $response->data    = $stmt->rowCount();
        } catch (PDOException $e) {
            $response->msg = "Error al eliminar datos";
            ob_start();
            print_r($params);
            $params = ob_get_clean();
            file_put_contents(__DIR__ . '/../../logs/db.log', "[ " . date('d/m/Y H:i:s A') . " ] {$e->getMessage()} \n\nSQL: $sql \n\nPARAMS: $params \n\n", FILE_APPEND);
        }

        return $response;
    }

    /**
     * Ejecuta un procedimiento almacenado o una función en la base de datos.
     *
     * @param string $routineName El nombre del procedimiento o función.
     * @param array $params Un array de parámetros. Para procedimientos, cada parámetro
     *                      debe ser un array con 'name', 'value' (opcional), 'type' (opcional),
     *                      y 'direction' ('IN', 'OUT', 'INOUT').
     *                      Para funciones, puede ser un array asociativo simple ['name' => 'value'].
     * @param bool $isFunction Si es TRUE, se ejecutará como una función (SELECT routineName(...)).
     *                         Si es FALSE (por defecto), se ejecutará como un procedimiento (CALL routineName(...)).
     * @return DBResponse El campo 'data' contendrá un array con 'result_sets' y 'output_params' para procedimientos,
     *                    o el valor de retorno escalar para funciones.
     */
    public function call(string $routineName, array $params = [], bool $isFunction = false): DBResponse
    {
        $response = new DBResponse();

        // 1. Construir la lista de marcadores de posición para la llamada
        $placeholders = [];
        $paramNames = [];
        if (!$isFunction) {
            // Para procedimientos: :paramName
            foreach ($params as $param) {
                $placeholders[] = ':' . $param['name'];
            }
        } else {
            // Para funciones: también :paramName
            foreach ($params as $name => $value) {
                $placeholders[] = ':' . $name;
                $paramNames[] = $name;
            }
        }
        $placeholderString = implode(', ', $placeholders);

        // 2. Construir la sentencia SQL
        $sql = $isFunction
            ? "SELECT $routineName($placeholderString)"
            : "CALL $routineName($placeholderString)";

        try {
            $stmt = $this->pdo->prepare($sql);

            // 3. Vincular los parámetros
            if (!$isFunction) {
                // Manejo complejo para procedimientos con IN/OUT
                foreach ($params as &$param) { // Usamos referencia para poder recibir valores de OUT
                    $paramName = ':' . $param['name'];
                    $direction = $param['direction'] ?? 'IN'; // IN por defecto
                    $type = $param['type'] ?? PDO::PARAM_STR; // STR por defecto

                    if ($direction === 'IN') {
                        $stmt->bindValue($paramName, $param['value'], $type);
                    } else {
                        // Para OUT y INOUT, se usa bindParam y se necesita una variable
                        // que se pasará por referencia.
                        $stmt->bindParam($paramName, $param['value'], $type | PDO::PARAM_INPUT_OUTPUT, $param['length'] ?? 255);
                    }
                }
                unset($param); // Romper la referencia
            } else {
                // Manejo simple para funciones (solo IN)
                foreach ($params as $name => $value) {
                    $stmt->bindValue(':' . $name, $value);
                }
            }

            $stmt->execute();
            $response->success = true;

            // 4. Recolectar los resultados
            if ($isFunction) {
                // Las funciones devuelven un único valor escalar
                $response->data = $stmt->fetchColumn();
            } else {
                // Los procedimientos pueden devolver múltiples conjuntos de resultados y parámetros OUT
                $resultSets = [];
                do {
                    $result = $stmt->fetchAll();
                    if ($result) {
                        $resultSets[] = $result;
                    }
                } while ($stmt->nextRowset());

                // Recolectar los valores de los parámetros OUT
                $outputParams = [];
                foreach ($params as $param) {
                    if (($param['direction'] ?? 'IN') !== 'IN') {
                        $outputParams[$param['name']] = $param['value'];
                    }
                }

                $response->data = [
                    'result_sets' => $resultSets,
                    'output_params' => $outputParams,
                ];
            }
        } catch (PDOException $e) {
            $response->msg = "Error de base de datos";
            ob_start();
            print_r($params);
            $params = ob_get_clean();
            file_put_contents(__DIR__ . '/../../logs/db.log', "[ " . date('d/m/Y H:i:s A') . " ] {$e->getMessage()} \n\nSQL: $sql \n\nPARAMS: $params \n\n", FILE_APPEND);
        }

        return $response;
    }

    /**
     * Ejecuta un procedimiento almacenado diseñado para paginación que devuelve
     * un conjunto de resultados y establece una variable de sesión @total_records.
     *
     * @param string $procedureName El nombre del procedimiento.
     * @param array $inParams Un array asociativo simple de parámetros de ENTRADA.
     * @return DBResponse El campo 'data' contendrá ['items' => ..., 'total' => ...].
     */
    public function callPaginatedSP(string $procedureName, array $inParams = []): DBResponse
    {
        $response = new DBResponse();

        // 1. Construir la llamada al procedimiento
        $placeholders = implode(', ', array_map(fn($k) => ":$k", array_keys($inParams)));
        $sql = "CALL $procedureName($placeholders)";

        try {
            $stmt = $this->pdo->prepare($sql);

            // 2. Vincular los parámetros de ENTRADA
            foreach ($inParams as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }

            // 3. Ejecutar el procedimiento
            $stmt->execute();

            // 4. Obtener el conjunto de resultados (las filas de la página)
            $items = $stmt->fetchAll();

            // 5. Liberar el cursor para poder hacer la siguiente consulta
            $stmt->closeCursor();

            // 6. Ejecutar una segunda consulta para obtener el valor de la variable de sesión
            $totalStmt = $this->pdo->query("SELECT @total_records AS total");
            $totalRows = (int) $totalStmt->fetch(PDO::FETCH_OBJ)->total;

            $response->success = true;
            $response->data = [
                'items' => $items,
                'total' => $totalRows
            ];
        } catch (PDOException $e) {
            $response->msg = "Error de base de datos";
            ob_start();
            print_r($inParams);
            $params = ob_get_clean();
            file_put_contents(__DIR__ . '/../../logs/db.log', "[ " . date('d/m/Y H:i:s A') . " ] {$e->getMessage()} \n\nSQL: $sql \n\nPARAMS: $params \n\n", FILE_APPEND);
            throw new Exception('Errors');
        }

        return $response;
    }

    /**
     * Devuelve la instancia de PDO actual.
     *
     * @return PDO
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Comprueba si hay una transacción activa.
     */
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * Confirma una transacción.
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Revierte una transacción.
     */
    public function rollBack(): bool
    {
        // Solo intenta revertir si hay una transacción activa
        if ($this->pdo->inTransaction()) {
            return $this->pdo->rollBack();
        }
        return false;
    }
}
