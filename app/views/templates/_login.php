<?php

use app\helpers\Asset;
$currentPath = $viewInstance->request->uri;

?>

<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $_ENV['APP_NAME'] ?? 'system' ?></title>
        
        <?php require_once __DIR__.'/refs/head.php' ?>

        <link rel="stylesheet" href="/assets/css/styles.css">
        <link rel="stylesheet" href="<?= Asset::getSource($_SERVER['REQUEST_URI'], 'css') ?>">
    </head>
    <body data-bs-theme="light">

        <main id="module">
            <?php require $modulo; ?>
        </main>

        <?php require_once __DIR__.'/refs/scripts.php' ?>

        <script type="module" src="<?= Asset::getSource($_SERVER['REQUEST_URI'], 'js') ?>"></script>
    </body>
</html>