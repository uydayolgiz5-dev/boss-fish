<template>
  <div class="page">
    <header class="page-head">
      <div>
        <h1>{{ $t('companyEmployees') }}</h1>
        <p class="muted">{{ $t('companyEmployeesHint') }}</p>
      </div>
      <button class="btn btn-primary" type="button" @click="openCreate">{{ $t('add') }}</button>
    </header>

    <div v-if="loading" class="muted">{{ $t('loading') }}</div>
    <div v-else-if="loadError" class="employee-empty error">{{ loadError }}</div>
    <div v-else-if="!employees.length" class="employee-empty">{{ $t('noData') }}</div>
    <div v-else class="employee-list">
      <article
        v-for="e in employees"
        :key="e.id"
        class="employee-card"
        :class="{ 'is-inactive': e.is_active === false }"
      >
        <header class="employee-card__head">
          <div class="employee-card__who">
            <span class="employee-card__avatar" aria-hidden="true">{{ initials(e.name) }}</span>
            <div class="employee-card__identity">
              <div class="employee-card__title-row">
                <strong>{{ e.name }}</strong>
                <small v-if="e.is_active === false" class="employee-card__off">{{ $t('inactive') }}</small>
                <small v-else class="employee-card__on">{{ $t('active') }}</small>
              </div>
              <dl class="employee-card__creds">
                <div>
                  <dt>{{ $t('login') }}</dt>
                  <dd>{{ e.login }}</dd>
                </div>
                <div>
                  <dt>{{ $t('password') }}</dt>
                  <dd>{{ $t('passwordHidden') }}</dd>
                </div>
              </dl>
            </div>
          </div>
          <div class="employee-card__actions">
            <button class="btn btn-primary btn-sm" type="button" @click="openEdit(e)">{{ $t('edit') }}</button>
            <button class="btn btn-outline btn-sm" type="button" @click="resetPassword(e)">{{ $t('resetPassword') }}</button>
            <button class="btn btn-outline btn-sm btn-danger-outline" type="button" @click="remove(e)">{{ $t('delete') }}</button>
          </div>
        </header>

        <div class="employee-card__roles-block">
          <div class="employee-card__roles-head">
            <p class="employee-card__roles-label">{{ $t('companyEmployeeAllowedRoles') }}</p>
            <span class="employee-card__roles-count">
              {{ (e.roles || []).length
                ? $t('companyEmployeeSelectedCount', { n: (e.roles || []).length })
                : $t('companyEmployeeNoneSelected') }}
            </span>
          </div>
          <ul v-if="(e.roles || []).length" class="employee-card__perms">
            <li v-for="key in e.roles" :key="`${e.id}-${key}`">
              <span class="role-num">{{ roleNumber(key) }}</span>
              <span class="employee-card__perm-text">{{ roleLabel(key) }}</span>
            </li>
          </ul>
          <p v-else class="employee-card__empty">{{ $t('companyNoTasks') }}</p>
        </div>
      </article>
    </div>

    <AppModal
      v-model="showForm"
      :title="editingId ? $t('companyEditEmployee') : $t('companyAddEmployee')"
      :close-on-backdrop="!saving"
    >
      <form id="employee-edit-form" class="form-grid one-col employee-form" @submit.prevent="save">
        <div class="field">
          <label>{{ $t('name') }}</label>
          <input v-model.trim="form.name" type="text" required autocomplete="off" name="employee_name" />
        </div>
        <div class="field">
          <label>{{ $t('login') }}</label>
          <input
            v-model.trim="form.login"
            type="text"
            required
            autocomplete="off"
            name="employee_login"
            inputmode="text"
            :placeholder="$t('companyEmployeeLoginPh')"
          />
          <small class="field-hint">{{ $t('companyEmployeeLoginHint') }}</small>
        </div>
        <div class="field">
          <label>{{ editingId ? $t('newPasswordOptional') : $t('password') }}</label>
          <input
            v-model="form.password"
            type="password"
            :required="!editingId"
            minlength="4"
            autocomplete="new-password"
            name="employee_password"
            :placeholder="editingId ? $t('leaveBlankUnchanged') : ''"
          />
        </div>

        <div class="field roles-field">
          <div class="roles-field__head">
            <label>{{ $t('companyEmployeeTasks') }}</label>
            <span class="roles-count" :class="{ 'is-empty': !form.roles.length }">
              {{ form.roles.length
                ? $t('companyEmployeeSelectedCount', { n: form.roles.length })
                : $t('companyEmployeeNoneSelected') }}
            </span>
          </div>
          <p class="field-hint">{{ $t('companyEmployeeTasksHintShort') }}</p>

          <div class="perm-grid" role="group" :aria-label="$t('companyEmployeeTasks')">
            <label
              v-for="key in jobRoles"
              :key="key"
              class="perm-item"
              :class="{ 'is-on': form.roles.includes(key) }"
            >
              <input v-model="form.roles" type="checkbox" class="perm-item__input" :value="key" />
              <span class="perm-item__mark" aria-hidden="true">
                <svg v-if="form.roles.includes(key)" viewBox="0 0 20 20" width="14" height="14" fill="none">
                  <path d="M4.5 10.5 8 14l7.5-8" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </span>
              <span class="perm-item__body">
                <strong>
                  <span class="role-num">{{ roleNumber(key) }}</span>
                  {{ roleLabel(key) }}
                </strong>
              </span>
            </label>
          </div>
        </div>

        <label v-if="editingId" class="active-toggle">
          <input v-model="form.is_active" type="checkbox" />
          <span>{{ $t('active') }}</span>
        </label>

        <p v-if="error" class="error">{{ error }}</p>
      </form>

      <template #footer>
        <div class="employee-form-actions">
          <button
            class="btn btn-primary"
            type="submit"
            form="employee-edit-form"
            :disabled="saving || !form.roles.length"
          >
            {{ saving ? $t('loading') : $t('save') }}
          </button>
          <button class="btn btn-outline" type="button" :disabled="saving" @click="showForm = false">
            {{ $t('cancel') }}
          </button>
        </div>
      </template>
    </AppModal>
  </div>
