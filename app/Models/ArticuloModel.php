<?php

namespace app\Models;

class ArticuloModel extends BaseModel {

    protected static string $table = 'ca_articulo';
    // protected static string $primaryKey = 'id';

    public ?int    $id = null;
    public string  $nombre;
    public int     $precio;
    public bool    $estatus;
    public int     $usuario_alta;
    public string  $fecha_alta;
    public ?int    $usuario_mod = null;
    public ?string $fecha_mod   = null;

    # Fillable (datos que contendran nuestras instancias)
    public array $fillable = [
        'nombre',
        'precio',
        'estatus',
        'usuario_alta',
        'fecha_alta',
    ];
}