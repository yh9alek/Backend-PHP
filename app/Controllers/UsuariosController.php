<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\KeycloakService;

class UsuariosController extends BaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->keycloakService = new KeycloakService();
    }

    /**
     * POST /api/usuarios/batch
     * Obtiene información de múltiples usuarios por sus UUIDs
     * 
     * Body: { "user_ids": ["uuid1", "uuid2", ...] }
     */
    public function batch(Request $request): Response
    {
        $userIds = $request->input('user_ids', []);

        if (empty($userIds) || !is_array($userIds)) {
            return $this->error('Se requiere un array de user_ids', 400);
        }

        try {
            $usuarios = $this->keycloakService->getUsersByIds($userIds);
            
            return $this->json([
                'success' => true,
                'data' => $usuarios
            ]);
        } catch (\Exception $e) {
            return $this->error('Error al obtener usuarios: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/usuarios/search?q=termino&max=10
     * Busca usuarios por nombre, email, etc.
     */
    public function search(Request $request): Response
    {
        $query = $request->query('q', '');
        $max = (int) $request->query('max', 10);

        if (empty($query)) {
            return $this->error('Se requiere un término de búsqueda', 400);
        }

        try {
            $usuarios = $this->keycloakService->searchUsers($query, $max);
            
            return $this->json([
                'success' => true,
                'data' => $usuarios
            ]);
        } catch (\Exception $e) {
            return $this->error('Error al buscar usuarios: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/usuarios/{userId}
     * Obtiene información de un usuario específico
     */
    public function show(Request $request): Response
    {
        $userId = $request->param('id');

        try {
            $usuario = $this->keycloakService->getUserById($userId);
            
            if (!$usuario) {
                return $this->error('Usuario no encontrado', 404);
            }
            
            return $this->json([
                'success' => true,
                'data' => $usuario
            ]);
        } catch (\Exception $e) {
            return $this->error('Error al obtener usuario: ' . $e->getMessage(), 500);
        }
    }
}