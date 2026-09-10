import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap';
import './assets/styles/main.scss';
import '@fontsource/inter/400.css';
import '@fontsource/inter/500.css';
import '@fontsource/inter/600.css';
import '@fontsource/inter/700.css';
import '@fontsource/jetbrains-mono/500.css';
import './assets/styles/lab-blue.css';
import './assets/styles/lab-resources.css';

import { createPinia } from 'pinia';
import { createApp } from 'vue';

import App from './App.vue';
import router from './router';
import { useAuthStore } from './stores/auth';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);

const authStore = useAuthStore();

window.addEventListener('auth:unauthorized', () => {
  authStore.clearAuth();
});

authStore.initializeAuth().finally(() => {
  app.mount('#app');
});
