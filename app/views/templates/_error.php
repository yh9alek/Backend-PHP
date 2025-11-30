<?php $currentPath = $viewInstance->request->uri; ?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>SIGA</title>
        
        <?php require_once __DIR__.'/refs/head.php' ?>

    </head>
    <body style="background-color: #202124;">

        <main id="module" style="padding-top: 0; margin-top: -50px;">
            <div class="container" style="height: 100vh;">
                <?php require $modulo; ?>
            </div>
        </main>

        <?php require_once __DIR__.'/refs/scripts.php' ?>

    </body>
</html>