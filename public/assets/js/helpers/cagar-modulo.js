import { mostrarLoader } from "./alertas.js";

// Estado global para rastrear el módulo actual y sus elementos inyectados
let moduloActual = {
    id: null,
    elementos: [],  // Guardaremos aquí los <style> y <script> que inyectemos
    instancia: null // Para guardar la instancia del objeto del módulo y llamar a un método de limpieza
};

/**
 * Elimina los assets de un módulo previamente cargado y limpia sus listeners.
 */
const descargarModuloAnterior = () => {
    if (moduloActual.id) {

        // Eliminar los elementos <style> y <script> del DOM
        moduloActual.elementos.forEach(el => el.remove());

        // Si el módulo tiene una función de limpieza, la llamamos para evitar memory leaks
        if (moduloActual.instancia && typeof moduloActual.instancia.destroy === 'function') {
            moduloActual.instancia.destroy();
        }

        // Reiniciar el estado
        moduloActual = { id: null, elementos: [], instancia: null };
        document.querySelector('.page-content').innerHTML = '';
        window.instanciaModuloActual = null;
        window.moduleReady = null;
    }
};

/**
 * Carga el contenido de un CSS y lo inyecta en un tag <style>.
 */
const cargarCssContenido = async (ruta, idModulo) => {

    const contenido   = await fetch(`/api/assets?file=${ruta}`).then(res => res.text());
    const style       = document.createElement('style');
    style.textContent = contenido;

    style.setAttribute('data-module-id', idModulo);
    document.head.appendChild(style);

    moduloActual.elementos.push(style);
};

const cargarJsContenido = (ruta, idModulo) => {

    return new Promise(async (resolve, reject) => {
        try {
            const contenido = await fetch(`/api/assets?file=${ruta}`).then(res => res.text());
            const script = document.createElement('script');
            script.setAttribute('data-module-id', idModulo);

            // Configuramos los manejadores de eventos ANTES de añadir el script al DOM.
            script.onload = () => {
                // Este evento se dispara cuando el script ha sido cargado y ejecutado con éxito.
                // Ahora es seguro continuar.
                console.log(`Script ejecutado: ${ruta}`);
                resolve(); 
            };
            script.onerror = (err) => {
                console.error(`Error al ejecutar el script: ${ruta}`, err);
                reject(new Error(`Error en el script ${ruta}`));
            };
            
            const esModulo = contenido.includes('import ') || contenido.includes('export ');

            if (esModulo) {
                script.type = 'module';
                const blob = new Blob([contenido], { type: 'application/javascript' });
                const url = URL.createObjectURL(blob);
                script.src = url;
            } else {
                script.textContent = contenido;
            }

            document.body.appendChild(script);
            moduloActual.elementos.push(script);

            // Para scripts clásicos (no módulos) que no tienen 'src', el evento 'onload' no se dispara.
            // Se ejecutan síncronamente, por lo que podemos resolver la promesa inmediatamente después de añadirlos.
            if (!esModulo) {
                resolve();
            }

        } catch (error) {
            console.error(`Error al obtener el script: ${ruta}`, error);
            reject(error);
        }
    });
};

export const cargarModulo = async (ruta, contenedor) => {

    try {
        mostrarLoader();

        // Obtener los datos del nuevo módulo PRIMERO.
        const { data } = await apiFetch(ruta, { method: 'POST', responseType: 'json' });

        if (!data || !data.html || !data.assets) {
            throw new Error('Respuesta del servidor inválida.');
        }

        // Ahora es seguro destruir el módulo anterior.
        descargarModuloAnterior();

        // Construir el nuevo estado del módulo.
        const idModulo       = ruta.replace(/[^a-zA-Z0-9]/g, '-');
        moduloActual.id      = idModulo;
        contenedor.innerHTML = data.html;


        // Preparamos la promesa de "apretón de manos"
        let resolveModulePromise;
        const moduleLoadedPromise = new Promise(resolve => {
            resolveModulePromise = resolve;
        });

        // La exponemos globalmente para que el módulo pueda llamarla
        window.moduleReady = resolveModulePromise;

        // Cargar todos los assets para el nuevo módulo.
        const promesasCss = data.assets.css.map(url => cargarCssContenido(url, idModulo));
        await Promise.all(promesasCss);
        
        for (const url of data.assets.js) {
            await cargarJsContenido(url, idModulo);
        }

        // Esperamos la señal del módulo.
        await moduleLoadedPromise;

        //  Ahora es seguro asignar la instancia.
        if (window.instanciaModuloActual) {
            moduloActual.instancia = window.instanciaModuloActual;
            window.instanciaModuloActual = null;
        }

        // Lógica de UI extra
        if(window.innerWidth <= 991 && document.body.classList.contains('sidebar-open')) {
            document.body.classList.remove('sidebar-open');
        }

        lucide.createIcons();

    } catch (error) {     
        console.error("Error al cargar el módulo:", error);
        dispararMensaje(
            'ERROR', 
            'No se pudo cargar el módulo solicitado.', 
            'error', 
            { confirmButtonText: 'Aceptar' }
        );
    }
};