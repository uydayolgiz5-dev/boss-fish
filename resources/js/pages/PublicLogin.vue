<template>
  <div class="auth-page auth-login">
    <div class="auth-card auth-card--login">
      <div class="auth-card-top">
        <div class="auth-brand">
          <BrandLogo variant="light" />
        </div>
        <LanguageSwitcher />
      </div>

      <div class="auth-card-body">
        <h1>{{ $t('loginGreeting') }}</h1>
        <p class="subtitle">{{ $t('companyIdLoginHint') }}</p>

        <form @submit.prevent="submit" class="form-grid one-col">
          <div class="field">
            <label>{{ $t('companyId') }}</label>
            <input
              v-model="form.company_id"
              type="number"
              min="0"
              required
              :placeholder="$t('companyId')"
            />
          </div>
          <div class="field">
            <label>{{ $t('login') }}</label>
            <input
              v-model="form.login"
              type="text"
              required
              autocomplete="username"
              :placeholder="$t('loginOrPhone')"
            />
          </div>
          <div class="field">
            <label>{{ $t('password') }}</label>
            <input
              v-model="form.password"
              type="password"
              required
              minlength="4"
              :placeholder="$t('password')"
            />
          </div>
          <p v-if="error" class="error">{{ error }}</p>
          <button class="btn btn-primary auth-submit" type="submit" :disabled="loading">
            {{ loading ? $t('loggingIn') : $t('login') }}
          </button>
        </form>

        <router-link class="auth-back" :to="{ name: 'home' }">{{ $t('back') }}</router-link>
      </div>

      <div class="auth-card-foot">
        <p class="auth-contact">{{ $t('loginContactHint') }}</p>
        <a class="auth-contact-link" :href="`tel:${supportTel}`">{{ supportPhone }}</a>
        <p class="auth-rights">{{ $t('loginRightsReserved') }}</p>
      </div>
    </div>
  </div>
</template>

<script>
import { onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import auth from '../auth';
import BrandLogo from '../components/BrandLogo.vue';
import LanguageSwitcher from '../components/LanguageSwitcher.vue';
import { t } from '../i18n';

export default {
  components: { BrandLogo, LanguageSwitcher },
  props: {
    role: { type: String, default: null },
  },
  setup() {
    const router = useRouter();
    const loading = ref(false);
    const error = ref('');
    const form = reactive({ company_id: '', login: '', password: '' });
    const supportPhone = '+998 77 444 2525';
    const supportTel = '+998774442525';

    onMounted(async () => {
      if (!auth.hasValidSession()) return;
      if (!auth.state.user) {
        await auth.fetchUser();
      }
      if (auth.isAuthenticated()) {
        await router.replace(auth.homeForRole());
      }
    });

    async function submit() {
      loading.value = true;
      error.value = '';
      try {
        auth.clearAuth();
        const { data } = await window.axios.post('/auth/login', {
          company_id: Number(form.company_id),
          login: String(form.login).trim(),
          password: form.password,
        });
        auth.setAuth(data.user, data.token, data.expires_at);
        await router.push(auth.homeForRole());
      } catch (e) {
        const errors = e.response?.data?.errors || {};
        error.value = errors.login?.[0]
          || errors.company_id?.[0]
          || errors.password?.[0]
          || e.response?.data?.message
          || t('errorOccurred');
      } finally {
        loading.value = false;
      }
    }

    return { form, loading, error, submit, supportPhone, supportTel };
  },
};
</script>
