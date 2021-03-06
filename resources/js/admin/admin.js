/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

window.Vue = require('vue').default;
window.Event = new Vue();
import UsuariosCe from './components/UsuariosCe';
import NuevoUsuario from './components/NuevoUsuario';
import EditarUsuario from './components/EditarUsuario';
import UsuariosCeRowTemplate from './components/UsuariosCeRowTemplate.vue';
import { BootstrapVue,BootstrapVueIcons  } from 'bootstrap-vue'
import { GridPlugin } from '@syncfusion/ej2-vue-grids';

Vue.use(GridPlugin);
Vue.use(BootstrapVue);
Vue.use(BootstrapVueIcons);

/**
 * The following block of code may be used to automatically register your
 * Vue components. It will recursively scan this directory for the Vue
 * components and automatically register them with their "basename".
 *
 * Eg. ./components/ExampleComponent.vue -> <example-component></example-component>
 */

const app = new Vue({
    el: '#app',

    data: {
        roles: roles,
        academic_areas: academic_areas,
        academic_entities: academic_entities,
        academic_comittes: academic_comittes,
       
    },

    components: {
        'usuarios-ce': UsuariosCe,
        'nuevo-usuario':NuevoUsuario,
        'editar-usuario':EditarUsuario,
        'usuarios-ce-row-template': UsuariosCeRowTemplate
    },

    methods: {


        
    },
});