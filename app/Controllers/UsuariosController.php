<?php

namespace app\Controllers;

use app\Core\QueryBuilder;
use app\Core\Request;
use app\Core\Response;
use app\Core\Validator;
use app\Helpers\Uuid;
use app\Models\UserModel;

class UsuariosController extends BaseController
{

    /**
     * GET /api/usuarios
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->query('page', 1);
        $limit = (int) $request->query('limit', 15);
        $search = $request->query('search', '');

        $whereClause = '';
        $params = [];

        if (!empty($search)) {
            $booleanSearch = '+' . str_replace(' ', ' +', $search) . '*';
            $whereClause = "MATCH(u.nombre, u.apellido_paterno, u.apellido_materno, u.username, u.email, u.telefono) AGAINST (:search IN BOOLEAN MODE)";
            $params['search'] = $booleanSearch;
        }

        $db = new QueryBuilder();
        $response = $db->paginate(
            table: 'usuarios u',
            page: $page,
            perPage: $limit,
            columns: [
                'u.uuid',
                "CONCAT_WS(' ', u.nombre, u.apellido_paterno, u.apellido_materno) as nombreCompleto",
                'u.username',
                'u.email',
                'u.telefono',
                'DATE_FORMAT(u.created_at, "%d/%m/%Y %T") created_at',
                'DATE_FORMAT(u.updated_at, "%d/%m/%Y %T") updated_at',
            ],
            where: $whereClause,
            params: $params,
            extras: 'ORDER BY u.created_at DESC'
        );

        if (!$response->success) {
            return $this->error($response->error ?? 'Error al obtener usuarios.', 500);
        }

        return $this->json([
            'success' => true,
            'data' => $response->data
        ]);
    }

    /**
     * GET /api/usuarios
     */
    // public function index(Request $request): Response
    // {
    //     $db = new QueryBuilder();
    //     $response = $db->select(
    //         table: 'usuarios u',
    //         columns: [
    //             'u.uuid',
    //             "CONCAT_WS(' ', u.nombre, u.apellido_paterno, u.apellido_materno) as nombreCompleto",
    //             'u.username',
    //             'u.email',
    //             'u.telefono',
    //             'DATE_FORMAT(u.created_at, "%d/%m/%Y %T") created_at',
    //             'DATE_FORMAT(u.updated_at, "%d/%m/%Y %T") updated_at',
    //         ],
    //         extras: 'ORDER BY u.created_at DESC'
    //     );

    //     if (!$response->success) {
    //         return $this->error($response->error ?? 'Error al obtener usuarios.', 500);
    //     }

    //     return $this->json([
    //         'success' => true,
    //         'data' => $response->data
    //     ]);
    // }

