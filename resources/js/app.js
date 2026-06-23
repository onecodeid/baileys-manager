require('./bootstrap');

window.Vue = require('vue').default;

import Dashboard from './components/Dashboard.vue';

const app = new Vue({
    el: '#app',
    components: {
        'dashboard-component': Dashboard
    }
});

// Tambahkan ini untuk auto attach token
axios.interceptors.request.use(config => {
    const token = document.querySelector('meta[name="csrf-token"]');
    if (token) {
        config.headers['X-CSRF-TOKEN'] = token.getAttribute('content');
    }
    return config;
});