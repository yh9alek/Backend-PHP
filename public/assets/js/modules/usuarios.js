(() => {

    class ModuloUsuarios {

        constructor() {
            // Crear referencias
            this.grid              = null;
            this.selectPerfil      = null;
            this.selectArea        = null;
            this.btnAgregar        = document.querySelector('.btn-agregar-usuarios');
            this.btnGuardarUsuario = document.querySelector('.btnRegistrar');
            this.modalAgregar      = document.querySelector('#modal-agregar-usuario');

            // Listeners
            if (this.btnGuardarUsuario) this.btnGuardarUsuario.addEventListener('click', this.guardarUsuario);
            if (this.modalAgregar) {
                this.modalAgregar.addEventListener('hidden.bs.modal', this.resetearModal);
            }

            // Dependencias
            this.cargarComponentes();
        }

        destroy() {
            // Destruir listeners
            if (this.btnGuardarUsuario) this.btnGuardarUsuario.removeEventListener('click', this.guardarUsuario);
            if (this.modalAgregar)      this.modalAgregar.removeEventListener('hidden.bs.modal', this.resetearModal);

            // Destruir referencias
            this.grid                 = null;
            this.selectPerfil         = null;
            this.selectArea           = null;
            this.btnAgregar           = null;
            this.btnGuardarUsuario    = null;
            this.modalAgregar         = null;
        }

        // Inicializar dependencias
        cargarComponentes() {

            this.grid = new Grid('.grid-usuarios', '/usuarios/obtenerUsuarios', {
                columns: [
                    { key: 'nombres', label: 'Nombre Completo' },
                    { key: 'profile', label: 'Perfil' },
                    { key: 'username', label: 'Usuario' },
                    {
                        key: 'actions',
                        label: '...',
                        render: (fila) => {

                            const {
                                create_user, created_at,
                                update_user, updated_at
                            } = fila;

                            const contenedorAcciones = document.createElement('div');
                            contenedorAcciones.style.display = 'flex';
                            contenedorAcciones.style.gap = '8px';

                            // Usamos el método estático de Grid para crear botones
                            const btnEditar = Grid.createAction({
                                title: 'Editar',
                                iconClass: 'bi bi-pencil-square',
                                attributes: {
                                    'data-bs-toggle': 'modal',
                                    'data-bs-target': '#modal-agregar-usuario',
                                },
                                onClick: () => this.editarUsuario(fila)
                            });

                            const btnInfo = Grid.createAction({
                                title: 'Información',
                                iconClass: 'bi bi-info-circle-fill',
                                color: '#2B5CC5',

                                onClick: () => mostrarDetallesRegistro({
                                    create_user,
                                    created_at,
                                    update_user,
                                    updated_at
                                })
                            });

                            const btnEliminar = Grid.createAction({
                                title: 'Dar de baja',
                                iconClass: 'bi bi-person-circle',
                                color: '#EB755D',

                                onClick: () => this.eliminarUsuario(fila)
                            });

                            contenedorAcciones.append(btnEditar, btnInfo, btnEliminar);
                            return contenedorAcciones;
                        }
                    }
                ],
                rowsPerPage: 8,
                serverSide: true
            });

            this.selectPerfil = new Select('.perfil', {
                name: 'perfil',
                url: 'perfil/obtenerPerfiles',
                dataPath: 'data',
                placeholder: '-- Seleccione --',
                labelKey: "name",
                valueKey: 'id',
                onChange: (item, instancia) => {
                    if(instancia) instancia.clearValidation();
                }
            });

            this.selectArea = new Select('.area', {
                name: 'area',
                url: 'area/obtenerAreas',
                searchable: false,
                dataPath: 'data',
                placeholder: '-- Seleccione --',
                labelKey: "name",
                valueKey: 'id',
                onChange: (item, instancia) => {
                    if(instancia) instancia.clearValidation();
                }
            });
        }

        editarUsuario(datos) {

            cargarDatosFormulario({
                datos,
                form: '.formulario-registro',
                titulo: 'Editar Usuario',
            });

        }

        eliminarUsuario(fila) {
            alert(`Función para eliminar el usuario con ID: ${id}`);
        }

        /* MANEJO DE EVENTOS */

        guardarUsuario = async (e) => {
            e.preventDefault();

            const formulario = document.querySelector('.formulario-registro');

            if (!validarFormulario('.formulario-registro'))
                return;

            try {

                const { data, status } = await apiFetch('/usuarios/registrar', {
                    method: 'POST',
                    body: new FormData(formulario)
                });

                await this.grid.recargarDatos();

                desactivarBotonesFormulario(formulario, false);

                dispararMensaje(
                    data.msg,
                    '',
                    'success', 
                    { confirmButtonText: 'Aceptar' }
                );

                if(status === 201) this.resetearModal();

            } catch(err) {
                habilitarFormulario('.formulario-registro', err);

                const tituloError = err.message;
                const mensaje     = err.status === 500 ? 'Favor de comunicar a soporte' : '';

                dispararMensaje(
                    tituloError,
                    mensaje,
                    'error',
                    { confirmButtonText: 'Aceptar' }
                );
            }
        }

        resetearModal = () => {
            limpiarModal(this.modalAgregar);
            this.modalAgregar.querySelector('.modal-title > span').innerHTML = 'Nuevo Usuario';
        }
    }

    window.instanciaModuloActual = new ModuloUsuarios();
    if (typeof window.moduleReady === 'function') {
        window.moduleReady();
    }

})();