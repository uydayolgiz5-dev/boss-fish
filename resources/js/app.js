import './bootstrap';
import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import auth from './auth';
import FormattedNumberInput from './components/FormattedNumberInput.vue';
import { setLocale, i18nState, t } from './i18n';
import { formatDisplayDate, formatDisplayDateTime } from './utils/date';
import { initTheme } from './utils/theme';

initTheme();
setLocale(i18nState.locale);

async function bootstrap() {
    await auth.initAuth();

    const app = createApp(App);
    app.component('FormattedNumberInput', FormattedNumberInput);
    // $t + locale — barcha sahifalarda til almashtirish bir xil ishlashi uchun
    Object.defineProperty(app.config.globalProperties, '$t', {
        get() {
            void i18nState.locale;
            return t;
        },
    });
    Object.defineProperty(app.config.globalProperties, '$locale', {
        get() {
            return i18nState.locale;
        },
    });
    // Sana ko‘rinishi butun saytda: 24.09.2026
    app.config.globalProperties.$formatDate = formatDisplayDate;
    app.config.globalProperties.$formatDateTime = formatDisplayDateTime;
    app.use(router);
    await router.isReady();
    app.mount('#app');
}

bootstrap();
