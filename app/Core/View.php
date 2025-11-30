<?php

namespace app\Core;

/**
 * Clase para renderizar vistas
 */
class View
{
    protected string $viewPath;
    protected ?Request $request = null;

    public function __construct(string $basePath)
    {
        $this->viewPath = rtrim($basePath, '/');
    }

    /**
     * Establece el objeto Request para que esté disponible en las vistas.
     */
    public function setRequest(Request $request): self
    {
        $this->request = $request;
        return $this;
    }

    /**
     * Renderiza una vista, con o sin datos.
     * @param string $view   Nombre de la vista.
     * @param ?array $data   Datos a renderizar en la vista.
     * @param string $layout Nombre del template o layout a utilizar para este render.
     */
    public function render(string $view, array $data = [], string $layout = '_login'): string
    {
        $modulo = "{$this->viewPath}/modules/{$view}.php";
        $layout = "{$this->viewPath}/templates/{$layout}.php";

        //  // --- LÍNEAS DE DEPURACIÓN ---
        // echo "<!-- Intentando cargar vista: {$modulo} -->\n";
        // echo "<!-- ¿Existe la vista?: " . (file_exists($modulo) ? 'SI' : 'NO') . " -->\n";
        // echo "<!-- Intentando cargar layout: {$layout} -->\n";
        // echo "<!-- ¿Existe el layout?: " . (file_exists($layout) ? 'SI' : 'NO') . " -->\n";
        // // -----------------------------

        if (!file_exists($modulo)) {
            throw new \Exception("La vista '{$modulo}' no fue encontrada.");
        }
        if (!file_exists($layout)) {
            throw new \Exception("El layout '{$layout}' no fue encontrado.");
        }

        // Hacemos que la instancia actual de View esté disponible en las vistas
        // para que puedan acceder a sus propiedades y métodos (como $this->request).
        $data['viewInstance'] = $this;

        $data['modulo'] = $modulo;

        extract($data, EXTR_SKIP);

        ob_start();
        require_once $layout;
        return ob_get_clean();
    }

    /**
     * Renderiza solo el archivo de un módulo (parcial) sin un layout.
     * Ideal para respuestas HTTP.
     * 
     * @param string  $view Nombre de la vista/módulo.
     * @param array   $data Datos a pasar a la vista.
     * @return string El HTML renderizado del módulo.
     */
    public function renderPartial(string $view, array $data = []): string
    {
        $modulo = "{$this->viewPath}/modules/{$view}.php";

        if (!file_exists($modulo)) {
            throw new \Exception("La vista parcial '{$modulo}' no fue encontrada.");
        }

        // Hacemos que la instancia actual de View esté disponible en las vistas
        $data['viewInstance'] = $this;

        // Extraemos los datos para que estén disponibles como variables en la vista
        extract($data, EXTR_SKIP);

        // Capturamos la salida del archivo de la vista
        ob_start();
        require $modulo;
        return ob_get_clean();
    }
}
