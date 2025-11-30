export class FileUploader {
    constructor(selector, options = {}) {
        this.container = document.querySelector(selector);
        this.options = Object.assign({
            maxFiles: Infinity,
            maxSize: Infinity, // en KB
            only: null // array de extensiones normalizadas
        }, options);

        this.files = [];
        this.createUI();
        this.setupListeners();
    }

    createUI() {
        this.container.classList.add('file-uploader');
        this.container.innerHTML = `
            <div class="uploader-header">
                <p class="status-text" style="margin: 0;">Arrastre y suelte sus archivos</p>
                <button type="button" class="clear-btn" style="display:none; margin-left:auto; font-size:12px; background:none; border:none; color:#FD5E5C; cursor:pointer;">
                    <i class="bi bi-trash-fill"></i> Limpiar
                </button>
            </div>
            <p class="error-message" style="display:none; color:#FD5E5C; font-size:12px; margin:4px 0 0;"></p>
            <input type="file" multiple hidden />
            <div class="file-list-container">
                <div class="file-list">
                    <img class="upload-image" src="/assets/src/imgs/upload.png">
                </div>
            </div>
        `;

        this.input = this.container.querySelector('input[type="file"]');
        this.fileList = this.container.querySelector('.file-list');
        this.statusText = this.container.querySelector('.status-text');
        this.clearButton = this.container.querySelector('.clear-btn');
        this.uploadImage = this.container.querySelector('.upload-image');
        this.errorMessage = this.container.querySelector('.error-message');
        this.uploaderHeader = this.container.querySelector('.uploader-header');
        this.fileListContainer = this.container.querySelector('.file-list-container');

        this.clearButton.addEventListener('click', (e) => {
            e.stopPropagation();
            this.clearFiles();
        });
    }

    setupListeners() {
        this.input.addEventListener('change', (e) => {
            this.addFiles(Array.from(e.target.files));
        });

        this.container.addEventListener('click', () => {
            this.input.click();
        });

        this.container.addEventListener('dragover', (e) => {
            e.preventDefault();
            this.container.classList.add('drag-over');
        });

        this.container.addEventListener('dragleave', () => {
            this.container.classList.remove('drag-over');
        });

        this.container.addEventListener('drop', (e) => {
            e.preventDefault();
            this.container.classList.remove('drag-over');
            this.addFiles(Array.from(e.dataTransfer.files));
        });
    }

    showError(message) {
        this.errorMessage.textContent = message;
        this.errorMessage.style.display = 'block';
        this.errorMessage.style.marginTop = '10px';

        this.container.style.outline = '1px solid #ff8583';

        clearTimeout(this._errorTimeout);
        this._errorTimeout = setTimeout(() => {
            this.container.style.outline = 'none';
            this.errorMessage.style.display = 'none';
        }, 1500);
    }

    addFiles(newFiles) {
        for (const file of newFiles) {
            const ext = file.name.split('.').pop().toLowerCase();
            const sizeKB = file.size / 1024;

            const exists = this.files.some(f => f.name === file.name && f.size === file.size);

            if (this.files.length >= this.options.maxFiles) {
                this.showError('Máximo número de archivos alcanzado');
                break;
            }

            if (this.options.only && !this.isAllowed(ext)) {
                this.showError(`Archivo no permitido`);
                continue;
            }

            if (sizeKB > this.options.maxSize) {
                this.showError(`Archivo demasiado grande`);
                continue;
            }

            if (exists) {
                this.showError(`Archivo duplicado`);
                continue;
            }

            this.files.push(file);
            this.renderFile(file);
        }

        this.input.value = '';
        this.updateStatus();
    }


    clearFiles() {
        this.files = [];
        this.fileList.querySelectorAll('.file-item').forEach(item => item.remove());
        this.updateStatus();
    }

    updateStatus() {

        if (this.files.length >= 3)
            this.fileListContainer.style.paddingRight = '6px';
        else
            this.fileListContainer.style.paddingRight = '0';

        if (this.files.length > 0) {
            this.statusText.innerHTML = `Archivos <span class="archivos">${this.files.length}</span>`;
            this.statusText.style.width = 'max-content';
            this.statusText.style.textAlign = 'inset';
            this.statusText.style.fontWeight = '500';
            this.clearButton.style.display = 'inline'
            this.uploaderHeader.style.paddingBottom = '12px';
            if (this.uploadImage) this.uploadImage.style.display = 'none';
        } else {
            this.container.style.flexDirection = 'column-reverse';
            this.fileList.style.marginTop = '0';
            this.statusText.textContent = 'Arrastre y suelte sus archivos';
            this.statusText.style.fontWeight = 'normal';
            this.statusText.style.width = '100%';
            this.statusText.style.textAlign = 'center';
            this.clearButton.style.display = 'none';
            this.uploaderHeader.style.paddingBottom = '0';
            if (this.uploadImage) this.uploadImage.style.display = 'block';
        }
    }


    isAllowed(ext) {
        const rules = {
            pdf: ['pdf'],
            excel: ['xls', 'xlsx', 'csv'],
            word: ['doc', 'docx'],
            power: ['ppt', 'pptx'],
            image: ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
            code: ['js', 'ts', 'html', 'css', 'php', 'py', 'java', 'cpp', 'c', 'json', 'xml', 'cs', 'rb', 'go'],
            db: ['sql', 'db', 'sqlite', 'accdb'],
            txt: ['txt'],
            diagram: ['drawio', 'vsdx', 'dia', 'cdx']
        };

        let allowed = [];

        for (const rule of this.options.only) {
            if (rules[rule]) {
                allowed.push(...rules[rule]);
            } else {
                allowed.push(rule.toLowerCase());
            }
        }

        return allowed.includes(ext);
    }

    renderFile(file) {
        this.container.style.flexDirection = 'column';

        const item = document.createElement('div');
        item.className = 'file-item';
        item.style.position = 'relative';

        const deleteBtn = document.createElement('i');
        deleteBtn.classList.add('delete-item', 'bi', 'bi-x-circle-fill');
        deleteBtn.style.cssText = `
            font-size: 18px;
            width: 18px;
            height: 18px;
            line-height: 18px;
            text-align: center;
            cursor: pointer;
        `;

        // Evento para eliminar un archivo
        deleteBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const index = this.files.indexOf(file);
            if (index > -1) {
                this.files.splice(index, 1);
            }

            item.classList.remove('enter'); // quita animación de entrada si aún está
            item.classList.add('exit');     // agrega animación de salida

            setTimeout(() => {
                item.remove();
                this.updateStatus();
            }, 200);
        });


        const leftContainer = document.createElement('div');
        leftContainer.style.display = 'flex';
        leftContainer.style.gap = '10px';
        leftContainer.style.alignItems = 'center';

        const nameContainer = document.createElement('div');
        nameContainer.style.display = 'grid';

        const iconFile = document.createElement('img');
        iconFile.style.width = '20px';
        iconFile.style.height = '24px';
        this.setIconFile(iconFile, file);

        const name = document.createElement('span');
        name.className = 'name';
        name.textContent = file.name;
        name.style.marginBottom = '-3px';
        name.title = file.name;

        const size = document.createElement('span');
        size.className = 'size';
        size.textContent = this.formatFileSize(file.size);

        nameContainer.append(name, size);
        leftContainer.append(iconFile, nameContainer);
        item.append(leftContainer, deleteBtn);
        this.fileList.appendChild(item);

        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                item.classList.add('enter');
            });
        });
    }

    setIconFile(iconFile, file) {
        const ext = file.name.split('.').pop().toLowerCase();
        const mime = file.type;

        if (mime.startsWith('image/') || ['svg', 'png', 'jpg', 'jpeg', 'webp', 'gif'].includes(ext)) {
            iconFile.src = '/assets/src/imgs/image-icon.png';
        } else if (
            ['xls', 'xlsx', 'csv'].includes(ext) ||
            mime.includes('spreadsheet') ||
            mime.includes('excel')
        ) {
            iconFile.src = '/assets/src/imgs/excel-icon.png';
        } else if (
            ['doc', 'docx'].includes(ext) ||
            mime.includes('word')
        ) {
            iconFile.src = '/assets/src/imgs/word-icon.png';
        } else if (
            ['ppt', 'pptx'].includes(ext) ||
            mime.includes('presentation')
        ) {
            iconFile.src = '/assets/src/imgs/power-icon.png';
        } else if (ext === 'pdf') {
            iconFile.src = '/assets/src/imgs/pdf-icon.png';
        } else if (
            ['js', 'ts', 'html', 'css', 'php', 'py', 'java', 'cpp', 'c', 'json', 'xml', 'cs', 'rb', 'go'].includes(ext)
        ) {
            iconFile.src = '/assets/src/imgs/code-icon.png';
        } else if (
            ['sql', 'db', 'sqlite', 'accdb'].includes(ext)
        ) {
            iconFile.src = '/assets/src/imgs/database-icon.png';
        } else if (ext === 'txt') {
            iconFile.src = '/assets/src/imgs/txt-icon.png';
        } else if (
            ['drawio', 'vsdx', 'dia', 'cdx'].includes(ext)
        ) {
            iconFile.src = '/assets/src/imgs/diagram-icon.png';
        } else {
            iconFile.src = '/assets/src/imgs/default-icon.png';
        }
    }

    formatFileSize(bytes) {
        const formatter = new Intl.NumberFormat('es-MX', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        });

        if (bytes < 1024) {
            return `${formatter.format(bytes)} B`;
        } else if (bytes < 1024 ** 2) {
            return `${formatter.format(bytes / 1024)} KB`;
        } else if (bytes < 1024 ** 3) {
            return `${formatter.format(bytes / 1024 ** 2)} MB`;
        } else {
            return `${formatter.format(bytes / 1024 ** 3)} GB`;
        }
    }

    async uploadFile(file, item) {
        const formData = new FormData();
        formData.append('file', file);

        try {
            const res = await fetch('/inicio', {
                method: 'POST',
                body: formData
            });

            if (res.ok) {
                item.style.backgroundColor = '#28a745';
            } else {
                item.style.backgroundColor = '#dc3545';
            }
        } catch (err) {
            console.error('Upload failed', err);
            item.style.backgroundColor = '#dc3545';
        }
    }

    getFormData(fieldName = 'files') {
        const formData = new FormData();
        this.files.forEach(file => {
            formData.append(`${fieldName}[]`, file);
        });
        return formData;
    }
}
