<template>
  <div class="page owner-page os">
    <header class="page-head">
      <div>
        <h1>{{ $t('settings') }}</h1>
        <p class="muted">{{ $t('settingsHint') }}</p>
      </div>
    </header>

    <div v-if="loading" class="empty">{{ $t('loading') }}</div>

    <template v-else>
      <form class="panel os-panel" @submit.prevent="save">
        <div class="os-panel__head">
          <h2>{{ $t('myCabinet') }}</h2>
          <p>{{ $t('settingsHint') }}</p>
        </div>

        <div class="os-grid">
          <div class="field">
            <label for="os-name">{{ $t('name') }}</label>
            <input id="os-name" v-model="form.name" type="text" required autocomplete="name" />
          </div>
          <div class="field">
            <label for="os-login">{{ $t('login') }}</label>
            <input id="os-login" v-model="form.login" type="text" required autocomplete="username" />
          </div>
          <div class="field">
            <label for="os-password">{{ $t('password') }}</label>
            <div class="os-pass">
              <input
                id="os-password"
                v-model="form.password"
                :type="showPass ? 'text' : 'password'"
                minlength="4"
                :placeholder="$t('leaveBlankUnchanged')"
                autocomplete="new-password"
              />
              <button class="btn btn-outline btn-sm" type="button" @click="showPass = !showPass">
                {{ showPass ? $t('hide') : $t('show') }}
              </button>
            </div>
          </div>
        </div>

        <div v-if="currentPassword" class="os-current">
          <span>{{ $t('currentPassword') }}</span>
          <strong class="mono">{{ showCurrent ? currentPassword : '••••••••' }}</strong>
          <button class="btn btn-outline btn-sm" type="button" @click="showCurrent = !showCurrent">
            {{ showCurrent ? $t('hide') : $t('show') }}
          </button>
        </div>

        <p v-if="error" class="error">{{ error }}</p>
        <p v-if="saved" class="os-ok">{{ $t('saved') }}</p>

        <div class="os-actions">
          <button class="btn btn-primary" type="submit" :disabled="saving">
            {{ saving ? $t('saving') : $t('save') }}
          </button>
        </div>
      </form>

      <section class="panel os-panel os-admins">
        <div class="os-panel__head">
          <h2>{{ $t('platformAdminsTitle') }}</h2>
          <p>{{ $t('platformAdminsHint') }}</p>
        </div>

        <div v-if="adminsLoading" class="empty">{{ $t('loading') }}</div>

        <div v-else class="os-admin-list">
          <article v-for="a in admins" :key="a.id" class="os-admin-card">
            <div class="os-admin-card__top">
              <div>
                <strong>{{ a.name }}</strong>
                <span class="mono">{{ a.login }}</span>
              </div>
              <span class="os-badge" :class="a.is_active ? 'is-on' : 'is-off'">
                {{ a.is_active ? $t('active') : $t('inactive') }}
              </span>
            </div>
            <div class="os-admin-card__pass">
              <span>{{ $t('password') }}</span>
              <strong class="mono">{{ revealed[a.id] ? (a.password_plain || '—') : '••••••••' }}</strong>
              <button class="btn btn-outline btn-sm" type="button" @click="toggleReveal(a.id)">
                {{ revealed[a.id] ? $t('hide') : $t('show') }}
              </button>
            </div>
            <div class="os-admin-card__actions">
              <button class="btn btn-outline btn-sm" type="button" @click="startEdit(a)">{{ $t('edit') }}</button>
              <button
                class="btn btn-outline btn-sm"
                type="button"
                @click="toggleActive(a)"
                :disabled="adminBusy === a.id"
              >
                {{ a.is_active ? $t('deactivate') : $t('activate') }}
              </button>
              <button
                class="btn btn-outline btn-sm os-danger"
                type="button"
                @click="removeAdmin(a)"
                :disabled="adminBusy === a.id"
              >
                {{ $t('delete') }}
              </button>
            </div>
          </article>

          <p v-if="!admins.length" class="muted os-empty-admins">{{ $t('platformAdminsEmpty') }}</p>
        </div>

        <form class="os-admin-form" @submit.prevent="saveAdmin">
          <h3>{{ editingId ? $t('platformAdminEdit') : $t('platformAdminCreate') }}</h3>
          <div class="os-grid">
            <div class="field">
              <label for="pa-name">{{ $t('name') }}</label>
              <input id="pa-name" v-model="adminForm.name" type="text" required />
            </div>
            <div class="field">
              <label for="pa-login">{{ $t('login') }}</label>
              <input id="pa-login" v-model="adminForm.login" type="text" required autocomplete="off" />
            </div>
            <div class="field">
              <label for="pa-password">{{ $t('password') }}</label>
              <input
                id="pa-password"
                v-model="adminForm.password"
                type="text"
                minlength="4"
                :required="!editingId"
                :placeholder="editingId ? $t('leaveBlankUnchanged') : ''"
                autocomplete="off"
              />
            </div>
            <div class="field">
              <label for="pa-phone">{{ $t('phone') }}</label>
              <input id="pa-phone" v-model="adminForm.phone" type="text" />
            </div>
          </div>
          <p v-if="adminError" class="error">{{ adminError }}</p>
          <p v-if="adminSaved" class="os-ok">{{ $t('saved') }}</p>
          <div class="os-actions">
            <button class="btn btn-primary" type="submit" :disabled="adminSaving">
              {{ adminSaving ? $t('saving') : (editingId ? $t('save') : $t('create')) }}
            </button>
            <button v-if="editingId" class="btn btn-outline" type="button" @click="resetAdminForm">
              {{ $t('cancel') }}
            </button>
          </div>
        </form>
      </section>
    </template>
  </div>
