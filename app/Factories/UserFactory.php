<?php

namespace app\Factories;

use app\Models\ProfileModel;
use app\Models\UserModel;

class UserFactory extends BaseFactory
{
    protected string $modelClass = UserModel::class;
    
    protected array $profileIds = [];
    protected array $areaIds    = [];
    
    /**
     * @var string Pre-calculamos el hash de la contraseña una sola vez.
     */
    protected string $preHashedPassword;

    public function __construct()
    {
        parent::__construct();
        
        // 1. Cargamos los perfiles una sola vez.
        $this->profileIds = array_column(ProfileModel::all(), 'id');

        // 2. Hasheamos la contraseña UNA SOLA VEZ.
        $this->preHashedPassword = password_hash('password', PASSWORD_DEFAULT);
    }

    public function definition(): array
    {
        if (empty($this->profileIds)) {
            throw new \Exception("No se encontraron perfiles en la base de datos.");
        }
        
        $randomProfileId = $this->profileIds[array_rand($this->profileIds)];
        
        return [
            'profile_id'       => $randomProfileId,
            'area_id'          => 1,
            'nombre'           => $this->faker->name(),
            'apellido_paterno' => $this->faker->lastName(),
            'apellido_materno' => $this->faker->lastName(),
            'username'         => $this->faker->unique()->userName(),
            'email'            => $this->faker->userName() . '@example.com',
            'pass'             => $this->preHashedPassword,
            'create_user'      => 27,
            'created_at'       => $this->faker->dateTimeThisYear()->format('Y-m-d H:i:s'),
        ];
    }
}