const file = document.querySelector('.file_input');
const img  = document.querySelector('.image_tag');

// Listener para actualizar y mostrar la imagen
// actualmente seleccionada por un <input type="file">
file.addEventListener('change', e => {

    const files = e.target.files;
    const file  = files[files.length - 1];
    
    if(file) {
        const reader  = new FileReader();
        reader.onload = function(e) {
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
    
});