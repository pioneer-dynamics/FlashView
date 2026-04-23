import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import './assets/main.css';

document.documentElement.classList.add('dark');

createApp(App).use(router).mount('#app');
