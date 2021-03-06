const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */
//mix.setPublicPath('./controlescolar/');
mix.js('resources/js/app.js', 'public/js').vue().sass('resources/sass/app.scss', 'public/css');
mix.js('resources/js/admin/admin.js', 'public/js').vue();
mix.js('resources/js/home/home.js', 'public/js').vue();

// Pre-registro
mix.js('resources/js/pre-registro/preregistro.js', 'public/js').vue();
mix.js('resources/js/pre-registro/preRegistroUpdate.js', 'public/js').vue();


// Vistas postulación
mix.js('resources/js/postulacion/postulacion.js', 'public/js').vue();

mix.js('resources/js/postulacion/appliant-view/appliant.js', 'public/appliant/js').vue();
mix.js('resources/js/postulacion/appliant-view/appliantUpdateDocuments.js', 'public/appliant/js').vue();
mix.js('resources/js/postulacion/appliant-view/appliantShowRegisterArchives.js', 'public/appliant/js').vue();
mix.js('resources/js/postulacion/appliant-view/appliantNewArchive.js', 'public/appliant/js').vue();


mix.js('resources/js/postulacion/professor-view/professor.js', 'public/professor/js').vue();
mix.js('resources/js/postulacion/professor-view-only-rl/professor-only-rl.js', 'public/professor/js').vue();

mix.js('resources/js/postulacion/controlescolar-view/controlescolar.js', 'public/controlescolar/js').vue();
mix.js('resources/js/postulacion/close-view/close.js', 'public/postulacion/js').vue();

// Carta de recomendacion
mix.js('resources/js/recommendation-letter/recommendation-letter.js', 'public/js').vue();
// Carta de intencion
mix.js('resources/js/postulacion/intention-letter.js', 'public/js').vue();

// Entrevistas
mix.js('resources/js/entrevistas/entrevistas.js', 'public/js').vue();
mix.js('resources/js/entrevistas-profesor/programaEntrevistas.js', 'public/js').vue();
mix.js('resources/js/rubrica/rubrica.js', 'public/js').vue();
mix.js('resources/js/rubrica/rubricaPromedio.js', 'public/js').vue();
mix.js('resources/js/rubrica/rubricaPromedioCa.js', 'public/js').vue();
mix.js('resources/js/postulacion/appliant-view/appliantInterviewUpdateDocuments.js', 'public/appliant/js').vue();
