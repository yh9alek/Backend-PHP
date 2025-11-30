export class Grid {

    padding = '10px';
    searchPlaceholder = 'Buscar...';
    alignCells = 'left';
    marginBottom = '100px';
    searching = true;
    pagination = true;

    // --- PROPIEDADES PARA PAGINACIÓN DEL SERVIDOR ---
    serverSide = false; // Por defecto, la paginación es del lado del cliente
    paginationMeta = {}; // Para almacenar los metadatos del backend (total, last_page, etc.)

    constructor(container, url, options = {}) {

        // 1. Identificar el contenedor original (sin cambios)
        const originalContainer = typeof container === "string" ? document.querySelector(container) : container;
        if (!originalContainer) {
            throw new Error(`El contenedor "${container}" no fue encontrado.`);
        }

        // 2. Crear y configurar el wrapper (sin cambios)
        this.grid_container = document.createElement('div');
        this.grid_container.classList.add('grid-container');
        originalContainer.parentElement.insertBefore(this.grid_container, originalContainer);
        this.grid_container.appendChild(originalContainer);

        // 3. Asignar el contenedor interno
        this.container = originalContainer;
        this.container.classList.add('grid');

        // 4. Configuración inicial usando el objeto de opciones
        this.url = url;
        this.columns = options.columns || [];
        this.rowsPerPage = options.rowsPerPage || 8;
        this.dataPath = options.dataPath || 'items'; // Apunta a 'items' por defecto en la respuesta paginada

        // --- NUEVO: Detectar si la paginación es del lado del servidor ---
        this.serverSide = options.serverSide || false;

        // Opciones de estilo y comportamiento (usando el objeto de opciones)
        this.padding = options.padding || '10px';
        this.searchPlaceholder = options.searchPlaceholder || 'Buscar...';
        this.alignCells = options.alignCells || 'left';
        this.marginBottom = options.marginBottom || '100px';
        this.searching = options.searching !== false; // true por defecto
        this.pagination = options.pagination !== false; // true por defecto

        // Propiedades de estado
        this.originalData = [];
        this.filteredData = []; // Solo se usa para paginación del cliente
        this.currentPage = 1;
        this.elements = {};
        this.searchDebounceTimer = null;

        this.init();
    }

    async init() {
        this.buildDOM(); // Construye la estructura una sola vez
        await this.fetchAndUpdateView(); // Obtiene los datos iniciales y actualiza la vista
    }

    /**
     * Construye los elementos estáticos del DOM (barra de búsqueda, cabecera de la tabla, botones).
     * Se ejecuta solo una vez.
     */
    buildDOM() {
        this.container.innerHTML = "";
        this.buildSearchBar();
        this.buildTable();
        this.buildPagination();
    }

    /**
     * Centraliza la obtención de datos y la actualización de la vista.
     */
    async fetchAndUpdateView() {
        // this.showLoadingPlaceholder();

        const data = await this._fetchData();
        if (data === null) return;

        if (this.serverSide) {
            this.originalData = data;
        } else {
            this.originalData = data;
            this.applyClientSideFilter(); // Re-aplica el filtro local
        }

        if (this.columns.length === 0 && data.length > 0) {
            this.columns = Object.keys(data[0]).map(key => ({ key, label: key }));
            // Si las columnas se autogeneran, necesitamos reconstruir la cabecera
            this.buildTable();
        }

        this.updateView(); // Actualiza el contenido dinámico
    }

    /**
     * Recarga los datos de la tabla desde el backend y actualiza la vista.
     * Mantiene el término de búsqueda actual.
     */
    async recargarDatos() {
        await this.fetchAndUpdateView();
    }

    async fetchAndRender() {
        // this.showLoadingPlaceholder();

        const data = await this._fetchData();

        if (data === null) return; // _fetchData ya maneja el error

        if (this.serverSide) {
            // En modo servidor, los datos recibidos son solo la página actual
            this.originalData = data;
        } else {
            // En modo cliente, los datos recibidos son el conjunto completo
            this.originalData = data;
            this.filteredData = [...data]; // Hacemos una copia para filtrar
        }

        // Autogenerar columnas si no se proporcionan
        if (this.columns.length === 0 && data.length > 0) {
            this.columns = Object.keys(data[0]).map(key => ({ key, label: key }));
        }

        this.render();
    }

    async _fetchData() {
        try {
            let finalUrl = new URL(this.url, window.location.origin);

            if (this.serverSide) {
                finalUrl.searchParams.append('page', this.currentPage);
                finalUrl.searchParams.append('limit', this.rowsPerPage);

                const searchQuery = this.elements.searchInput ? this.elements.searchInput.value : '';
                if (this.searching && searchQuery) {
                    finalUrl.searchParams.append('search', searchQuery);
                }
            }

            const {data: json} = await apiFetch(finalUrl, { method: 'POST', responseType: 'json' });

            if (this.serverSide) {
                // Si es del lado del servidor, la respuesta tiene meta y items
                this.paginationMeta = json.meta;
                return json.items; // Solo devolvemos los 'items'
            } else {
                const data = this.dataPath ? this.getNestedValue(json, this.dataPath) : json;
                if (!Array.isArray(data)) {
                    throw new Error('La fuente de datos no es un array');
                }
                return data;
            }

        } catch (err) {
            if (window.Swal) { window.Swal.close(); }

            const tituloError = err.message;
            const mensaje     = err.status === 500 ? 'Favor de comunicar a soporte' : '';

            dispararMensaje(
                tituloError,
                mensaje,
                'error',
                { confirmButtonText: 'Aceptar' }
            );
            

            this.renderEmptyTable("ERROR AL CARGAR LOS DATOS");
            return null;
        }
    }

    // [OPTIMIZACIÓN]
    showLoadingPlaceholder() {
        this.container.innerHTML = "";
        const table = this.createEl('table', 'table-bordered', this.tableStyles());
        const thead = table.createTHead();
        const headerRow = thead.insertRow();
        const colCount = this.columns.length || 4; // Estima 4 columnas si no están definidas

        // Crea las cabeceras
        for (let i = 0; i < colCount; i++) {
            const th = document.createElement('th');
            th.style.padding = this.padding;
            th.style.textAlign = this.alignCells;
            th.innerHTML = `<span class="placeholder-glow"><span class="placeholder col-6"></span></span>`;
            headerRow.appendChild(th);
        }

        const tbody = document.createElement('tbody');
        const placeholderRow = `
            <tr class="placeholder-glow">
                ${Array(colCount).fill(`
                    <td style="padding: ${this.padding}; text-align: ${this.alignCells};">
                        <span class="placeholder col-12"></span>
                    </td>
                `).join('')}
            </tr>
        `;
        // Genera las filas de placeholders como una cadena HTML para mayor eficiencia
        tbody.innerHTML = Array(this.rowsPerPage).fill(placeholderRow).join('');

        table.appendChild(tbody);
        this.container.appendChild(table);
    }

    render() {
        this.container.innerHTML = "";

        this.buildSearchBar();
        this.buildTable();
        this.buildPagination();
    }

    buildSearchBar() {
        if (!this.searching) return;

        const wrapper = this.createEl('div', 'search', {
            marginBottom: '12px', display: 'flex', justifyContent: 'end',
            position: 'absolute', right: 0,
        });

        const input = this.createEl('input', null, {
            padding: '6px 32px 6px 12px', width: '200px', border: '1px solid #ccc',
            borderRadius: '4px', outline: 'none'
        });

        input.type = 'text';
        input.placeholder = this.searchPlaceholder;

        const clearBtn = this.createEl('button', 'btn-clear', {
            width: '12px', height: '12px', position: 'absolute', zIndex: '2',
            top: '12px', right: '11px', border: 'none', outline: 'none', display: 'none'
        });

        clearBtn.addEventListener('click', () => {
            input.value = '';
            input.dispatchEvent(new Event('input')); // Dispara el evento para actualizar la tabla
        });

        input.addEventListener('input', () => {
            clearTimeout(this.searchDebounceTimer);
            this.searchDebounceTimer = setTimeout(() => {
                this.currentPage = 1;
                if (this.serverSide) {
                    this.fetchAndUpdateView(); // Recarga desde el servidor
                } else {
                    this.applyClientSideFilter(); // Filtra localmente
                    this.updateView(); // Y actualiza la vista
                }
            }, 300);
        });

        wrapper.append(clearBtn, input);
        this.container.appendChild(wrapper);
        this.elements.searchInput = input;
        this.elements.btnClear = clearBtn;
    }

    /**
     * Función para filtrar datos en el modo cliente.
     */
    applyClientSideFilter() {
        const query = this.elements.searchInput ? this.elements.searchInput.value.toLowerCase().trim() : '';
        if (!query) {
            this.filteredData = [...this.originalData];
            return;
        }
        this.filteredData = this.originalData.filter(item =>
            this.columns.some(col => {
                if (col.render) return false;
                const val = this.getNestedValue(item, col.key);
                return String(val).toLowerCase().includes(query);
            })
        );
    }

    buildTable() {
        const table = this.createEl('table', 'table-bordered', this.tableStyles());
        const thead = table.createTHead();
        const headerRow = thead.insertRow();

        this.columns.forEach(col => {
            const th = document.createElement('th');
            th.textContent = col.label || col.key;
            th.style.padding = this.padding;
            th.style.textAlign = this.alignCells;
            headerRow.appendChild(th);
        });

        this.elements.tbody = document.createElement('tbody');
        table.appendChild(this.elements.tbody);

        this.container.appendChild(table);
        this.updateTableBody();
    }

    buildPagination() {
        if (!this.pagination) return;

        const nav = this.createEl('div', 'paginacion', {
            display: 'flex', gap: '16px', justifyContent: 'end', alignItems: 'center',
            marginTop: '10px', position: 'absolute', right: '0', bottom: '-34px'
        });

        const prev = this.createEl('button', 'btn-p', {});
        const next = this.createEl('button', 'btn-p', {});
        const info = this.createEl('span');
        const regInfo = this.createEl('p', 'registros', { position: 'absolute', bottom: '-34px' });

        prev.addEventListener('click', () => {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.serverSide ? this.fetchAndUpdateView() : this.updateView();
            }
        });

        next.addEventListener('click', () => {
            const totalPages = this.serverSide ? this.paginationMeta.last_page : Math.ceil(this.filteredData.length / this.rowsPerPage);
            if (this.currentPage < totalPages) {
                this.currentPage++;
                this.serverSide ? this.fetchAndUpdateView() : this.updateView();
            }
        });

        nav.append(prev, info, next);
        this.container.append(nav, regInfo);

        this.elements.btnPrev = prev;
        this.elements.btnNext = next;
        this.elements.pageInfo = info;
        this.elements.showInfo = regInfo;

        this.updatePagination();
    }

    // [OPTIMIZACIÓN] usar DocumentFragment
    updateTableBody() {

        const { tbody } = this.elements;
        let pageData;

        if (this.serverSide) {
            // En modo servidor, los datos ya vienen paginados
            pageData = this.originalData;
        } else {
            // En modo cliente, cortamos el array filtrado
            const start = (this.currentPage - 1) * this.rowsPerPage;
            pageData = this.filteredData.slice(start, start + this.rowsPerPage);
        }

        // Usamos un DocumentFragment para construir las filas en memoria
        const fragment = document.createDocumentFragment();

        if (pageData.length === 0) {
            const tr = fragment.appendChild(document.createElement('tr'));
            const td = tr.insertCell();
            td.colSpan = this.columns.length || 1;
            td.textContent = "SIN REGISTROS";
            Object.assign(td.style, {
                textAlign: 'center', padding: this.padding, color: '#6A6A6A',
                position: 'absolute', left: '0', right: '0'
            });
            // Ajustar la posición de la paginación si no hay datos
            if (this.elements.pageInfo) {
                this.elements.pageInfo.parentElement.style.bottom = '-80px';
                this.elements.showInfo.style.bottom = '-80px';
                this.container.style.marginBottom = `calc(${this.marginBottom} + 46px)`;
            }
        } else {
            if (this.elements.pageInfo) {
                this.elements.pageInfo.parentElement.style.bottom = '-34px';
                this.elements.showInfo.style.bottom = '-34px';
                this.container.style.marginBottom = this.marginBottom;
            }

            pageData.forEach(row => {
                const tr = document.createElement('tr');
                this.columns.forEach(col => {
                    const td = tr.insertCell();
                    td.style.padding = this.padding;
                    td.style.textAlign = this.alignCells;
                    if (col.key === 'actions') {
                        td.style.whiteSpace = 'nowrap';
                        td.style.width = '1%';
                    }

                    if (typeof col.render === 'function') {
                        const content = col.render(row);
                        td.appendChild(content instanceof Node ? content : document.createTextNode(content));
                    } else {
                        td.textContent = this.getNestedValue(row, col.key) ?? '';
                    }
                });
                fragment.appendChild(tr);
            });
        }

        // [OPTIMIZACIÓN] Limpiamos el tbody y añadimos todo el fragmento en una sola operación.
        tbody.innerHTML = ''; // O `tbody.replaceChildren()`
        tbody.appendChild(fragment);
    }

    updatePagination() {
        if (!this.pagination || !this.elements.pageInfo) return;

        let totalPages, totalRecords, from, to;

        if (this.serverSide) {
            totalPages = this.paginationMeta.last_page || 1;
            totalRecords = this.paginationMeta.total || 0;
            from = this.paginationMeta.from || 0;
            to = this.paginationMeta.to || 0;
        } else {
            totalPages = Math.ceil(this.filteredData.length / this.rowsPerPage) || 1;
            totalRecords = this.filteredData.length;
        }

        this.elements.pageInfo.innerHTML = `Pag. <b>${this.currentPage}</b> de <b>${totalPages}</b>`;

        if (this.serverSide) {
            this.elements.showInfo.innerHTML = `Mostrando <b>${from}</b> a <b>${to}</b> de <b>${totalRecords}</b> registros`;
        } else {
            this.elements.showInfo.innerHTML = `<b>${totalRecords}</b> Registros`;
        }

        this.elements.btnPrev.disabled = this.currentPage === 1;
        this.elements.btnNext.disabled = this.currentPage >= totalPages;

        if (window.Swal) {
            window.Swal.close();
        }
    }

    updateView() {
        // Actualiza el botón de limpiar búsqueda
        if (this.searching && this.elements.btnClear) {
            const query = this.elements.searchInput.value;
            this.elements.btnClear.style.display = query ? 'block' : 'none';
        }
        
        this.updateTableBody();
        this.updatePagination();
    }

    renderEmptyTable(message) {
        this.container.innerHTML = "";
        const table = this.createEl("table", null, this.tableStyles());
        const thead = table.createTHead();
        const row = thead.insertRow();
        const th = document.createElement("th");
        th.textContent = " ";
        th.style.padding = this.padding;
        row.appendChild(th);

        const tbody = document.createElement("tbody");
        const tr = tbody.insertRow();
        const td = tr.insertCell();
        td.colSpan = 1;
        td.textContent = message;
        td.style.padding = this.padding;
        td.style.textAlign = "center";
        td.style.fontWeight = '500';
        td.style.color = "#EB755D";

        table.appendChild(tbody);
        this.container.appendChild(table);
    }

    createEl(tag, className = '', styles = {}, text = '') {
        const el = document.createElement(tag);
        if (className) el.className = className;
        Object.assign(el.style, styles);
        if (text) el.textContent = text;
        return el;
    }

    tableStyles() {
        return {
            width: "100%",
            borderCollapse: "collapse",
            tableLayout: "auto",
            marginTop: this.searching ? '47px' : 0,
        };
    }

    getNestedValue(obj, keyPath) {
        return keyPath.split('.').reduce((acc, key) => acc?.[key], obj);
    }

    /**
     * Crea un botón de acción para la columna de acciones del Grid.
     * 
     * @param {object} options - Opciones para configurar el botón.
     * @param {string} options.title - Título que se mostrará en el tooltip del botón.
     * @param {string} options.iconClass - Clases del ícono a utilizar (ej. 'fas fa-pencil').
     * @param {object} [options.attributes={}] - Un objeto de atributos para añadir al botón (ej. {'data-bs-toggle': 'modal'}).
     * @param {string} [options.color=null] - Color a aplicar al ícono.
     * @param {Function} [options.onClick=null] - Callback con la acción a realizar al hacer clic.
     * @returns {HTMLButtonElement} El elemento del botón creado.
     */
    static createAction({title, iconClass, attributes = {}, color = null, onClick = null}) {
        const btn = document.createElement('button');
        
        // Configuración básica
        btn.innerHTML = `<i class="${iconClass}" ${color ? `style="color:${color}"` : ''}></i>`;
        btn.title = title;
        btn.classList.add('grid-button');

        // Asignar el evento onClick si se proporciona
        if (onClick && typeof onClick === 'function') {
            btn.onclick = onClick;
        }

        // Inyectar atributos personalizados
        // Esto permite añadir `data-bs-toggle`, `data-bs-target`, o cualquier otro atributo.
        if (attributes) {
            for (const [key, value] of Object.entries(attributes)) {
                btn.setAttribute(key, value);
            }
        }

        return btn;
    }

    dispararMensaje(title, text, icon, options = {}) {

        // Definimos el mixin una sola vez para mantener la consistencia.
        const SwalWithBootstrapButtons = Swal.mixin({
            customClass: {
                confirmButton: 'btn btn-primary',
                cancelButton: 'btn btn-secondary me-2'
            },
            showClass: {
                popup: 'swal-popup-zoom-in'
            },
            hideClass: {
                popup: 'swal-popup-zoom-out'
            },
            buttonsStyling: false
        });

        // Construimos la configuración de la alerta
        const swalConfig = {

            title: title,
            text: text,
            icon: icon,

            confirmButtonText: options.confirmButtonText || 'Aceptar',
            showCancelButton: options.showCancelButton || false,
            cancelButtonText: options.cancelButtonText || 'Cancelar',

            allowOutsideClick: options.allowOutsideClick ?? true,
            allowEscapeKey: options.allowEscapeKey ?? true,
        };

        SwalWithBootstrapButtons.fire(swalConfig).then((result) => {

            // Si se confirma y hay una función de callback 'onConfirm'
            if (result.isConfirmed && options.onConfirm) {
                options.onConfirm();
            }
            // Si se cancela (usando el botón de cancelar) y hay un callback 'onCancel'
            else if (result.dismiss === Swal.DismissReason.cancel && options.onCancel) {
                options.onCancel();
            }
        })
    }

    mostrarLoader() {
        Swal.fire({
            title: 'Cargando',
            allowOutsideClick: false, // No permitir cerrar haciendo clic fuera
            allowEscapeKey: false,    // No permitir cerrar con la tecla Esc
            showConfirmButton: false, // Ocultar el botón de confirmar
            showClass: {
                popup: 'swal-popup-in'
            },
            hideClass: {
                popup: 'swal-popup-out'
            },
            didOpen: () => {
                Swal.showLoading(); // Muestra el ícono de carga (spinner)
            }
        });
    }
}
