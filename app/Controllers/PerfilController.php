<?php

namespace app\Controllers;

use app\Core\QueryBuilder;
use app\Core\Request;

class PerfilController extends BaseController
{
    public function obtenerPerfiles() {

        $db = new QueryBuilder();

        $response = $db->select(
            table: 'profile p',
            columns: ['p.id', 'p.name'],
        );

        return $this->json([
            'data' => $response->data
        ]);

    }
}