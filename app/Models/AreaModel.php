<?php

namespace app\Models;

class AreaModel extends BaseModel {
    protected static string $table = 'user';
    
    // --- PROPIEDADES DE LA TABLA ---
    public ?int    $id = null;
    public string  $name;
    
    /**
     * Los atributos que son asignables masivamente. Debería ser 'protected'.
     */
    protected array $fillable = [
        'name'
    ];

    // --- MÉTODOS DE RELACIONES ---
    public function users(): array {
        return [
            'type'       => 'hasMany',
            'model'      => UserModel::class,
            'foreignKey' => 'area_id',
            'localKey'   => 'id'
        ];
    }
}