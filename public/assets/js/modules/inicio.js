// import { Grid } from '../helpers/Grid.js';
// import { Select } from '../helpers/Select.js';
// import { Datepicker } from '../helpers/Datepicker.js';
// import { FileUploader } from '../helpers/FileUploader.js';

// const grid = new Grid('.rick', 'https://rickandmortyapi.com/api/character', [
//     { key: 'id', label: 'ID' },
//     { key: 'name', label: 'Nombre' },
//     { key: 'origin name', label: 'Origen' },

//     // { key: 'actions', label: '...',
//     //     render: (data) => {
//     //         const container = document.createElement('div');
//     //         container.classList.add('grid-actions');

//     //         let image = document.createElement('img');
//     //         image.style.width = '80px';
//     //         image.style.height = '80px';
//     //         image.src = data.image;

//     //         container.append(
//     //             image
//     //         );

//     //         return container;
//     //     }
//     // },

//     { key: 'actions', label: '...',
//         render: (data) => {
//             const container = document.createElement('div');
//             container.classList.add('grid-actions');

//             container.append(
//                 Grid.createAction('Editar', 'bi bi-pencil-square'),
//                 Grid.createAction('Eliminar', 'bi bi-trash-fill', '#EB755D'),
//                 Grid.createAction('Info.', 'bi bi-info-circle-fill', '#2B5CC5'),
//             );

//             return container;
//         }
//     }
// ], 8, 'results');

// new Select(".first", {
//     items: [
//         { id: 1, ubicacion: 'Mazatlán' },
//         { id: 2, ubicacion: 'Choix' },
//         { id: 3, ubicacion: 'Obregon' },
//         { id: 4, ubicacion: 'Tijuana' },
//         { id: 5, ubicacion: 'Texas' }
//     ],
//     labelKey: "ubicacion",
//     valueKey: "id",
//     name: 'post',
//     searchable: false
// });

// new Select(".second", {
//     url: 'https://countriesnow.space/api/v0.1/countries/population/cities',
//     dataPath: 'data',
//     placeholder: '-- Elige una ciudad --',
//     labelKey: "city",
//     name: 'ciudad',
//     onChange: (item) => console.log("Seleccionado:", item)
// });

// const fechainicio = new Datepicker('.fechainicio', new Date(), {
//     minDate: new Date(), // Hoy
//     maxDate: new Date(2026, 0, 15),
//     inputName: 'inicio'
// });

// const fechafinal = new Datepicker('.fechafinal', "14/12/2025", {
//     inputName: 'final'
// });

// const uploader = new FileUploader('.pedimentos', {
//     // maxFiles: 2,
//     // maxSize: 2048, // KB
//     // only: ['pdf']
// });

// const form = document.querySelector('#formulario');

// form.addEventListener('submit', async (e) => {
//     e.preventDefault();

//     const formData = new FormData(form);

//     const uploaderData = uploader.getFormData('pedimentos');
//     for (const [key, value] of uploaderData.entries())
//         formData.append(key, value);

//     const response = await fetch(form.action, {
//         method: 'POST',
//         body: formData
//     });

//     console.log(await response.text());
// });