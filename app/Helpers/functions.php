<?php

namespace app\helpers;

use app\Router;
use DateTime;

/**
 * Previene ataques XSS escapando la salida HTML.
 */
function e(?string $string): string
{
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Genera una URL para una ruta con nombre.
 * Opcionalmente, reemplaza parámetros dinámicos.
 *
 * @param string $name El nombre de la ruta.
 * @param array  $params Un array asociativo de parámetros a reemplazar en la URL.
 * @return string La URL generada.
 * @throws \Exception Si la ruta no se encuentra.
 */
function route(string $name, array $params = []): string
{
    $namedRoutes = Router::getNamedRoutes();

    if (!isset($namedRoutes[$name])) {
        throw new \Exception("La ruta con nombre '{$name}' no está definida.");
    }

    $url = $namedRoutes[$name];

    // Reemplazar parámetros dinámicos (ej. /pokemon/{id})
    if (!empty($params)) {
        foreach ($params as $key => $value) {
            $url = str_replace('{' . $key . '}', (string)$value, $url);
        }
    }

    return $url;
}

/**
 * Obtener una cadena con caracteres aleatorios.
 * @param int $n*  Longitud que debería tener la cadena a generar (mínimo 8).
 * @return string
 */
function getRandomString(int $n): string
{
    if ($n <= 8) $n = 8;

    // random_bytes es criptográficamente seguro.
    // bin2hex lo convierte en una cadena legible.
    return bin2hex(random_bytes($n / 2));
}

/**
 * Formatear una fecha
 */
function formatearFecha(?string $dbDateString, string $defaultValue = ''): string
{
    // Si la fecha de entrada es nula, vacía o no es una cadena, devolvemos el valor por defecto.
    if (empty($dbDateString)) {
        return $defaultValue;
    }

    try {
        // PHP puede manejar los milisegundos directamente al crear el objeto DateTime.
        // Creamos un objeto DateTime a partir de la cadena de la base de datos.
        $dateObject = new DateTime($dbDateString);

        // Usamos el método format() con los especificadores de formato deseados.
        // d -> Día del mes, 2 dígitos con ceros iniciales (01 a 31)
        // m -> Representación numérica de un mes, con ceros iniciales (01 a 12)
        // Y -> Una representación numérica completa de un año, 4 dígitos (ej. 2025)
        // h -> Hora en formato de 12 horas con ceros iniciales (01 a 12)
        // i -> Minutos con ceros iniciales (00 a 59)
        // A -> "AM" o "PM" en mayúsculas
        $formattedDate = $dateObject->format('d/m/Y - h:i A');

        // Añadimos los puntos a "A.M." y "P.M."
        return str_replace(['AM', 'PM'], ['A.M.', 'P.M.'], $formattedDate);
    } catch (\Exception $e) {
        // Si la cadena de fecha no es válida, DateTime lanzará una excepción.
        // En ese caso, devolvemos el valor por defecto para evitar que la aplicación se rompa.
        return $defaultValue;
    }
}
