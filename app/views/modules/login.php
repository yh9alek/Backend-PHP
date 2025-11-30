<?php

use function app\helpers\e;
use function app\helpers\route;

?>

<form>

    <img class="form_img" src="/assets/src/imgs/TMaz_w.png">
    <h1 class="from_title"><?= $_ENV['APP_NAME'] ?? 'Sistema' ?></h1>
    <p style="font-size: 16px;"><?= $_ENV['APP_DESC'] ?? 'Descripción' ?></p>

    <div class="form-group" style="margin-bottom: 20px;">
        <label for="username">Usuario</label>
        <div class="input-group flex-nowrap">
            <input class="form-control" type="text" name="username" aria-describedby="awuser" required>
            <span class="input-group-text px-2" id="awuser"><i class="bi bi-person-circle" style="color: gray"></i></span>
        </div>
    </div>
    <div class="form-group" style="margin-bottom: 20px;">
        <label for="pass">Contraseña</label>
        <div class="input-group flex-nowrap">
            <input class="form-control" type="password" name="pass" aria-describedby="awpass" required>
            <span class="input-group-text px-2" id="awpass"><i class="bi bi-lock-fill" style="color: gray"></i></span>
        </div>
    </div>

    <button class="btn btn-primary" type="submit" style="font-weight: 600;">Acceder</button>
    <div class="notificaciones d-flex justify-content-center">
        
    </div>

</form>