</template>

<script>
import { onMounted, reactive, ref } from 'vue';
import AppModal from '../../components/AppModal.vue';
import { showSaved } from '../../flash';
import { t } from '../../i18n';

const ROLE_ORDER = [
  'full_manage',
  'feed_report',
  'fish_stock',
  'water_test',
  'warehouse',
  'finance_expense',
  'reports_view',
];

const ROLE_LABELS = {
  full_manage: 'jobRoleFullManage',
  feed_report: 'jobRoleFeedReport',
  fish_stock: 'jobRoleFishStock',
  water_test: 'jobRoleWaterTest',
  warehouse: 'jobRoleWarehouse',
  finance_expense: 'jobRoleFinanceExpense',
  reports_view: 'jobRoleReportsView',
};

export default {
  components: { AppModal },
  setup() {
    const employees = ref([]);
    const jobRoles = ref([]);
    const loading = ref(true);
    const loadError = ref('');
    const saving = ref(false);
    const showForm = ref(false);
    const editingId = ref(null);
    const error = ref('');
    const form = reactive({
      name: '',
      login: '',
      password: '',
      roles: [],
      is_active: true,
    });

    function roleNumber(key) {
      const idx = ROLE_ORDER.indexOf(key);
      return idx >= 0 ? String(idx + 1) : '';
    }

    function roleLabel(key) {
      return ROLE_LABELS[key] ? t(ROLE_LABELS[key]) : key;
    }

    function initials(name) {
      const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
      if (!parts.length) return '?';
      if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
      return `${parts[0][0] || ''}${parts[1][0] || ''}`.toUpperCase();
    }

    async function load() {
      loading.value = true;
      loadError.value = '';
      try {
        const [empRes, keysRes] = await Promise.all([
          window.axios.get('/company/employees'),
          window.axios.get('/company/permission-keys'),
        ]);
        employees.value = empRes.data;
        const keys = keysRes.data.job_roles || [];
        jobRoles.value = ROLE_ORDER.filter((k) => keys.includes(k)).concat(
          keys.filter((k) => !ROLE_ORDER.includes(k)),
        );
      } catch (e) {
        employees.value = [];
        loadError.value = e.response?.data?.message || t('errorOccurred');
      } finally {
        loading.value = false;
      }
    }

    function openCreate() {
      error.value = '';
      editingId.value = null;
      Object.assign(form, {
        name: '',
        login: '',
        password: '',
        roles: [],
        is_active: true,
      });
      showForm.value = true;
    }

    function openEdit(employee) {
      error.value = '';
      editingId.value = employee.id;
      Object.assign(form, {
        name: employee.name || '',
        login: employee.login || '',
        password: '',
        roles: [...(employee.roles || [])],
        is_active: employee.is_active !== false,
      });
      showForm.value = true;
    }

    async function save() {
      if (!form.roles.length) {
        error.value = t('rolePermissionsRequired');
        return;
      }
      saving.value = true;
      error.value = '';
      try {
        const payload = {
          name: form.name,
          login: form.login,
          roles: form.roles,
        };
        let oncePassword = '';
        if (editingId.value) {
          payload.is_active = !!form.is_active;
          if (form.password) payload.password = form.password;
          const { data } = await window.axios.put(`/company/employees/${editingId.value}`, payload);
          oncePassword = data?.password_plain || '';
        } else {
          payload.password = form.password;
          const { data } = await window.axios.post('/company/employees', payload);
          oncePassword = data?.password_plain || form.password || '';
        }
        showSaved(t('saved'));
        if (oncePassword) {
          window.alert(`${t('passwordOnceHint')}\n\n${t('login')}: ${form.login}\n${t('password')}: ${oncePassword}`);
        }
        showForm.value = false;
        editingId.value = null;
        Object.assign(form, {
          name: '',
          login: '',
          password: '',
          roles: [],
          is_active: true,
        });
        await load();
      } catch (e) {
        const fieldErrors = e.response?.data?.errors || {};
        error.value = fieldErrors.login?.[0]
          || fieldErrors.password?.[0]
          || fieldErrors.name?.[0]
          || fieldErrors.roles?.[0]
          || e.response?.data?.message
          || t('errorOccurred');
      } finally {
        saving.value = false;
      }
    }

    async function remove(employee) {
      if (!confirm(t('confirmDeleteEntry', { name: employee.name }))) return;
      try {
        await window.axios.delete(`/company/employees/${employee.id}`);
        showSaved(t('deleted'));
        await load();
      } catch (e) {
        window.alert(e.response?.data?.message || t('errorOccurred'));
      }
    }

    async function resetPassword(employee) {
      if (!confirm(t('resetPasswordConfirm'))) return;
      try {
        const { data } = await window.axios.post(`/company/employees/${employee.id}/reset-password`);
        const oncePassword = data?.password_plain || '';
        if (oncePassword) {
          window.alert(`${t('passwordOnceHint')}\n\n${t('login')}: ${data.login || employee.login}\n${t('password')}: ${oncePassword}`);
        }
        showSaved(t('saved'));
      } catch (e) {
        window.alert(e.response?.data?.message || t('errorOccurred'));
      }
    }

    onMounted(load);
    return {
      employees,
      jobRoles,
      loading,
      loadError,
      saving,
      showForm,
      editingId,
      form,
      error,
      roleNumber,
      roleLabel,
      initials,
      openCreate,
      openEdit,
      save,
      remove,
      resetPassword,
    };
  },
};
</script>

