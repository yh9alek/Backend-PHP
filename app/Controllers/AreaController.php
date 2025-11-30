<?php

namespace app\Controllers;

use app\Core\QueryBuilder;
use app\Core\Request;

class AreaController extends BaseController
{
    public function obtenerAreas() {

        $db = new QueryBuilder();

        $response = $db->select(
            table: 'area a',
            columns: ['a.id', 'a.name'],
        );

        return $this->json([
            'data' => $response->data
        ]);

    }
}