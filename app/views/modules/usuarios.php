<?php

use function app\helpers\e;
use function app\helpers\route;

?>

<nav aria-label="breadcrumb" class="mt-2">
    <h1 class="page-title">Usuarios</h1>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="#">Usuarios</a></li>
        <li class="breadcrumb-item"><a href="#">Registro</a></li>
        <li class="breadcrumb-item active" aria-current="page">.</li>
    </ol>
</nav>

<button class="btn btn-primary btn-agregar-usuarios" style="position: relative; top: 35px; z-index: 10;" data-bs-toggle="modal" data-bs-target="#modal-agregar-usuario">Agregar</button>
<div class="grid-usuarios"></div>

<!-- MODALES -->
<div class="modal fade" id="modal-agregar-usuario" tabindex="-1" aria-labelledby="modal-agregar-usuarioLabel" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog" style="max-width: 798px;">
        <form class="modal-content formulario-registro" novalidate>

            <input type="hidden" name="id">

            <div class="modal-header">
                <h2 class="modal-title" id="modal-agregar-usuarioLabel"> <i style="position: relative; top: -3px;" data-lucide="user-round-plus"></i> &nbsp;<span>Nuevo Usuario</span></h2>
            </div>

            <div class="modal-body">

                <div class="row">
                    <div class="mb-3 col-sm-4">
                        <label for="username" class="form-label">Usuario: <span class="ast">*</span></label>
                        <input type="text" class="form-control" id="username" name="username" autocomplete="off" placeholder="example.user" required>
                    </div>
                    <div class="mb-3 col-sm-4">
                        <label for="perfil" class="form-label">Perfil: <span class="ast">*</span></label>
                        <div class="perfil" required></div>
                    </div>
                    <div class="mb-3 col-sm-4">
                        <label for="area" class="form-label">Area: <span class="ast">*</span></label>
                        <div class="area" required></div>
                    </div>
                </div>

                <div class="row">
                    <div class="mb-3 col-sm-6">
                        <label for="nombres" class="form-label">Nombres: <span class="ast">*</span></label>
                        <input type="text" class="form-control" id="nombres" name="nombres" autocomplete="off" placeholder="Nombres" required>
                    </div>
                    <div class="mb-3 col-sm-3">
                        <label for="apellido_p" class="form-label">Apellido Paterno: <span class="ast">*</span></label>
                        <input type="text" class="form-control" id="apellido_p" name="apellido_p" autocomplete="off" placeholder="Apellido Paterno" required>
                    </div>
                    <div class="mb-3 col-sm-3">
                        <label for="apellido_m" class="form-label">Apellido Materno:</label>
                        <input type="text" class="form-control" id="apellido_m" name="apellido_m" autocomplete="off" placeholder="Apellido Materno">
                    </div>
                </div>
                
                <div class="row">
                    <div class="mb-3 col-sm-3">
                        <label for="telefono" class="form-label">Teléfono:</label>
                        <input type="tel" class="form-control" id="telefono" name="telefono" autocomplete="off" placeholder="0000000000">
                    </div>
                    <div class="mb-3 col-sm-6">
                        <label for="correo" class="form-label">Correo:</label>
                        <input type="email" class="form-control" id="correo" name="correo" autocomplete="off" placeholder="example@domain.com">
                    </div>
                </div>

                <p class="label-campos-o">( <span class="ast">*</span> ) Campos obligatorios</p>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="submit" class="btn btn-primary btnRegistrar" style="width: 81.55px;">Guardar</button>
            </div>

        </form>
    </div>
</div>