<style scoped>
.employee-empty {
  margin-top: 0.5rem;
  padding: 1.25rem 1rem;
  border: 1px dashed rgba(15, 23, 42, 0.16);
  border-radius: 16px;
  color: #64748b;
  text-align: center;
  background: #f8fafc;
}

.employee-list {
  display: grid;
  gap: 1rem;
  margin-top: 0.35rem;
}

.employee-card {
  display: grid;
  gap: 0;
  border: 1px solid rgba(15, 23, 42, 0.1);
  border-radius: 18px;
  background: #fff;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
  overflow: hidden;
}

.employee-card.is-inactive {
  opacity: 0.78;
  background: #f8fafc;
}

.employee-card__head {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.85rem 1rem;
  padding: 1rem 1.05rem;
  background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);
  border-bottom: 1px solid rgba(15, 23, 42, 0.08);
}

.employee-card__who {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  min-width: 0;
  flex: 1;
}

.employee-card__avatar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.6rem;
  height: 2.6rem;
  border-radius: 14px;
  background: #0f766e;
  color: #fff;
  font-size: 0.86rem;
  font-weight: 800;
  letter-spacing: 0.02em;
  flex-shrink: 0;
}

.employee-card__identity {
  min-width: 0;
  flex: 1;
}

.employee-card__title-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  align-items: center;
}

.employee-card__title-row strong {
  font-size: 1.05rem;
  color: #0f172a;
  line-height: 1.2;
}

.employee-card__creds {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem 0.85rem;
  margin: 0.45rem 0 0;
}

.employee-card__creds > div {
  display: flex;
  align-items: baseline;
  gap: 0.35rem;
  min-width: 0;
}

