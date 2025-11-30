export class Select {
    static instances = [];

    constructor(container, config = {}) {
        this.container = typeof container === "string" ? document.querySelector(container) : container;
        this.container.classList.add('select');
        this.config = config;

        this.items = [];
        this.selected = null;
        this.highlightedIndex = -1;
        this._isSyncing = false;

        this.onChange = config.onChange || null;
        this.placeholder = config.placeholder || "-- Selecciona --";
        this.addPlaceholderOption = config.addPlaceholderOption !== false;
        this.searchable = config.searchable !== false;
        this.batchSize = config.batchSize || 8;
        this.loadedCount = 0;

        this.parentForm = this.container.closest('form');

        if (this.parentForm) {
            this.boundReset = this.reset.bind(this);
            this.parentForm.addEventListener('reset', this.boundReset);
        }

        this.render();

        if (this.container.hasAttribute('required')) {
            this.nativeSelect.required = true;
            
            this.container.removeAttribute('required');
        }

        this.bindEvents();
        this.proxyNativeSelectValue();

        if (config.url) {
            this.fetchItems(config.url);
        } else if (Array.isArray(config.items)) {
            this.setItems(config.items);
            this.renderOptions();
        } else {
            this.setLoading("Sin datos.");
        }

        Select.instances.push(this);
    }

    /**
     * Intercepta la propiedad 'value' del select nativo para permitir la sincronización bidireccional.
     * Cuando alguien escribe `miSelect.nativeSelect.value = 'X'`, esta función lo detecta
     * y llama a `setValue()` para actualizar la UI personalizada.
     */
    proxyNativeSelectValue() {
        // Guardamos el descriptor original de la propiedad 'value' del prototipo de HTMLSelectElement
        const valueDescriptor = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');

        Object.defineProperty(this.nativeSelect, 'value', {
            configurable: true, // Permite redefinir la propiedad más tarde si es necesario
            enumerable: true,
            get: () => {
                // El 'getter' simplemente llama al getter original
                return valueDescriptor.get.call(this.nativeSelect);
            },
            set: (newValue) => {
                // El 'setter' llama primero al setter original para cambiar el valor real
                valueDescriptor.set.call(this.nativeSelect, newValue);

                // Si ya estamos en medio de una sincronización, salimos para evitar un bucle infinito
                if (this._isSyncing) return;

                // Si no, iniciamos la sincronización de la UI
                this.setValue(newValue);
            }
        });
    }

    reset() {
        
        this.clearValidation();
        // 1. Reinicia el estado interno de la selección
        this.selected = null;

        // 2. Actualiza la UI para mostrar el placeholder
        this.selectBtn.querySelector("span").textContent = this.placeholder;
        this.container.classList.remove('has-selection');

        // 3. Limpia el valor del <select> nativo subyacente
        if (!this._isSyncing) {
            this._isSyncing = true;
            this.nativeSelect.value = "";
            this._isSyncing = false;
        } else {
             this.nativeSelect.value = "";
        }

        // 4. Vuelve a renderizar las opciones para quitar la clase 'selected' de cualquier ítem
        //    (esto es útil si el desplegable está abierto cuando se llama a reset)
        this.renderOptions();

        // 5. Opcional: Dispara el evento onChange con `null` para notificar que la selección se ha limpiado
        if (typeof this.onChange === "function") {
            this.onChange(null);
        }
    }

    render() {
        this.container.innerHTML = `
            <select class="native-select" hidden></select>

            <div class="select-btn" tabindex="0">
                <span style="max-height: 35px; overflow: hidden; text-overflow: ellipsis;">${this.placeholder}</span>
                <i class="bi bi-chevron-down"></i>
            </div>
            <div class="select-content">
                ${this.searchable ? `
                <div class="search">
                    <i class="bi bi-search"></i>
                    <input class="select-input" type="text" placeholder="Buscar..." autocomplete="off">
                </div>` : ''}
                <div class="options no-scroll"></div>
            </div>
        `;

        this.selectBtn = this.container.querySelector(".select-btn");
        this.searchInp = this.container.querySelector(".select-input");
        this.optionsBox = this.container.querySelector(".options");
        this.nativeSelect = this.container.querySelector(".native-select");

        if (this.config.name) {
            this.nativeSelect.name = this.config.name;
            this.nativeSelect.id = this.config.name;
        }
    }

    normalizeItems(items) {
        const labelKey = this.config.labelKey || "label";
        const valueKey = this.config.valueKey || "value";

        return items.map(item => {
            if (typeof item === "string") {
                return { label: item, value: item };
            } else if (typeof item === "object" && item !== null) {
                return {
                    label: item[labelKey] ?? JSON.stringify(item),
                    value: item[valueKey] ?? item[labelKey]
                };
            }
            return { label: String(item), value: String(item) };
        });
    }

    async fetchItems(url) {
        this.setLoading(
            `<div class="d-flex justify-content-center">
                <div class="spinner-border" style="color: #CDD0D5;" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>`
        );
        try {
            const res = await fetch(url, {
                method: 'POST'
            });
            if (!res.ok) {
                this.selectBtn.style.backgroundColor = '#fd8b8915';
                this.selectBtn.style.border = '1px solid #FD8B89';
                throw new Error("Error al cargar los datos.");
            }
            const data = await res.json();

            let list;
            if (Array.isArray(data)) list = data;
            else if (this.config.dataPath) list = this.extractByPath(data, this.config.dataPath);
            else if (Array.isArray(data.data)) list = data.data;
            else if (Array.isArray(data.items)) list = data.items;

            if (!Array.isArray(list)) throw new Error("No se encontró un array válido.");

            // this.items = this.normalizeItems(list);
            // this.showingMessage = false;
            // this.renderOptions();
            this.setItems(list);
        } catch (err) {
            this.setLoading(err.message);
            console.error(err);
            this.container.querySelector('.select-btn > span').innerText = '...';
            this.container.querySelector('.search').style.display = 'none';
        }
    }

    extractByPath(obj, path) {
        return path.split(".").reduce((acc, part) => acc && acc[part], obj);
    }

    setLoading(message) {
        this.optionsBox.innerHTML = `<p style="margin-top: 10px; font-size: 14px; text-align: center;">${message}</p>`;
        this.optionsBox.classList.add('no-scroll');
        this.showingMessage = true;
    }


    renderOptions(filtered = null) {
        if (this.showingMessage) return;

        const data = filtered || this.items;
        this.optionsBox.innerHTML = "";
        this.nativeSelect.innerHTML = "";
        this.loadedCount = 0;

        if (!this.searchable) {
            this.optionsBox.style.marginTop = '0';
        }

        if (data.length === 0) {
            this.setLoading("Sin resultados");
            return;
        }

        this.appendOptions(data);
        this.checkOverflow();
    }


    appendOptions(data) {
        const nextBatch = data.slice(this.loadedCount, this.loadedCount + this.batchSize);

        nextBatch.forEach(item => {
            const index = this.items.findIndex(i => i.value === item.value);

            const div = document.createElement("div");
            div.className = "custom-option";
            div.textContent = item.label;
            div.dataset.value = item.value;
            div.dataset.index = index;
            if (this.selected?.value === item.value) div.classList.add("selected");
            div.addEventListener("click", () => this.select(index));
            this.optionsBox.appendChild(div);

            const opt = document.createElement("option");
            opt.value = item.value;
            opt.textContent = item.label;
            this.nativeSelect.appendChild(opt);
        });

        this.loadedCount += this.batchSize;
    }

    bindEvents() {
        this.selectBtn.addEventListener("click", () => {
            if (this.searchInp) this.searchInp.value = "";
            this.container.classList.toggle("active");

            if (this.container.classList.contains("active")) {
                this.optionsBox.scrollTop = 0; // <--- REINICIA SCROLL
                this.showingMessage = false;
            }

            this.renderOptions();
            this.highlightedIndex = -1;
        });

        if (this.searchable && this.searchInp) {
            this.searchInp.addEventListener("input", () => {
                const term = this.searchInp.value.trim().toLowerCase();

                this.showingMessage = false; // <-- permite renderizar de nuevo

                if (term === "") {
                    this.renderOptions(); // Mostrar todos los elementos
                } else {
                    const filtered = this.items.filter(item =>
                        item.label.toLowerCase().startsWith(term)
                    );

                    if (filtered.length === 0) {
                        this.setLoading("Sin resultados");
                    } else {
                        this.renderOptions(filtered);
                    }
                }
            });

            this.searchInp.addEventListener("keydown", e => this.handleKeydown(e));
        }

        this.optionsBox.addEventListener("scroll", () => {
            const data = this.searchInp?.value
                ? this.items.filter(item =>
                    item.label.toLowerCase().startsWith(this.searchInp.value.trim().toLowerCase()))
                : this.items;

            if (this.optionsBox.scrollTop + this.optionsBox.clientHeight >= this.optionsBox.scrollHeight - 20) {
                if (this.loadedCount < data.length) {
                    this.appendOptions(data);
                }
            }
        });

        document.addEventListener("click", e => {
            if (!this.container.contains(e.target)) {
                this.container.classList.remove("active");
            }
        });

        this.selectBtn.addEventListener("keydown", e => this.handleKeydown(e));
    }

    handleKeydown(e) {
        const isOpen = this.container.classList.contains("active");
        const options = Array.from(this.optionsBox.querySelectorAll(".custom-option"));

        if (!isOpen && ["ArrowDown", "ArrowUp", "Enter"].includes(e.key)) {
            this.container.classList.add("active");
            return;
        }

        if (e.key === "ArrowDown") {
            e.preventDefault();
            this.highlightedIndex = Math.min(this.highlightedIndex + 1, options.length - 1);
            this.highlightOption(options);
        } else if (e.key === "ArrowUp") {
            e.preventDefault();
            this.highlightedIndex = Math.max(this.highlightedIndex - 1, 0);
            this.highlightOption(options);
        } else if (e.key === "Enter") {
            e.preventDefault();
            if (this.highlightedIndex >= 0) {
                const value = options[this.highlightedIndex].dataset.value;
                const index = this.items.findIndex(item => item.value === value);
                this.select(index);
            }
        } else if (e.key === "Escape") {
            this.container.classList.remove("active");
        }
    }

    checkOverflow() {
        const hasVerticalScroll = this.optionsBox.scrollHeight > this.optionsBox.clientHeight;
        this.optionsBox.classList.toggle("no-scroll", !hasVerticalScroll);
    }

    highlightOption(options) {
        options.forEach((el, i) => {
            el.classList.toggle("highlight", i === this.highlightedIndex);
        });
    }

    select(index) {
        const selectedItem = this.items[index];

        // CASO 1: Se seleccionó el placeholder
        if (this.addPlaceholderOption && selectedItem.value === '') {
            this.selected = null;
            this.selectBtn.querySelector("span").textContent = this.placeholder;
            this.container.classList.remove('has-selection');

            if (typeof this.onChange === "function") {
                this.onChange(null);
            }
        }
        // CASO 2: Se seleccionó un ítem real
        else {
            this.selected = selectedItem;
            this.selectBtn.querySelector("span").textContent = this.selected.label;
            this.container.classList.add('has-selection');

            if (typeof this.onChange === "function") {
                this.onChange(this.selected, this);
            }
        }

        // --- ACCIONES COMUNES ---
        if (this.searchInp) this.searchInp.value = "";
        this.container.classList.remove("active");
        this.renderOptions(); // Reconstruye los <option>

        this.nativeSelect.value = this.selected ? this.selected.value : '';
    }

    getValue() {
        return this.nativeSelect.value || null;
    }

    setValue(value) {
        if (this._isSyncing) return;

        this._isSyncing = true; // Activa la bandera

        const index = this.items.findIndex(item => item.value == value);
        if (index !== -1) {
            this.select(index);
        } else {
            // Si el valor no existe, resetea el select a su estado inicial
            this.reset();
        }

        this._isSyncing = false;
    }

    setItems(newItems) {
        this.items = this.normalizeItems(newItems);

        // Si la opción está activada, añade el placeholder como primer elemento
        if (this.addPlaceholderOption) {
            this.items.unshift({
                label: this.placeholder,
                value: ''
            });
        }

        this.showingMessage = false;
        this.renderOptions();
    }

    /**
     * Marca el select como inválido, añadiendo la clase 'is-invalid'.
     */
    markAsInvalid() {
        this.container.classList.remove('is-valid');
        this.container.classList.add('is-invalid');
    }

    /**
     * Marca el select como válido, añadiendo la clase 'is-valid'.
     */
    markAsValid() {
        this.container.classList.remove('is-invalid');
        this.container.classList.add('is-valid');
    }

    /**
     * Limpia cualquier clase de validación.
     */
    clearValidation() {
        this.container.classList.remove('is-invalid', 'is-valid');
    }

    destroy() {
        if (this.parentForm) {
            this.parentForm.removeEventListener('reset', this.boundReset);
        }
        this.container.innerHTML = "";
        Select.instances = Select.instances.filter(inst => inst !== this);
    }
}
