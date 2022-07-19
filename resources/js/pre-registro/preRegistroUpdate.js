/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

 window.Vue = require('vue').default;

 import ActualizarDatosPortal from "./components/ActualizarDatosPortal.vue";
 import BuscarUsuarioPortal from "./components/BuscarUsuarioPortal.vue";
 import PreRegistroUpdate from "./components/PreRegistroUpdate.vue";
 
 import VueSweetalert2 from 'vue-sweetalert2';
 import 'sweetalert2/dist/sweetalert2.min.css';
 
 /**
  * The following block of code may be used to automatically register your
  * Vue components. It will recursively scan this directory for the Vue
  * components and automatically register them with their "basename".
  *
  * Eg. ./components/ExampleComponent.vue -> <example-component></example-component>
  */
 
 const app = new Vue({
     el: '#app',
 
     components: {
        ActualizarDatosPortal, 
        BuscarUsuarioPortal, 
        PreRegistroUpdate, 
        VueSweetalert2
     },
     data: {
         academic_programs: academicPrograms,
         imgHeader: imgHeader,
         selected_academic_program: null,
     },
 });
 
 