.employee-card__creds dt {
  margin: 0;
  color: #94a3b8;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.employee-card__creds dd {
  margin: 0;
  color: #334155;
  font-size: 0.9rem;
  font-weight: 650;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  word-break: break-all;
}

.employee-card__roles-block {
  padding: 0.85rem 1.05rem 1rem;
  background: #fff;
}

.employee-card__roles-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.35rem 0.75rem;
  margin-bottom: 0.55rem;
}

.employee-card__roles-label {
  margin: 0;
  font-size: 0.74rem;
  font-weight: 750;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #64748b;
}

.employee-card__roles-count {
  font-size: 0.76rem;
  font-weight: 700;
  color: #0f766e;
  background: #ecfdf5;
  border: 1px solid #a7f3d0;
  border-radius: 999px;
  padding: 0.12rem 0.55rem;
}

.employee-card__empty {
  margin: 0;
  color: #b45309;
  font-size: 0.88rem;
  font-weight: 650;
  padding: 0.55rem 0.7rem;
  border-radius: 12px;
  background: #fffbeb;
  border: 1px solid #fcd34d;
}

.employee-card__perms {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  list-style: none;
  margin: 0;
  padding: 0;
}

.employee-card__perms li {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  max-width: 100%;
  padding: 0.35rem 0.55rem 0.35rem 0.35rem;
  border-radius: 999px;
  background: #ecfdf5;
  border: 1px solid #99f6e4;
  color: #065f46;
  font-size: 0.82rem;
  font-weight: 650;
  line-height: 1.25;
}

.employee-card__perm-text {
  min-width: 0;
}

.employee-card__off,
.employee-card__on {
  font-size: 0.72rem;
  font-weight: 750;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  border-radius: 999px;
  padding: 0.12rem 0.45rem;
}

.employee-card__off {
  color: #b91c1c;
  background: #fef2f2;
}

.employee-card__on {
  color: #047857;
  background: #ecfdf5;
}

.employee-card__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  flex-shrink: 0;
}

@media (max-width: 640px) {
  .employee-card__head {
    flex-direction: column;
  }

  .employee-card__actions {
    width: 100%;
  }

  .employee-card__actions .btn {
    flex: 1;
  }
}

.role-num {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.35rem;
  height: 1.35rem;
  padding: 0 0.25rem;
  border-radius: 999px;
  background: #0f766e;
  color: #fff;
  font-size: 0.72rem;
  font-weight: 800;
  font-style: normal;
  flex-shrink: 0;
}

.field-hint {
  display: block;
  margin: 0.2rem 0 0.55rem;
  color: #64748b;
  font-size: 0.8rem;
}

.roles-field__head {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.4rem;
}

.roles-count {
  font-size: 0.78rem;
  font-weight: 750;
  color: #0f766e;
  background: #ecfdf5;
  border: 1px solid #a7f3d0;
  border-radius: 999px;
  padding: 0.18rem 0.55rem;
}

.roles-count.is-empty {
  color: #92400e;
  background: #fffbeb;
  border-color: #fcd34d;
}

.perm-grid {
  display: grid;
  gap: 0.4rem;
}

.perm-item {
  position: relative;
  display: flex;
  gap: 0.65rem;
  align-items: center;
  min-height: 48px;
  padding: 0.65rem 0.75rem;
  border: 1.5px solid #e2e8f0;
  border-radius: 12px;
  background: #fff;
  cursor: pointer;
  transition: border-color 0.15s ease, background 0.15s ease;
  -webkit-tap-highlight-color: transparent;
}

.perm-item:hover {
  border-color: #94a3b8;
}

.perm-item.is-on {
  border-color: #0f766e;
  background: #f0fdfa;
}

.perm-item__input {
  position: absolute;
  opacity: 0;
  width: 1px;
  height: 1px;
  pointer-events: none;
}

.perm-item__mark {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.35rem;
  height: 1.35rem;
  border-radius: 6px;
  border: 2px solid #94a3b8;
  background: #fff;
  color: #fff;
  flex-shrink: 0;
}

.perm-item.is-on .perm-item__mark {
  border-color: #0f766e;
  background: #0f766e;
}

.perm-item__body {
  min-width: 0;
  flex: 1;
}

.perm-item__body strong {
  display: inline-flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.4rem;
  font-size: 0.92rem;
  font-weight: 700;
  color: #0f172a;
  line-height: 1.3;
}

.employee-form-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.55rem;
}

.employee-form-actions .btn {
  width: 100%;
  min-height: 44px;
}

.active-toggle {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}
</style>