</template>

<script>
import { onMounted, reactive, ref } from 'vue';
import auth from '../../auth';
import { t } from '../../i18n';

function emptyAdminForm() {
  return { name: '', login: '', password: '', phone: '' };
}

export default {
  setup() {
    const loading = ref(true);
    const saving = ref(false);
    const error = ref('');
    const saved = ref(false);
    const showPass = ref(false);
    const showCurrent = ref(false);
    const currentPassword = ref('');
    const form = reactive({ name: '', login: '', password: '' });

    const admins = ref([]);
    const adminsLoading = ref(true);
    const adminForm = reactive(emptyAdminForm());
    const editingId = ref(null);
    const adminSaving = ref(false);
    const adminBusy = ref(null);
    const adminError = ref('');
    const adminSaved = ref(false);
    const revealed = reactive({});

    async function load() {
      loading.value = true;
      error.value = '';
      try {
        const { data } = await window.axios.get('/superadmin/settings');
        form.name = data.name || '';
        form.login = data.login || '';
        form.password = '';
        currentPassword.value = data.password_plain || '';
      } catch (e) {
        error.value = e.response?.data?.message || t('errorOccurred');
      } finally {
        loading.value = false;
      }
    }

    async function loadAdmins() {
      adminsLoading.value = true;
      try {
        const { data } = await window.axios.get('/superadmin/platform-admins');
        admins.value = Array.isArray(data) ? data : [];
      } catch (e) {
        adminError.value = e.response?.data?.message || t('errorOccurred');
      } finally {
        adminsLoading.value = false;
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
        };
        if (form.password) {
          payload.password = form.password;
        }
        const { data } = await window.axios.put('/superadmin/settings', payload);
        form.password = '';
        currentPassword.value = data.password_plain || currentPassword.value;
        if (auth.state.user) {
          auth.state.user = { ...auth.state.user, name: data.name, login: data.login };
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

    function resetAdminForm() {
      editingId.value = null;
      Object.assign(adminForm, emptyAdminForm());
      adminError.value = '';
      adminSaved.value = false;
    }

    function startEdit(a) {
      editingId.value = a.id;
      adminForm.name = a.name || '';
      adminForm.login = a.login || '';
      adminForm.password = '';
      adminForm.phone = a.phone || '';
      adminError.value = '';
      adminSaved.value = false;
    }

    function toggleReveal(id) {
      revealed[id] = !revealed[id];
    }

    async function saveAdmin() {
      adminSaving.value = true;
      adminError.value = '';
      adminSaved.value = false;
      try {
        const payload = {
          name: adminForm.name,
          login: adminForm.login,
          phone: adminForm.phone || null,
        };
        if (adminForm.password) payload.password = adminForm.password;

        if (editingId.value) {
          const { data } = await window.axios.put(`/superadmin/platform-admins/${editingId.value}`, payload);
          const idx = admins.value.findIndex((x) => x.id === data.id);
          if (idx >= 0) admins.value[idx] = data;
          else await loadAdmins();
        } else {
          if (!adminForm.password) {
            adminError.value = t('passwordRequired');
            return;
          }
          payload.password = adminForm.password;
          const { data } = await window.axios.post('/superadmin/platform-admins', payload);
          admins.value = [data, ...admins.value];
        }
        resetAdminForm();
        adminSaved.value = true;
      } catch (e) {
        adminError.value = e.response?.data?.message
          || Object.values(e.response?.data?.errors || {})?.[0]?.[0]
          || t('errorOccurred');
      } finally {
        adminSaving.value = false;
      }
    }

    async function toggleActive(a) {
      adminBusy.value = a.id;
      adminError.value = '';
      try {
        const { data } = await window.axios.put(`/superadmin/platform-admins/${a.id}`, {
          is_active: !a.is_active,
        });
        const idx = admins.value.findIndex((x) => x.id === a.id);
        if (idx >= 0) admins.value[idx] = data;
      } catch (e) {
        adminError.value = e.response?.data?.message || t('errorOccurred');
      } finally {
        adminBusy.value = null;
      }
    }

    async function removeAdmin(a) {
      if (!window.confirm(t('platformAdminDeleteConfirm', { name: a.name }))) return;
      adminBusy.value = a.id;
      adminError.value = '';
      try {
        await window.axios.delete(`/superadmin/platform-admins/${a.id}`);
        admins.value = admins.value.filter((x) => x.id !== a.id);
        if (editingId.value === a.id) resetAdminForm();
      } catch (e) {
        adminError.value = e.response?.data?.message || t('errorOccurred');
      } finally {
        adminBusy.value = null;
      }
    }

    onMounted(() => {
      load();
      loadAdmins();
    });

    return {
      loading,
      saving,
      error,
      saved,
      showPass,
      showCurrent,
      currentPassword,
      form,
      save,
      admins,
      adminsLoading,
      adminForm,
      editingId,
      adminSaving,
      adminBusy,
      adminError,
      adminSaved,
      revealed,
      toggleReveal,
      startEdit,
      resetAdminForm,
      saveAdmin,
      toggleActive,
      removeAdmin,
    };
  },
};
</script>

<style scoped>
.os-panel {
  max-width: 820px;
}
.os-panel__head {
  margin-bottom: 1rem;
}
.os-panel__head h2 {
  margin: 0 0 0.25rem;
  font-size: 1.1rem;
  font-weight: 850;
  color: #0f172a;
}
.os-panel__head p {
  margin: 0;
  color: #475569;
  font-size: 0.9rem;
}

.os-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.85rem 1rem;
}

.os-pass {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}
.os-pass input {
  flex: 1;
  min-width: 0;
}

.os-current,
.os-admin-card__pass {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.55rem 0.75rem;
  margin-top: 1rem;
  padding: 0.75rem 0.9rem;
  border-radius: 12px;
  background: #f8faf9;
  border: 1px solid rgba(15, 23, 42, 0.06);
}
.os-current span,
.os-admin-card__pass span {
  font-size: 0.84rem;
  font-weight: 700;
  color: #3a5548;
}
.os-current strong,
.os-admin-card__pass strong {
  font-size: 0.95rem;
  color: #0f172a;
}

.os-ok {
  margin: 0.75rem 0 0;
  color: #15803d;
  font-weight: 700;
}

.os-actions {
  display: flex;
  gap: 0.65rem;
  margin-top: 1.15rem;
  padding-top: 1rem;
  border-top: 1px solid rgba(15, 23, 42, 0.06);
}

.os-admins {
  margin-top: 1.25rem;
}

.os-admin-list {
  display: grid;
  gap: 0.85rem;
  margin-bottom: 1.25rem;
}

.os-admin-card {
  border: 1px solid rgba(15, 23, 42, 0.08);
  border-radius: 14px;
  padding: 0.9rem 1rem;
  background: #fff;
}

.os-admin-card__top {
  display: flex;
  justify-content: space-between;
  gap: 0.75rem;
  align-items: flex-start;
}
.os-admin-card__top strong {
  display: block;
  font-size: 1rem;
  color: #0f172a;
}
.os-admin-card__top .mono {
  display: block;
  margin-top: 0.15rem;
  color: #475569;
  font-size: 0.88rem;
}

.os-badge {
  font-size: 0.75rem;
  font-weight: 800;
  padding: 0.2rem 0.55rem;
  border-radius: 999px;
}
.os-badge.is-on {
  background: #dcfce7;
  color: #166534;
}
.os-badge.is-off {
  background: #fee2e2;
  color: #991b1b;
}

.os-admin-card__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
  margin-top: 0.75rem;
}

.os-danger {
  color: #b91c1c !important;
  border-color: rgba(185, 28, 28, 0.35) !important;
}

.os-admin-form {
  margin-top: 0.5rem;
  padding-top: 1rem;
  border-top: 1px solid rgba(15, 23, 42, 0.06);
}
.os-admin-form h3 {
  margin: 0 0 0.85rem;
  font-size: 0.98rem;
  font-weight: 800;
  color: #0f172a;
}

.os-empty-admins {
  margin: 0;
  padding: 0.5rem 0;
}

.mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

@media (max-width: 640px) {
  .os-grid {
    grid-template-columns: 1fr;
  }
}
</style>