    /**
     * GET /api/usuarios/{uuid}
     */
    public function show(Request $request): Response
    {
        $uuid = $request->param('id');

        $validator = Validator::make(
            ['uuid' => $uuid],
            ['uuid' => 'required|uuid']
        );

        if ($validator->fails()) {
            return $this->json($validator->getErrorResponse(), 400);
        }

        $db = new QueryBuilder();
        $response = $db->select(
            table: 'user u',
            columns: [
                'u.uuid', 
                'u.username', 
                'u.email', 
                'u.nombre', 
                'u.apellido_paterno', 
                'u.apellido_materno', 
                'u.telefono', 
                'p.name',
                'DATE_FORMAT(u.created_at, "%d/%m/%Y %T") created_at',
                'DATE_FORMAT(u.updated_at, "%d/%m/%Y %T") updated_at',
            ],
            joins: [
                ['type' => 'INNER', 'table' => 'profile p', 'on' => 'u.profile_id = p.id']
            ],
            where: 'u.uuid = :uuid',
            params: ['uuid' => $uuid]
        );

        if (!$response->success) {
            return $this->json([
                'success' => false,
                'error' => 'Usuario no encontrado.'
            ], 404);
        }

        $data = $response->data;

        return $this->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * POST /api/usuarios
     */
    public function store(Request $request): Response
    {
        $db = new QueryBuilder();
        
        // Validar datos y resolver UUIDs a IDs
        $validator = Validator::make($request->allParams(), [
            'username'         => 'required|max:50',
            'profile_id'       => 'required|uuid|exists:profile',  
            'area_id'          => 'required|uuid|exists:area',
            'nombre'           => 'required|max:50',
            'apellido_paterno' => 'required|max:50',
            'apellido_materno' => 'max:50',
            'email'            => 'email|max:50',
            'telefono'         => 'numeric|max:10',
        ], $db);

        if ($validator->fails()) {
            return $this->json($validator->getErrorResponse(), 422);
        }

        // Obtener los IDs enteros resueltos
        $resolvedIds = $validator->getResolvedIds();

        extract($request->allParams());

        // Preparar datos para inserción
        $data = [
            'uuid'             => Uuid::generate(),
            'profile_id'       => $resolvedIds['profile_id'],
            'area_id'          => $resolvedIds['area_id'],
            'nombre'           => $nombre,
            'apellido_paterno' => $apellido_paterno,
            'apellido_materno' => $apellido_materno ?? null,
            'username'         => $username,
            'email'            => $email ?? null,
            'telefono'         => $telefono ?? null,
            'create_user'      => $this->getUserIdFromToken($request),
        ];

        $result = $db->insert('user', $data);

        if (!$result->success) {
            return $this->error($result->error ?? 'Error al crear usuario.');
        }

        return $this->json([
            'success' => true,
            'message' => 'Usuario creado exitosamente.',
            'data' => [
                'uuid' => $data['uuid']
            ]
        ], 201);
    }

    /**
     * PUT /api/usuarios/{uuid}
     */
    public function update(Request $request): Response
    {
        $uuid = $request->param('id');

        // Validar UUID del recurso
        $uuidValidator = Validator::make(
            ['uuid' => $uuid],
            ['uuid' => 'required|uuid']
        );

        if ($uuidValidator->fails()) {
            return $this->json($uuidValidator->getErrorResponse(), 400);
        }

        // Verificar existencia del usuario
        if (!($user = UserModel::find($uuid))) {
            return $this->error('Usuario no encontrado.', 404);
        }

        $db = new QueryBuilder();

        // Validar datos de actualización y resolver UUIDs
        $validator = Validator::make($request->allParams(), [
            'username'         => 'max:50',
            'profile_id'       => 'uuid|exists:profile',
            'area_id'          => 'uuid|exists:area',
            'nombre'           => 'max:50',
            'apellido_paterno' => 'max:50',
            'apellido_materno' => 'max:50',
            'email'            => 'email|max:50',
            'telefono'         => 'max:10',
        ], $db);

        if ($validator->fails()) {
            return $this->json($validator->getErrorResponse(), 422);
        }

        // Obtener los IDs resueltos
        $resolvedIds = $validator->getResolvedIds();

        // Merge de datos incluyendo los IDs enteros
        $updateData = array_merge($request->allParams(), [
            'profile_id' => $resolvedIds['profile_id'],
            'area_id'    => $resolvedIds['area_id'],
        ]);

        $user->merge($updateData);
        $user->updateUser = $this->getUserIdFromToken($request);

        if (!$user->save()) {
            return $this->error('Error al actualizar usuario.', 500);
        }

        return $this->success(null, 'Usuario actualizado exitosamente.');
    }

    /**
     * DELETE /api/usuarios/{uuid}
     */
    public function destroy(Request $request): Response
    {
        $uuid = $request->param('id');

        $validator = Validator::make(
            ['uuid' => $uuid],
            ['uuid' => 'required|uuid']
        );

        if ($validator->fails()) {
            return $this->json($validator->getErrorResponse(), 400);
        }

        // Verificar existencia del usuario
        if (!($user = UserModel::find($uuid))) {
            return $this->error('Usuario no encontrado.', 404);
        }

        $success = UserModel::deleteByUuid($uuid);

        if (!$success) {
            return $this->error('Error al eliminar usuario.', 500);
        }

        return $this->success(null, 'Usuario eliminado exitosamente.');
    }

    private function getUserIdFromToken(Request $request): int
    {
        $user = UserModel::where('username', '=', $request->username())
            ->limit(1)
            ->get()[0] ?? null;

        return $user?->id ?? 1;
    }
}