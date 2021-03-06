/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */


import AverageRubricCa from './components/AverageRubricCa';
import { GridPlugin } from '@syncfusion/ej2-vue-grids';
import axios from 'axios';

window.Vue = require('vue').default;
Vue.use(GridPlugin);

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
        appliant: appliant,
        rubric: rubric,
        appliant: appliant,
        scores: scores,
        type: type
    },

    mounted() {
        // console.log(this.scores); 
    },

    methods:{
    },

    components: {
        AverageRubricCa,
    },
});