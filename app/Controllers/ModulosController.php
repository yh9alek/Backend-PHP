<?php

namespace app\Controllers;

use app\Core\QueryBuilder;
use app\Core\Request;
use app\Core\Response;
use app\services\MenuService;

class ModulosController extends BaseController
{
    private MenuService $menuService;

    public function __construct($container)
    {
        parent::__construct($container);
        $this->menuService = new MenuService();
    }

    /**
     * GET /api/modulos/usuario
     * 
     * Obtiene los módulos del usuario actual basado en sus Client Roles de Keycloak.
     * Retorna una estructura jerárquica de módulos.
     */
    public function getUserModules(Request $request): Response
    {
        // Obtener el usuario autenticado (establecido por AuthMiddleware)
        $user = $request->user();

        if (!$user) {
            return $this->error('Usuario no autenticado.', 401);
        }

        // Obtener módulos según los roles del usuario
        $modules = $this->menuService->getModulesByUserRoles($user);

        return $this->success($modules, 'Módulos obtenidos exitosamente.');
    }

    /**
     * GET /api/modulos
     * Lista todos los módulos con paginación y búsqueda.
     */
    public function index(Request $request): Response {

        $page = (int) $request->query('page', 1);
        $limit = (int) $request->query('limit', 7);
        $search = $request->query('search', '');

        $where = '';
        $params = [];

        if (!empty($search)) {
            $booleanSearch = '+' . str_replace(' ', ' +', $search) . '*';
            $where = "MATCH(cm.nombre) AGAINST (:search IN BOOLEAN MODE)";
            $params['search'] = $booleanSearch;
        }

        $db = new QueryBuilder();
        $response = $db->paginate(
            table: 'ca_modulo cm',
            page: $page,
            perPage: $limit,
            columns: [
                'cm.uuid',
                'cm.nombre',
                'cm.descripcion',
                'cm.icono',
                'cm.parent_id',
                'cm.orden',
                'cm.estatus',
                'DATE_FORMAT(cm.fecha_alta, "%d/%m/%Y %T") fecha_alta',
                'DATE_FORMAT(cm.fecha_mod, "%d/%m/%Y %T") fecha_mod',
            ],
            where: $where,
            params: $params,
            extras: 'ORDER BY cm.fecha_alta DESC'
        );

        if (!$response->success) {
            return $this->error($response->error ?? 'Error al obtener los módulos.', 500);
        }

        return $this->json([
            'success' => true,
            'data' => $response->data
        ]);
    }

    /**
     * GET /api/modulos/{id}
     * Obtiene un módulo específico por su ID.
     */
    public function show(Request $request): Response
    {
        try {
            $id = (int) $request->param('id');

            if (!$id) {
                return $this->error('ID de módulo inválido.', 400);
            }

            $module = $this->menuService->getModuleById($id);

            if (!$module) {
                return $this->error('Módulo no encontrado.', 404);
            }

            return $this->success($module, 'Módulo obtenido exitosamente.');

        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
