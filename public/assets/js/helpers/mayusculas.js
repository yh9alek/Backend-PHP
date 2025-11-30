/**
 * Convierte automáticamente a mayúsculas el texto de los elementos seleccionados.
 * @param {string} selector - Un selector CSS para los inputs y textareas deseados.
 *                             Ej: 'input', '.clase-mayus', '#mi-input'
 */
function activarMayusculasAutomaticas(selector) {
  // Usamos document.querySelectorAll para obtener todos los elementos que coincidan
  const elementos = document.querySelectorAll(selector);

  elementos.forEach(elemento => {
    // Añadimos un "escuchador" para el evento 'input'
    // El evento 'input' se dispara cada vez que el valor del elemento cambia.
    elemento.addEventListener('input', (event) => {
      // event.target se refiere al elemento que disparó el evento (nuestro input)
      const input = event.target;
      // Guardamos la posición del cursor para que no salte al final
      const start = input.selectionStart;
      const end = input.selectionEnd;

      // Convertimos el valor actual a mayúsculas
      input.value = input.value.toUpperCase();

      // Restauramos la posición del cursor
      input.setSelectionRange(start, end);
    });
  });
}