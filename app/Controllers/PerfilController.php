<?php

namespace app\Controllers;

use app\Core\QueryBuilder;
use app\Core\Request;
use app\Core\Response;
use app\Core\Validator;

class PerfilController extends BaseController {
    
     /**
     * GET /api/perfiles
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
            $whereClause = "MATCH(p.name, p.descripcion) AGAINST (:search IN BOOLEAN MODE)";
            $params['search'] = $booleanSearch;
        }

        $db = new QueryBuilder();
        $response = $db->paginate(
            table: 'profile p',
            page: $page,
            perPage: $limit,
            columns: [
                'p.uuid',
                'p.name',
                'p.description',
            ],
            where: $whereClause,
            params: $params,
            extras: 'ORDER BY p.created_at DESC'
        );

        if (!$response->success) {
            return $this->error($response->error ?? 'Error al obtener los perfiles.', 500);
        }

        return $this->json([
            'success' => true,
            'data' => $response->data
        ]);
    }

    public function show(Request $request): Response {

        $validator = Validator::make(
            ['uuid' => $request->param('id')],
            ['uuid' => 'required|uuid']
        );

        if($validator->fails()) {
            return $this->error('El uuid es requerido.', 400);
        }

        $db = new QueryBuilder();

        $response = $db->select(
            table: 'profile p',
            columns: ['p.uuid', 'p.name', 'p.description'],
            extras: 'ORDER BY p.created_at DESC'
        );

        if(!$response->success) {
            return $this->error('Error al consultar el perfil');
        }

        return $this->json([
            'success' => true,
            'data' => [...$response->data]
        ]);

    }

}