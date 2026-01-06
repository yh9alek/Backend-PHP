<?php

namespace app\services;

use app\Core\QueryBuilder;
use stdClass;

class MenuService
{
    /**
     * Obtiene los módulos a los que el usuario tiene acceso según sus Client Roles de Keycloak.
     * Retorna una estructura jerárquica de módulos (padres con hijos).
     * 
     * @param stdClass $decodedToken Token JWT decodificado del usuario
     * @param string|null $clientId ID del cliente de Keycloak (opcional)
     * @return array Estructura jerárquica de módulos
     */
    public function getModulesByUserRoles(stdClass $decodedToken, ?string $clientId = null): array
    {
        // Obtener los roles de cliente del usuario desde el token
        $clientId = $clientId ?? $_ENV['KEYCLOAK_CLIENT_ID'];
        $userRoles = $decodedToken->resource_access->$clientId->roles ?? [];

        if (empty($userRoles)) {
            return []; // El usuario no tiene roles de cliente
        }

        // Construir placeholders para la consulta SQL
        $placeholders = [];
        $params = [];
        
        foreach ($userRoles as $index => $role) {
            $key = "rol_$index";
            $placeholders[] = ":$key";
            $params[$key] = $role;
        }
        
        $placeholdersStr = implode(', ', $placeholders);

        // Consultar módulos a los que el usuario tiene acceso
        $db = new QueryBuilder();
        $response = $db->select(
            table: 'ca_modulo cm',
            columns: [
                'cm.id',
                'cm.uuid',
                'cm.nombre',
                'cm.descripcion',
                'cm.icono',
                'cm.parent_id',
                'cm.orden',
                'cm.estatus'
            ],
            joins: [
                [
                    'type' => 'INNER',
                    'table' => 'de_rol_modulo drm',
                    'on' => 'cm.id = drm.modulo_id'
                ]
            ],
            where: "drm.rol_keycloak IN ($placeholdersStr) AND cm.estatus = 1",
            params: $params,
            extras: 'GROUP BY cm.id ORDER BY cm.orden ASC, cm.nombre ASC'
        );

        if (!$response->success || empty($response->data)) {
            return []; // No se encontraron módulos o hubo un error
        }

        // Construir jerarquía de módulos
        return $this->buildModuleHierarchy($response->data);
    }

    /**
     * Construye una estructura jerárquica de módulos (padres con hijos).
     * 
     * @param array $modules Lista plana de módulos
     * @return array Estructura jerárquica
     */
    public function buildModuleHierarchy(array $modules): array
    {
        if (empty($modules)) {
            return [];
        }

        $modules = array_map(function($module) {
            return is_array($module) ? (object) $module : $module;
        }, $modules);

        // Indexar módulos por parent_id para acceso rápido
        $modulesByParent = [];
        $modulesById = [];

        foreach ($modules as $module) {
            // Inicializar array de hijos
            $module->children = [];
            
            // Indexar por ID
            $modulesById[$module->id] = $module;
            
            // Agrupar por parent_id
            $parentId = $module->parent_id ?? 'root';
            if (!isset($modulesByParent[$parentId])) {
                $modulesByParent[$parentId] = [];
            }
            $modulesByParent[$parentId][] = $module;
        }

        // Asignar hijos a sus padres
        foreach ($modules as $module) {
            if (isset($modulesByParent[$module->id])) {
                $module->children = $modulesByParent[$module->id];
            }
        }

        // Retornar solo los módulos raíz (sin parent_id o parent_id = null)
        return $modulesByParent['root'] ?? [];
    }

    /**
     * Obtiene todos los módulos activos del sistema.
     * 
     * @return array Lista de todos los módulos
     */
    public function getAllModules(): array
    {
        $db = new QueryBuilder();
        $response = $db->select(
            table: 'ca_modulo',
            where: 'estatus = 1',
            extras: 'ORDER BY orden ASC, nombre ASC'
        );

        return $response->success ? $response->data : [];
    }

    /**
     * Obtiene un módulo específico por su ID.
     * 
     * @param int $id ID del módulo
     * @return stdClass|null Módulo encontrado o null
     */
    public function getModuleById(int $id): ?stdClass
    {
        $db = new QueryBuilder();
        $response = $db->select(
            table: 'ca_modulo',
            where: 'id = :id AND estatus = 1',
            params: ['id' => $id]
        );

        if ($response->success && !empty($response->data)) {
            return $response->data[0];
        }

        return null;
    }

    /**
     * Transforma una lista plana de módulos en una estructura jerárquica para un menú.
     *
     * @param array  $flatModules Array de objetos ModuleModel, cada uno con su relación 'category' cargada.
     * @return array Estructura de menú anidada.
     */
    function structureModulesForMenu(array $flatModules): array
    {

        if (empty($flatModules)) {
            return [];
        }

        $groupedByCategory = [];

        # 1. Agrupar todos los módulos por su ID de categoría
        foreach ($flatModules as $module) {

            // Asegurarse de que la categoría está cargada
            if (!isset($module->category)) {
                continue;
            }
            $categoryId = $module->category->id;

            // Si es la primera vez que vemos esta categoría, la inicializamos
            if (!isset($groupedByCategory[$categoryId])) {
                $groupedByCategory[$categoryId] = [
                    'category' => $module->category,
                    'modules'  => []
                ];
            }

            // Añadimos el módulo a su categoría correspondiente
            $groupedByCategory[$categoryId]['modules'][] = $module;
        }

        $structuredMenu = [];

        # 2. Procesar cada categoría para construir la jerarquía de módulos
        foreach ($groupedByCategory as $categoryGroup) {
            $modules_in_category = $categoryGroup['modules'];
            $root_modules = [];
            $children_modules = [];

            // a. Separar módulos raíz de los hijos y preparar para anidación
            foreach ($modules_in_category as $module) {
                $module->children = []; // Añadimos una propiedad para los hijos
                if (empty($module->rootModuleId)) {
                    // Es un módulo raíz, lo usamos como clave para encontrarlo fácilmente
                    $root_modules[$module->id] = $module;
                } else {
                    // Es un módulo hijo, lo agrupamos por su padre
                    $children_modules[$module->rootModuleId][] = $module;
                }
            }

            // b. Anidar los módulos hijos dentro de sus padres
            foreach ($children_modules as $parentId => $children) {
                if (isset($root_modules[$parentId])) {
                    $root_modules[$parentId]->children = $children;
                }
                // Opcional: podrías manejar aquí hijos "huérfanos" si fuera necesario
            }

            // 3. Añadir la categoría con sus módulos ya jerarquizados a la estructura final
            $structuredMenu[] = [
                'category' => $categoryGroup['category'],
                'modules'  => array_values($root_modules) // Re-indexamos para tener un array limpio
            ];
        }

        // Opcional: Ordenar las categorías por nombre o ID si es necesario
        sort($structuredMenu);

        return $structuredMenu;
    }
}
