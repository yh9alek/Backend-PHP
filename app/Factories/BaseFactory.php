<?php

namespace app\Factories;

use Faker\Factory as FakerFactory;
use Faker\Generator;
use app\Models\BaseModel;

abstract class BaseFactory
{
    /**
     * El nombre de la clase del modelo que esta fábrica creará.
     * @var string
     */
    protected string $modelClass;

    /**
     * La instancia del generador Faker.
     * @var \Faker\Generator
     */
    protected Generator $faker;

    /**
     * Define la estructura de atributos por defecto para el modelo.
     * Este es el método que cada fábrica hija DEBE implementar.
     * @return array
     */
    abstract public function definition(): array;

    public function __construct()
    {
        $this->faker = FakerFactory::create('es_ES');
    }

    /**
     * Crea una instancia del modelo y la llena con datos, sin guardarla en la BD.
     *
     * @param int   $count El número de modelos a crear.
     * @param array $attributes Atributos para sobrescribir los valores por defecto.
     * @return BaseModel|array
     */
    public function make(int $count = 1, array $attributes = [])
    {
        if ($count > 1) {
            $models = [];
            for ($i = 0; $i < $count; $i++) {

                // Para múltiples, los atributos de override se aplican a todos
                $models[] = $this->makeOne($attributes);
            }
            return $models;
        }

        return $this->makeOne($attributes);
    }

    /**
     * Crea una instancia del modelo, la llena con datos Y la guarda en la BD.
     *
     * @param int   $count El número de modelos a crear.
     * @param array $attributes Atributos para sobrescribir los valores por defecto.
     * @return BaseModel|array
     */
    public function create(int $count = 1, array $attributes = [])
    {
        if ($count > 1) {
            // ... (preparación de $dataToInsert, sin cambios)
            $models = $this->make($count, $attributes);
            $dataToInsert = [];
            foreach ($models as $model) {
                $dataToInsert[] = $model->getAttributesForInsert();
            }

            // Si no hay nada que insertar, salimos.
            if (empty($dataToInsert)) {
                echo "ADVERTENCIA: No se generaron datos para insertar. Revisa tu array \$fillable y la definición de la fábrica.\n";
                return [];
            }

            $modelInstance = new $this->modelClass();
            $table = $modelInstance::getTable();
            $db = $modelInstance::queryBuilder();
            $pdo = $db->getPdo();

            try {
                $pdo->beginTransaction(); // 1. Inicia una transacción explícita

                $response = $db->bulkInsert($table, $dataToInsert);

                if ($response->success) {
                    $pdo->commit(); // 2. Si el bulkInsert fue exitoso, CONFIRMA la transacción.
                    return $models;
                } else {
                    // Si el bulkInsert falló, imprimimos el error y revertimos.
                    echo "ERROR DE BASE DE DATOS: " . $response->msg . "\n";
                    $pdo->rollBack();
                    return [];
                }

            } catch (\Exception $e) {
                echo "ERROR INESPERADO: " . $e->getMessage() . "\n";
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                return [];
            }
        }

        // La lógica para crear uno solo ya usa `save()`, que debería funcionar bien,
        // pero si también fallara, habría que encapsularla en una transacción.
        $model = $this->makeOne($attributes);
        if ($model->save()) {
            return $model;
        }

        return null;
    }

    /**
     * Lógica interna para crear una única instancia del modelo.
     */
    protected function makeOne(array $attributes = []): BaseModel
    {
        // 1. Obtenemos la definición por defecto de la fábrica hija
        $defaultData = $this->definition();

        // 2. Sobrescribimos con los atributos pasados como argumento
        $finalData = array_merge($defaultData, $attributes);

        // 3. Creamos una instancia del modelo y la "hidratamos"
        $model = new $this->modelClass();
        $model->hydrate($finalData);

        return $model;
    }
}
