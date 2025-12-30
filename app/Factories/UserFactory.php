<?php

namespace app\Factories;

use app\Models\UserModel;

class UserFactory extends BaseFactory
{
    protected string $modelClass = UserModel::class;

    /**
     * @var string Pre-calculamos el hash de la contraseña una sola vez.
     */
    protected string $preHashedPassword;

    public function __construct()
    {
        parent::__construct();
    }

    public function definition(): array
    {       
        return [
            'nombre'           => $this->faker->name(),
            'apellido_paterno' => $this->faker->lastName(),
            'apellido_materno' => $this->faker->lastName(),
            'username'         => $this->faker->unique()->userName(),
            'email'            => $this->faker->userName() . '@example.com',
            'telefono'         => '6691238829',
            'create_user'      => 1,
        ];
    }
}