<?php

require_once __DIR__.'/vendor/autoload.php';

# --------------- Cargar variables de entorno ---------------

use Dotenv\Dotenv as ENV;
ENV::createImmutable(__DIR__)->load();

# -----------------------------------------------------------

use app\Factories\UserFactory;

echo "Iniciando el seeder...\n";

$userFactory = new UserFactory();
$numberOfUsersToCreate = 25000;

echo "Creando $numberOfUsersToCreate usuarios...\n";

$result = $userFactory->create($numberOfUsersToCreate);

if (is_array($result) && !empty($result)) {
    echo "¡Seeding completado con éxito! Se han creado " . count($result) . " usuarios.\n";
} else {
    echo "¡ERROR DURANTE EL SEEDING! El seeder no pudo crear los registros.\n";
}