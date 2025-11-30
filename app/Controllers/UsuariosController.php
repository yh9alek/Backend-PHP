<?php

namespace app\Controllers;

use app\Core\QueryBuilder;
use app\Core\Request;
use app\Core\Response;
use app\Models\UserModel;
use Exception;

use function app\helpers\formatearFecha;

class UsuariosController extends BaseController
{
    public function mostrarModuloUsuarios(): Response
    {

        $assets = [
            'css' => ['css/modules/usuarios.css'],
            'js'  => ['js/modules/usuarios.js']
        ];

        $html = $this->view->renderPartial('usuarios');

        return $this->json([
            'html'   => $html,
            'assets' => $assets
        ]);
    }

    public function proc(): Response
    {

        $db = new QueryBuilder();

        $params = [
            ['name' => 'profileId', 'value' => 1, 'type' => \PDO::PARAM_INT, 'direction' => 'IN']
        ];

        $response = $db->call('GetUsersByProfile', $params);

        die(print_r($response));
    }

    public function obtenerUsuarios(): Response
    {

        $usersWithProfile = UserModel::with(['profile', 'createdByUser', 'updatedByUser'])->get();

        $formato = array_map(function ($user) {

            $fullNameCreateUser = "{$user->create->nombre} {$user->create->apellidoPaterno} {$user->create->apellidoMaterno}";
            $fullNameUpdateUser = "{$user->update->nombre} {$user->update->apellidoPaterno} {$user->update->apellidoMaterno}";

            return [
                'id'          => $user->id,
                'name'        => $user->username,
                'email'       => $user->email,
                'profile'     => $user->profile ? $user->profile->name : 'Sin rol asignado',
                'create_user' => $fullNameCreateUser,
                'created_at'  => formatearFecha($user->createdAt),
                'update_user' => $fullNameUpdateUser,
                'updated_at'  => formatearFecha($user->updatedAt),
            ];
        }, $usersWithProfile);

        return $this->json([
            'data' => $formato
        ]);
    }

    public function obtenerUsuariosSinORM(Request $request): Response
    {
        extract($request->allParams());

        $whereClause = '';
        $params = [];
        
        if (!empty($search)) {

            $booleanSearch = '+' . str_replace(' ', ' +', $search) . '*';
            $whereClause = "MATCH(u.nombre, u.apellido_paterno, u.apellido_materno, u.username, u.email) AGAINST (:search IN BOOLEAN MODE)";
            $params['search'] = $booleanSearch;
        }

        $db = new QueryBuilder();

        if (!($response = $db->paginate(
            table: 'user u',
            page: $page,
            perPage: $limit,
            columns: [
                'u.id',
                "CONCAT_WS(' ', u.nombre, u.apellido_paterno, u.apellido_materno) as nombreCompleto",
                'u.nombre nombres',
                'u.apellido_paterno apellido_p',
                'u.apellido_materno apellido_m',
                'p.name AS profile',
                'p.id AS perfil',
                'a.id AS area',
                'u.email correo',
                'u.telefono',
                'u.username',
                'DATE_FORMAT(u.created_at, "%d/%m/%Y %T") created_at',
                'DATE_FORMAT(u.updated_at, "%d/%m/%Y %T") updated_at',
                "CONCAT_WS(' ', creator.nombre, creator.apellido_paterno) AS create_user",
                "CONCAT_WS(' ', updater.nombre, updater.apellido_paterno) AS update_user"
            ],
            where: $whereClause,
            params: $params,
            joins: [
                ['type' => 'LEFT', 'table' => 'profile p',    'on' => 'u.profile_id  = p.id'],
                ['type' => 'LEFT', 'table' => 'area    a',    'on' => 'u.area_id     = a.id'],
                ['type' => 'LEFT', 'table' => 'user creator', 'on' => 'u.create_user = creator.id'],
                ['type' => 'LEFT', 'table' => 'user updater', 'on' => 'u.update_user = updater.id']
            ],
            extras: 'ORDER BY u.id DESC'
        ))->success)
            return $this->json((array) $response, 500);

        return $this->json($response->data);
    }

    public function obtenerUsuariosProc(Request $request): Response
    {
        // 1. Extraer y preparar los parámetros
        extract($request->allParams());
        $offset = ($page - 1) * $limit;

        $db = new QueryBuilder();

        // 2. Definir los parámetros para el procedimiento almacenado
        $params = [
            'search_term' => @$search,
            'page_limit'  => (int) $limit,
            'page_offset' => (int) $offset
        ];

        // 3. Llamar al procedimiento almacenado usando nuestro QueryBuilder
        $response = $db->callPaginatedSP('sp_get_users_paginated', $params);

        if (!$response->success) {
            return $this->json((array) $response, 500);
        }

        $items       = $response->data['items'] ?? [];
        $totalFromSP = $response->data['total'] ?? 0;

        $paginatedData = [
            'items' => $items,
            'meta' => [
                'total'        => (int) $totalFromSP,
                'per_page'     => (int) $limit,
                'current_page' => (int) $page,
                'last_page'    => (int) ceil($totalFromSP / $limit),
                'from'         => $totalFromSP > 0 ? $offset + 1 : 0,
                'to'           => $totalFromSP > 0 ? $offset + count($items) : 0,
            ]
        ];

        return $this->json($paginatedData);

    }

    public function guardarUsuario(Request $request): Response
    {
        # 1. Validar parámetros enviados desde el formulario al backend
        $camposRequeridos = [
            'username',
            'perfil',
            'area',
            'nombres',
            'apellido_p'
        ];

        if (($response = $this->validate($request, $camposRequeridos))->statusCode != 200)
            return $response;

        # 2. Extraer cada parámetro en variables independientes.
        extract($request->allParams());

        # 3. Preparar el array de datos base (común para INSERT y UPDATE).
        $data = [
            'profile_id'       => $perfil,
            'area_id'          => $area,
            'nombre'           => $nombres,
            'apellido_paterno' => $apellido_p,
            'apellido_materno' => $apellido_m ?? null,
            'username'         => $username,
            'email'            => $correo,
            'pass'             => 123,
            'telefono'         => $telefono ?? null,
        ];

        $db = new QueryBuilder();

        $response = null;

        # 4. ¿Actualizar o Insertar?
        if ($id) {

            // --- LÓGICA DE UPDATE ---

            // Añadimos los campos específicos de actualización
            $data['update_user'] = $this->jwtService->validateToken()->user_id;
            $data['updated_at']  = date('Y-m-d H:i:s');

            // Ejecutamos la actualización
            $response = $db->update(
                table: 'user',
                data: $data,
                where: 'id = :id',
                params: ['id' => $id]
            );

            $successMessage = 'El usuario ha sido actualizado con éxito.';
            $successCode = 200; // OK

        } else {

            // --- LÓGICA DE INSERT ---

            $data['create_user'] = $this->jwtService->validateToken()->user_id;
            $data['created_at']  = date('Y-m-d H:i:s');

            // Ejecutamos la inserción
            $response = $db->insert(
                table: 'user',
                data: $data
            );

            $successMessage = 'Se ha creado el usuario con éxito.';
            $successCode = 201; // Created
        }

        # 5. Manejar la respuesta de la base de datos y enviar el JSON final.
        if (!$response->success) {
            return $this->json((array) $response, 500);
        }

        return $this->json(['msg' => $successMessage], $successCode);
    }
}
