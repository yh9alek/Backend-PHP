<?php

namespace app\Controllers;

use app\Core\Request;
use app\Core\Response;
use app\Models\ModuleModel;
use app\Models\ProfileModel;

class AppController extends BaseController
{

    public function inicio() {

        # Obtener datos del usuario configurados en el payload del token JWT
        $userData     = $this->jwtService->validateToken();

        # Obtener los módulos a los que el perfil actual tiene acceso junto con su categoría.
        $userProfile  = ProfileModel::with(['modules.category'])
                          ->where('id', '=', $userData->profile_id)
                          ->get()[0];
        
        # Generar arbol de modulos para el sidebar
        $profileModules = $this->menuService->structureModulesForMenu(
            $userProfile->name != 'ADMIN' ? $userProfile->modules :
            ModuleModel::with(['category'])->get()
        );

        return $this->view('inicio', [

            # Información del usuario
            'session' => $userData,
            # Módulos a los que el perfil del usuario tiene acceso.
            'modules' => $profileModules

        ], '_home');

    }

    public function obtenerTiempoSesion() {
        return $this->json([
            'tiempoSesion' => $this->jwtService->validateToken()->remaining
        ]);
    }

    // public function do(Request $request): Response
    // {

    //     $post     = $request->param('post');
    //     $ciudad   = $request->param('ciudad');
    //     $inicio   = $request->param('inicio');
    //     $final    = $request->param('final');

    //     $files    = $_FILES['pedimentos'];

    //     return new Response(print_r($_FILES['pedimentos']));
    // }
}
