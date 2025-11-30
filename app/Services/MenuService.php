<?php

namespace app\services;

class MenuService
{

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
