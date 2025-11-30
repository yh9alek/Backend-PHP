<?php

namespace app\Models;

use app\Models\BaseModel;

class ModuleModel extends BaseModel {

    protected static string $table = 'module';
    // protected static string $primaryKey = 'id';

    # Campos de la tabla en BD
    public ?int    $id            = null;
    public ?int    $categoryId    = null;
    public ?int    $rootModuleId  = null;
    public string  $name;
    public ?string $url           = null;
    public ?string $icon          = null;
    public ?string $description   = null;
    public int     $createUser;
    public string  $createdAt;
    public ?int    $updateUser    = null;
    public ?string $updatedAt     = null;

    # Fillable (datos que contendran nuestras instancias)
    public array $fillable = [
        'category_id',
        'root_module_id',
        'name',
        'url',
        'icon',
        'description',
        'create_user',
        'created_at',
        'update_user',
        'updated_at'
    ];

    public ?CategoryModel $category;
    public ?array $children = [];

    public function category(): array {
        return [
            'type'       =>  'belongsTo',
            'model'      =>  CategoryModel::class,
            'foreignKey' =>  'category_id',
            'ownerKey'   =>  'id'
        ];
    }

    
}