<?php

namespace app\Factories;

use app\Models\ArticuloModel;

class ArticuloFactory extends BaseFactory
{
    protected string $modelClass = ArticuloModel::class;

    public function __construct()
    {
        parent::__construct();
    }

    public function definition(): array
    {
        return [
            'nombre'       => $this->faker->name(),
            'precio'       => $this->faker->randomNumber(5, false),
            'estatus'      => $this->faker->boolean(),
            'usuario_alta' => 1,
        ];
    }
}