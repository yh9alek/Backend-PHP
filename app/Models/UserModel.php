<?php

namespace app\Models;

class UserModel extends BaseModel {

    protected static string $table = 'user';
    
    // --- PROPIEDADES DE LA TABLA ---
    public ?int    $id = null;
    public int     $profileId;
    public int     $areaId;
    public ?string $nombre = null;          // Hacer opcionales las cadenas es más seguro
    public ?string $apellidoPaterno = null;
    public ?string $apellidoMaterno = null;
    public string  $username;
    public string  $email;
    public string  $pass;
    public int     $createUser;
    public string  $createdAt;
    public ?int    $updateUser = null;
    public ?string $updatedAt  = null;

    // --- PROPIEDADES PARA LAS RELACIONES ---
    public ?ProfileModel $profile = null;
    public ?AreaModel    $area    = null;
    public ?UserModel    $createdByUser = null;
    public ?UserModel    $updatedByUser = null;
    public array         $usersCreated = [];
    public array         $usersUpdated = [];

    /**
     * Los atributos que son asignables masivamente. Debería ser 'protected'.
     */
    protected array $fillable = [
        'profile_id',
        'area_id',
        'nombre',
        'apellido_paterno',
        'apellido_materno',
        'username',
        'email',
        'pass',
        'create_user',
        'created_at',
        'update_user',
        'updated_at',
    ];

    // --- MÉTODOS DE RELACIONES ---
    public function profile(): array {
        return [
            'type'       => 'belongsTo',
            'model'      => ProfileModel::class,
            'foreignKey' => 'profile_id',
            'ownerKey'   => 'id'
        ];
    }

    public function area(): array {
        return [
            'type'       => 'belongsTo',
            'model'      => AreaModel::class,
            'foreignKey' => 'area_id',
            'ownerKey'   => 'id'
        ];
    }

    /**
     * Obtiene el usuario que creó este registro.
     */
    public function createdByUser(): array {
        return [
            'type'       => 'belongsTo',
            'model'      => UserModel::class,
            'foreignKey' => 'create_user',
            'ownerKey'   => 'id'
        ];
    }

    /**
     * Obtiene el usuario que actualizó este registro.
     */
    public function updatedByUser(): array {
        return [
            'type'       => 'belongsTo',
            'model'      => UserModel::class,
            'foreignKey' => 'update_user',
            'ownerKey'   => 'id'
        ];
    }
    
    /**
     * Obtiene todos los usuarios creados por ESTE usuario.
     */
    public function usersCreated(): array {
        return [
            'type'       => 'hasMany',
            'model'      => UserModel::class,
            'foreignKey' => 'create_user',
            'localKey'   => 'id'
        ];
    }

    /**
     * Obtiene todos los usuarios actualizados por ESTE usuario.
     */
    public function usersUpdated(): array {
        return [
            'type'       => 'hasMany',
            'model'      => UserModel::class,
            'foreignKey' => 'update_user',
            'localKey'   => 'id'
        ];
    }
}