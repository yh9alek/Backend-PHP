<?php

require_once __DIR__.'/vendor/autoload.php';

# --------------- Cargar variables de entorno ---------------

use Dotenv\Dotenv as ENV;
ENV::createImmutable(__DIR__)->load();

# -----------------------------------------------------------

use app\Factories\ArticuloFactory;

echo "Iniciando el seeder...\n";

$articuloFactory = new ArticuloFactory();
$cantidad = 25000;

echo "Creando $cantidad usuarios...\n";

$result = $articuloFactory->create($cantidad);

if (is_array($result) && !empty($result)) {
    echo "¡Seeding completado con éxito!";
} else {
    echo "¡ERROR DURANTE EL SEEDING! El seeder no pudo crear los registros.\n";
}