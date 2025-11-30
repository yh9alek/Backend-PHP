<?php

namespace app\Models;

class ProfileModel extends BaseModel {

    protected static string $table = 'profile';
    // protected static string $primaryKey = 'id';

    public ?int   $id = null;
    public string $name;
    public string $description;

    # Fillable (datos que contendran nuestras instancias)
    public array $fillable = [
        'name',
        'description'
    ];

    public ?array $modules = [];

    public function modules(): array {
        return [
            'type'            => 'belongsToMany',
            'model'           => ModuleModel::class,

            # --- Claves de la Tabla Pivote ---
            'pivotTable'      => 'profile_module',
            'foreignPivotKey' => 'profile_id',
            'relatedPivotKey' => 'module_id',

            # --- Claves de los Modelos ---
            'localKey'        => 'id',
            'relatedKey'      => 'id'
        ];
    }

    public array $users = [];

    public function users(): array {
        return [
            'type'       => 'hasMany',
            'model'      => UserModel::class,
            
            'foreignKey' => 'profile_id',
            'localKey'   => 'id'
        ];
    }
}