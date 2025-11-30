<?php

namespace app\Controllers;

use app\Core\Request;
use app\Core\Response;

class AssetsController extends BaseController
{
    public function serve(Request $request): Response
    {
        $basePath = __DIR__ . '/../../public/assets';

        # 1. Si la carpeta de assets no existe o no es legible, es un error del servidor.
        if ($basePath === false) {
            error_log('La ruta no apunta a un directorio válido.');
            return new Response('Error de configuración del servidor.', 500);
        }

        # 2. Obtener el archivo solicitado del query string.
        $file = $request->param('file');
        if (!$file)
            return $this->view('error', [

                'title'     => 'Archivo no especificado',
                'message'   => '',
                'errorCode' => 400

            ], '_error');

        // Normalizar separadores de directorio para consistencia.
        // Esto convierte todas las \ en / para que las comparaciones sean fiables.
        $normalizedBasePath = str_replace('\\', '/', $basePath);
        $normalizedFile     = str_replace('\\', '/', $file);
        
        // Construir la ruta completa y usar realpath().
        $fullPath = $normalizedBasePath . '/' . $normalizedFile;
        $realPath = realpath($fullPath);

        // Normalizar la ruta base real para la comparación.
        $realBasePath = realpath($normalizedBasePath);

        // // --- DEPURACIÓN ---
        // echo '<pre>';
        // var_dump([
        //     'normalizedBasePath' => $normalizedBasePath,
        //     'normalizedFile' => $normalizedFile,
        //     'fullPath_constructed' => $fullPath,
        //     'realBasePath_resolved' => $realBasePath,
        //     'realPath_resolved' => $realPath,
        //     'comparison_result' => $realPath !== false && $realBasePath !== false && strpos($realPath, $realBasePath) === 0
        // ]);
        // die();
        // // ------------------------------------

        # 3. Validación de seguridad
        if ($realPath === false || $realBasePath === false || strpos($realPath, $realBasePath) !== 0) {
            return $this->view('error', [

                'title'     => 'No se encontró el recurso',
                'message'   => '',
                'errorCode' => 404

            ], '_error');
        }

        # 4. Determinar el Content-Type basado en la extensión del archivo
        $extension = pathinfo($realPath, PATHINFO_EXTENSION);
        $contentType = match (strtolower($extension)) {
            'css'   => 'text/css',
            'js'    => 'application/javascript',
            default => 'text/plain',
        };

        # 5. Leer el contenido y crear la respuesta
        $content = file_get_contents($realPath);

        $headers = ['Content-Type' => $contentType];

        return new Response($content, 200, $headers);
    }
}