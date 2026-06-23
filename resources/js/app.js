require('./bootstrap');

window.Vue = require('vue').default;

import Dashboard from './components/Dashboard.vue';

const app = new Vue({
    el: '#app',
    components: {
        'dashboard-component': Dashboard
    }
});