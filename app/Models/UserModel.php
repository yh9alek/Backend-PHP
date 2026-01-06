<?php

namespace app\Models;

class UserModel extends BaseModel
{
    protected static string $table = 'usuarios';

    // --- PROPIEDADES DE LA TABLA ---
    public ?int    $id = null;
    public int     $profileId;
    public int     $areaId;
    public ?string $nombre = null;
    public ?string $apellidoPaterno = null;
    public ?string $apellidoMaterno = null;
    public string  $username;
    public string  $email;
    public ?string $telefono = null;
    public int     $createUser;
    public string  $createdAt;
    public ?int    $updateUser = null;
    public ?string $updatedAt = null;

    // --- PROPIEDADES PARA LAS RELACIONES ---
    public ?ProfileModel $profile = null;
    public ?AreaModel $area = null;
    public ?UserModel $createdByUser = null;
    public ?UserModel $updatedByUser = null;
    public array $usersCreated = [];
    public array $usersUpdated = [];

    /**
     * Los atributos que son asignables masivamente.
     */
    protected array $fillable = [
        'profile_id',
        'area_id',
        'nombre',
        'apellido_paterno',
        'apellido_materno',
        'username',
        'email',
        'telefono',
        'create_user'
    ];

    // --- MÉTODOS DE RELACIONES ---
    public function profile(): array
    {
        return [
            'type' => 'belongsTo',
            'model' => ProfileModel::class,
            'foreignKey' => 'profile_id',
            'ownerKey' => 'id'
        ];
    }

    public function area(): array
    {
        return [
            'type' => 'belongsTo',
            'model' => AreaModel::class,
            'foreignKey' => 'area_id',
            'ownerKey' => 'id'
        ];
    }

    public function createdByUser(): array
    {
        return [
            'type' => 'belongsTo',
            'model' => UserModel::class,
            'foreignKey' => 'create_user',
            'ownerKey' => 'id'
        ];
    }

    public function updatedByUser(): array
    {
        return [
            'type' => 'belongsTo',
            'model' => UserModel::class,
            'foreignKey' => 'update_user',
            'ownerKey' => 'id'
        ];
    }

    public function usersCreated(): array
    {
        return [
            'type' => 'hasMany',
            'model' => UserModel::class,
            'foreignKey' => 'create_user',
            'localKey' => 'id'
        ];
    }

    public function usersUpdated(): array
    {
        return [
            'type' => 'hasMany',
            'model' => UserModel::class,
            'foreignKey' => 'update_user',
            'localKey' => 'id'
        ];
    }

    // --- MÉTODOS ESTÁTICOS ÚTILES ---

    /**
     * Busca un usuario por su Keycloak ID.
     *
     * @param string $keycloakId
     * @return UserModel|null
     */
    public static function findByKeycloakId(string $keycloakId): ?UserModel
    {
        return static::where('keycloak_id', '=', $keycloakId)
            ->limit(1)
            ->get()[0] ?? null;
    }
}