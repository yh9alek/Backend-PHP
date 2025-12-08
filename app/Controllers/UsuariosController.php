<?php

namespace app\Controllers;

use app\Core\QueryBuilder;
use app\Core\Request;
use app\Core\Response;
use app\Helpers\Uuid;
use app\Models\UserModel;

use function app\helpers\formatearFecha;

/**
 * Controlador RESTful para el recurso "usuarios".
 * Ya no renderiza HTML, solo devuelve JSON.
 */
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
            $whereClause = "MATCH(u.nombre, u.apellido_paterno, u.apellido_materno, u.username, u.email) AGAINST (:search IN BOOLEAN MODE)";
            $params['search'] = $booleanSearch;
        }

        $db = new QueryBuilder();
        $response = $db->paginate(
            table: 'user u',
            page: $page,
            perPage: $limit,
            columns: [
                'u.uuid',  // UUID en lugar de ID
                "CONCAT_WS(' ', u.nombre, u.apellido_paterno, u.apellido_materno) as nombreCompleto",
                'u.username',
                'u.email',
                'u.telefono',
                'p.name AS profile',
                'p.uuid AS profile_uuid',
                'a.name AS area',
                'a.uuid AS area_uuid',
                'DATE_FORMAT(u.created_at, "%d/%m/%Y %T") created_at',
                'DATE_FORMAT(u.updated_at, "%d/%m/%Y %T") updated_at',
            ],
            where: $whereClause,
            params: $params,
            joins: [
                ['type' => 'LEFT', 'table' => 'profile p', 'on' => 'u.profile_id = p.id'],
                ['type' => 'LEFT', 'table' => 'area a',    'on' => 'u.area_id = a.id'],
            ],
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
     * GET /api/usuarios/{uuid}
     */
    public function show(Request $request): Response
    {
        $uuid = $request->param('id');

        if (!Uuid::isValid($uuid)) {
            return $this->error('UUID inválido', 400);
        }

        $user = UserModel::with(['profile', 'area'])
            ->whereUuid($uuid)
            ->limit(1)
            ->get()[0] ?? null;

        if (!$user) {
            return $this->json([
                'success' => false,
                'error' => 'Usuario no encontrado'
            ], 404);
        }

        return $this->json([
            'success' => true,
            'data' => [
                'uuid'     => $user->uuid,
                'username' => $user->username,
                'email'    => $user->email,
                'nombre'   => $user->nombre,
                'apellido_paterno' => $user->apellidoPaterno,
                'apellido_materno' => $user->apellidoMaterno,
                'telefono' => $user->telefono,
                'profile'  => $user->profile ?? null,
                'area'     => $user->area ?? null,
                'created_at' => formatearFecha($user->createdAt),
                'updated_at' => formatearFecha($user->updatedAt),
            ]
        ]);
    }

    /**
     * POST /api/usuarios
     */
    public function store(Request $request): Response
    {
        if (($response = $this->validate($request, [
            'username',
            'email',
            'profile_id',
            'area_id',
            'nombre',
            'apellido_paterno'
        ]))->statusCode != 200) {
            return $response;
        }

        extract($request->allParams());

        $data = [
            'uuid'             => Uuid::generate(),
            'profile_id'       => $profile_id,
            'area_id'          => $area_id,
            'nombre'           => $nombre,
            'apellido_paterno' => $apellido_paterno,
            'apellido_materno' => $apellido_materno ?? null,
            'username'         => $username,
            'email'            => $email,
            'telefono'         => $telefono ?? null,
            'pass'             => '',
            'create_user'      => $this->getUserIdFromToken($request),
            'created_at'       => date('Y-m-d H:i:s')
        ];

        $db = new QueryBuilder();
        $result = $db->insert('user', $data);

        if (!$result->success) {
            return $this->error($result->error ?? 'Error al crear usuario.', 500);
        }

        return $this->json([
            'success' => true,
            'message' => 'Usuario creado exitosamente.',
            'data' => [
                'uuid' => $data['uuid']  // Devolver UUID
            ]
        ], 201);
    }

    /**
     * PUT /api/usuarios/{uuid}
     */
    public function updateSinORM(Request $request): Response
    {
        $uuid = $request->param('id');

        if (!Uuid::isValid($uuid)) {
            return $this->error('UUID inválido', 400);
        }

        extract($request->allParams());

        $data = [
            'profile_id'       => $profile_id,
            'area_id'          => $area_id,
            'nombre'           => $nombre,
            'apellido_paterno' => $apellido_paterno,
            'apellido_materno' => $apellido_materno,
            'username'         => $username,
            'email'            => $email,
            'telefono'         => $telefono,
            'update_user'      => $this->getUserIdFromToken($request),
            'updated_at'       => date('Y-m-d H:i:s')
        ];

        $db = new QueryBuilder();

        $result = $db->update(
            table: 'user',
            data: $data,
            where: 'uuid = :uuid',
            params: ['uuid' => $uuid]
        );

        if (!$result->success) {
            return $this->error($result->error ?? 'Error al actualizar usuario.', 500);
        }

        return $this->success(null, 'Usuario actualizado exitosamente.');
    }

    /**
     * PUT /api/usuarios/{uuid}
     */
    public function update(Request $request): Response
    {
        # Validar uuid
        $uuid = $request->param('id');
        if (!Uuid::isValid($uuid)) {
            return $this->error('UUID inválido', 400);
        }

        # Verificar existencia del registro a modificar
        if(!($user = UserModel::find($uuid))) {
            return $this->error($result->error ?? 'Usuario no encontrado.', 500);
        }

        $user->merge($request->allParams());
        $user->updateUser = $this->getUserIdFromToken($request);

        if (!$user->save()) {
            return $this->error($result->error ?? 'Error al actualizar usuario.', 500);
        }

        return $this->success(null, 'Usuario actualizado exitosamente.');
    }

    /**
     * DELETE /api/usuarios/{uuid}
     */
    public function destroy(Request $request): Response
    {
        $uuid = $request->param('id');

        if (!Uuid::isValid($uuid)) {
            return $this->error('UUID inválido', 400);
        }

        $success = UserModel::deleteByUuid($uuid);

        if (!$success) {
            return $this->error('Error al eliminar usuario.', 500);
        }

        return $this->success(null, 'Usuario eliminado exitosamente.');
    }

    private function getUserIdFromToken(Request $request): int
    {
        // $keycloakId = $request->userId();
        $user = UserModel::where('username', '=', $request->username())
            ->limit(1)
            ->get()[0] ?? null;

        return $user?->id ?? 1;
    }
}