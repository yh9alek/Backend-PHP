<?php

namespace app\Models;

use app\Models\BaseModel;

class CategoryModel extends BaseModel {

    protected static string $table = 'category';
    // protected static string $primaryKey = 'id';

    # Campos de la tabla en BD
    public ?int   $id = null;
    public string $name;

    # Fillable (datos que contendrán nuestras instancias)
    public array $fillable = [
        'name'
    ];

    public function modules(): array {
        return [
            'type'       =>  'hasMany',
            'model'      =>  ModuleModel::class,
            'foreignKey' =>  'category_id',
            'ownerKey'   =>  'id'
        ];
    }

}