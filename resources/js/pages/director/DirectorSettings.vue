<template>
  <div class="page">
    <header class="page-head">
      <div>
        <h1>{{ $t('settings') }}</h1>
        <p class="muted">{{ $t('directorSettingsHint') }}</p>
      </div>
    </header>

    <section class="panel theme-panel" aria-labelledby="theme-heading">
      <h2 id="theme-heading" class="settings-section-title">{{ $t('appearanceTitle') }}</h2>
      <p class="muted theme-panel__hint">{{ $t('appearanceHint') }}</p>
      <div class="theme-choices" role="group" :aria-label="$t('appearanceTitle')">
        <button
          type="button"
          class="theme-choice"
          :class="{ 'is-active': theme === 'light' }"
          @click="chooseTheme('light')"
        >
          <span class="theme-choice__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="4" />
              <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
            </svg>
          </span>
          <strong>{{ $t('themeLight') }}</strong>
          <span>{{ $t('themeLightHint') }}</span>
        </button>
        <button
          type="button"
          class="theme-choice"
          :class="{ 'is-active': theme === 'dark' }"
          @click="chooseTheme('dark')"
        >
          <span class="theme-choice__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 14.5A8.5 8.5 0 1 1 9.5 3a7 7 0 0 0 11.5 11.5z" />
            </svg>
          </span>
          <strong>{{ $t('themeDark') }}</strong>
          <span>{{ $t('themeDarkHint') }}</span>
        </button>
      </div>
    </section>

    <nav class="settings-links" :aria-label="$t('settings')">
      <router-link class="settings-link" :to="{ name: 'director-audit' }">
        <strong>{{ $t('auditJournal') }}</strong>
        <span>{{ $t('auditJournalHint') }}</span>
      </router-link>
    </nav>

    <div v-if="loading" class="muted">{{ $t('loading') }}</div>

    <form v-else class="panel form-grid one-col director-profile-form" @submit.prevent="save">
      <h2 class="settings-section-title">{{ $t('directorProfile') }}</h2>
      <div class="field">
        <label>{{ $t('name') }}</label>
        <input v-model.trim="form.name" type="text" required autocomplete="name" />
      </div>
      <div class="field">
        <label>{{ $t('login') }}</label>
        <input v-model.trim="form.login" type="text" required autocomplete="username" />
      </div>
      <div class="field">
        <label>{{ $t('phone') }}</label>
        <input v-model.trim="form.phone" type="text" autocomplete="tel" />
      </div>
      <div class="field">
        <label>{{ $t('password') }}</label>
        <input
          v-model="form.password"
          type="password"
          minlength="4"
          :placeholder="$t('leaveBlankUnchanged')"
          autocomplete="new-password"
        />
      </div>

      <p v-if="error" class="error">{{ error }}</p>
      <p v-if="saved" class="success-hint">{{ $t('saved') }}</p>

      <div class="form-actions">
        <button class="btn btn-primary" type="submit" :disabled="saving">
          {{ saving ? $t('saving') : $t('save') }}
        </button>
      </div>
    </form>
  </div>
</template>

<script>
import { computed, onMounted, reactive, ref } from 'vue';
import auth from '../../auth';
import { t } from '../../i18n';
import { setTheme, themeState } from '../../utils/theme';

export default {
  setup() {
    const loading = ref(true);
    const saving = ref(false);
    const error = ref('');
    const saved = ref(false);
    const form = reactive({ name: '', login: '', phone: '', password: '' });
    const theme = computed(() => themeState.theme);

    function chooseTheme(next) {
      setTheme(next);
    }

    async function load() {
      loading.value = true;
      error.value = '';
      try {
        const { data } = await window.axios.get('/director/profile');
        form.name = data.name || '';
        form.login = data.login || '';
        form.phone = data.phone || '';
        form.password = '';
      } catch (e) {
        error.value = e.response?.data?.message || t('errorOccurred');
      } finally {
        loading.value = false;
      }
    }

    async function save() {
      saving.value = true;
      error.value = '';
      saved.value = false;
      try {
        const payload = {
          name: form.name,
          login: form.login,
          phone: form.phone || null,
        };
        if (form.password) payload.password = form.password;
        const { data } = await window.axios.put('/director/profile', payload);
        form.password = '';
        if (auth.state.user) {
          auth.state.user = {
            ...auth.state.user,
            name: data.name,
            login: data.login,
            phone: data.phone,
          };
        }
        saved.value = true;
      } catch (e) {
        error.value = e.response?.data?.message
          || Object.values(e.response?.data?.errors || {})?.[0]?.[0]
          || t('errorOccurred');
      } finally {
        saving.value = false;
      }
    }

    onMounted(load);
    return { loading, saving, error, saved, form, save, theme, chooseTheme };
  },
};
</script>

<style scoped>
.theme-panel {
  margin: 0 0 1rem;
  padding: 1.1rem 1.15rem;
}

.theme-panel__hint {
  margin: 0 0 0.85rem;
  font-size: 0.88rem;
}

.theme-choices {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.65rem;
}

.theme-choice {
  display: grid;
  gap: 0.2rem;
  justify-items: start;
  text-align: left;
  padding: 0.85rem 0.9rem;
  border: 1.5px solid rgba(15, 23, 42, 0.12);
  border-radius: 12px;
  background: #f8fafc;
  color: inherit;
  cursor: pointer;
  transition: border-color 0.15s ease, background 0.15s ease;
}

.theme-choice__icon {
  display: inline-flex;
  margin-bottom: 0.15rem;
  color: #0f766e;
}

.theme-choice strong {
  font-size: 0.95rem;
}

.theme-choice span:last-child {
  font-size: 0.8rem;
  color: #64748b;
}

.theme-choice.is-active {
  border-color: #0f766e;
  background: #f0fdfa;
  box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.12);
}

.settings-links {
  display: grid;
  gap: 0.65rem;
  margin: 0 0 1rem;
}
.settings-link {
  display: grid;
  gap: 0.2rem;
  padding: 0.9rem 1rem;
  border: 1px solid rgba(15, 23, 42, 0.1);
  border-radius: 12px;
  background: #fff;
  text-decoration: none;
  color: inherit;
}
.settings-link strong {
  font-size: 0.98rem;
}
.settings-link span {
  font-size: 0.85rem;
  color: #64748b;
}
.settings-section-title {
  margin: 0 0 0.35rem;
  font-size: 1.05rem;
}
.director-profile-form {
  max-width: 28rem;
  padding: 1.25rem;
}
.form-actions {
  margin-top: 0.5rem;
}
.success-hint {
  color: #047857;
  margin: 0;
}

@media (max-width: 520px) {
  .theme-choices {
    grid-template-columns: 1fr;
  }
}
</style>
