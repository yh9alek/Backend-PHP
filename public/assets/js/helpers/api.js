// Variable para guardar el token JWT
let authToken = null;

// Función para establecer el token (se llamará después del login)
export const setAuthToken = (token, tiempoRestante) => {
    authToken = token;
    sessionStorage.setItem('authToken', token);
    sessionStorage.setItem('tiempoRestante', tiempoRestante);
};

// Función para obtener el token JWT
const getAuthToken = () => {
    if (!authToken) {
        authToken = sessionStorage.getItem('authToken');
    }
    return authToken;
};

// Parseo de respuesta según tipo
const parseByType = async (resp, type) => {
    if (type === 'json') return resp.json();
    if (type === 'html' || type === 'text') return resp.text();
    if (type === 'blob') return resp.blob();

    // auto: detecta por Content-Type
    const ct = (resp.headers.get('content-type') || '').toLowerCase();

    if (ct.includes('application/json'))
        return resp.json();

    return resp.text();
};

// Función genérica para hacer peticiones HTTP
export const apiFetch = async (endpoint, options = {}) => {

    const {
        responseType = 'auto',
        headers: hdrs = {},
        method = 'GET',
        body
    } = options;

    const headers = { ...hdrs };

    // Acepta según lo esperado
    if (!headers.Accept) {
        headers.Accept = (
            responseType === 'json' ? 'application/json' :
            responseType === 'html' || responseType === 'text' ? 'text/html, text/plain, */*;q=0.8' : '*/*'
        );
    }

    // Content-Type solo cuando corresponde (evita forzar en GET/FormData)
    const hasFormData = typeof FormData !== 'undefined' && body instanceof FormData;

    if (!hasFormData && body && !headers['Content-Type']) {

        // URLSearchParams, el tipo correcto:
        if (typeof URLSearchParams !== 'undefined' && body instanceof URLSearchParams) {
            headers['Content-Type'] = 'application/x-www-form-urlencoded;charset=UTF-8';
        }
    }

    const token = getAuthToken();
    if (token) 
        headers.Authorization = `Bearer ${token}`;

    const resp = await fetch(endpoint, { method, body, headers });

    // Manejo de errores HTTP con lectura segura
    if (!resp.ok) {
        let errorData;
        try {
            errorData = await resp.json();
        } catch (e) {
            // Si no es JSON, lee como texto plano como último recurso
            const rawText = await resp.text().catch(() => '');
            errorData = { msg: rawText.slice(0, 400) || `Error HTTP ${resp.status}` };
        }

        // El mensaje principal del error será el que viene del backend.
        const error = new Error(errorData.msg);
        error.data = errorData;
        error.status = resp.status;

        throw error; // Lanzamos el error para que sea capturado en el front por el .catch()
    }

    const data = await parseByType(resp, responseType);

    return {
        data,                // El cuerpo de la respuesta (JSON, texto, etc.)
        status: resp.status, // El código de estado HTTP (ej: 200, 201)
        ok: resp.ok,         // El booleano de éxito (siempre será true aquí)
        headers: resp.headers // Opcional: si necesitas acceso a las cabeceras
    };
};