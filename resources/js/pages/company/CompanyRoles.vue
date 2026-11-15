<template>
  <div class="page">
    <header class="page-head">
      <div>
        <h1>{{ $t('companyRoles') }}</h1>
        <p class="muted">{{ $t('companyRolesHint') }}</p>
      </div>
      <button class="btn btn-primary" type="button" @click="openCreate">{{ $t('add') }}</button>
    </header>

    <div v-if="loading" class="muted">{{ $t('loading') }}</div>
    <div v-else class="stack">
      <article v-for="role in roles" :key="role.id" class="card role-card">
        <div class="row-between role-card__head">
          <div>
            <strong>{{ role.name }}</strong>
            <p class="muted">{{ $t('companyRoleUsersCount', { n: role.users_count || 0 }) }}</p>
          </div>
          <div class="row-gap">
            <button class="btn btn-outline btn-sm" type="button" @click="openEdit(role)">{{ $t('edit') }}</button>
            <button
              class="btn btn-outline btn-sm btn-danger-outline"
              type="button"
              :disabled="(role.users_count || 0) > 0"
              @click="remove(role)"
            >
              {{ $t('delete') }}
            </button>
          </div>
        </div>
        <ul class="role-perms">
          <li v-for="key in role.permissions || []" :key="`${role.id}-${key}`">
            {{ permissionLabel(key) }}
          </li>
          <li v-if="!(role.permissions || []).length" class="muted">{{ $t('noData') }}</li>
        </ul>
      </article>
      <p v-if="!roles.length" class="muted">{{ $t('noData') }}</p>
    </div>

    <AppModal
      v-model="showForm"
      :title="editingId ? $t('edit') : $t('add')"
      :close-on-backdrop="!saving"
    >
      <form class="form-grid one-col" @submit.prevent="save">
        <div class="field">
          <label>{{ $t('name') }}</label>
          <input v-model.trim="form.name" type="text" required maxlength="120" />
        </div>
        <div class="field">
          <label>{{ $t('rolePermissions') }}</label>
          <p class="field-hint">{{ $t('rolePermissionsHint') }}</p>
          <div class="perm-grid">
            <label v-for="key in sections" :key="key" class="perm-item">
              <input v-model="form.permissions" type="checkbox" :value="key" />
              <span>
                <strong>{{ permissionLabel(key) }}</strong>
                <small>{{ permissionHint(key) }}</small>
              </span>
            </label>
          </div>
        </div>
        <p v-if="error" class="error">{{ error }}</p>
        <div class="actions">
          <button class="btn btn-primary" type="submit" :disabled="saving || !form.permissions.length">
            {{ saving ? $t('loading') : $t('save') }}
          </button>
          <button class="btn btn-outline" type="button" :disabled="saving" @click="showForm = false">
            {{ $t('cancel') }}
          </button>
        </div>
      </form>
    </AppModal>
  </div>
</template>

<script>
import { onMounted, reactive, ref } from 'vue';
import AppModal from '../../components/AppModal.vue';
import { t } from '../../i18n';

export default {
  components: { AppModal },
  setup() {
    const roles = ref([]);
    const sections = ref([]);
    const loading = ref(true);
    const saving = ref(false);
    const showForm = ref(false);
    const editingId = ref(null);
    const error = ref('');
    const form = reactive({ name: '', permissions: [] });

    function permissionLabel(key) {
      const map = {
        dashboard: t('permDashboard'),
        farms: t('permFarms'),
        ponds: t('permPonds'),
        daily_reports: t('permDailyReports'),
        main_reports: t('permMainReports'),
        warehouse: t('permWarehouse'),
        finance: t('permFinance'),
        fish_ops: t('permFishOps'),
        activities: t('permActivities'),
      };
      return map[key] || key;
    }

    function permissionHint(key) {
      const map = {
        dashboard: t('permDashboardHint'),
        farms: t('permFarmsHint'),
        ponds: t('permPondsHint'),
        daily_reports: t('permDailyReportsHint'),
        main_reports: t('permMainReportsHint'),
        warehouse: t('permWarehouseHint'),
        finance: t('permFinanceHint'),
        fish_ops: t('permFishOpsHint'),
        activities: t('permActivitiesHint'),
      };
      return map[key] || '';
    }

    async function load() {
      loading.value = true;
      try {
        const [rolesRes, keysRes] = await Promise.all([
          window.axios.get('/company/roles'),
          window.axios.get('/company/permission-keys'),
        ]);
        roles.value = rolesRes.data;
        sections.value = keysRes.data.sections || [];
      } finally {
        loading.value = false;
      }
    }

    function openCreate() {
      error.value = '';
      editingId.value = null;
      form.name = '';
      form.permissions = [];
      showForm.value = true;
    }

    function openEdit(role) {
      error.value = '';
      editingId.value = role.id;
      form.name = role.name;
      form.permissions = [...(role.permissions || [])];
      showForm.value = true;
    }

    async function save() {
      if (!form.permissions.length) {
        error.value = t('rolePermissionsRequired');
        return;
      }
      saving.value = true;
      error.value = '';
      try {
        const payload = {
          name: form.name,
          permissions: form.permissions,
        };
        if (editingId.value) {
          await window.axios.put(`/company/roles/${editingId.value}`, payload);
        } else {
          await window.axios.post('/company/roles', payload);
        }
        showForm.value = false;
        editingId.value = null;
        form.name = '';
        form.permissions = [];
        await load();
      } catch (e) {
        error.value = e.response?.data?.message
          || Object.values(e.response?.data?.errors || {})?.[0]?.[0]
          || t('errorOccurred');
      } finally {
        saving.value = false;
      }
    }

    async function remove(role) {
      if ((role.users_count || 0) > 0) {
        window.alert(t('roleDeleteHasUsers'));
        return;
      }
      if (!confirm(t('confirmDeleteEntry', { name: role.name }))) return;
      try {
        await window.axios.delete(`/company/roles/${role.id}`);
        await load();
      } catch (e) {
        window.alert(e.response?.data?.message
          || Object.values(e.response?.data?.errors || {})?.[0]?.[0]
          || t('errorOccurred'));
      }
    }

    onMounted(load);
    return {
      roles,
      sections,
      loading,
      saving,
      showForm,
      editingId,
      form,
      error,
      permissionLabel,
      permissionHint,
      openCreate,
      openEdit,
      save,
      remove,
    };
  },
};
</script>

<style scoped>
.role-card__head {
  margin-bottom: 0.55rem;
}

.role-perms {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  list-style: none;
  margin: 0;
  padding: 0;
}

.role-perms li {
  padding: 0.2rem 0.55rem;
  border-radius: 999px;
  background: #eef5f0;
  color: #1b4332;
  font-size: 0.8rem;
  font-weight: 650;
}

.perm-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 0.55rem;
}

.perm-item {
  display: flex;
  gap: 0.55rem;
  align-items: flex-start;
  padding: 0.55rem 0.65rem;
  border: 1px solid rgba(15, 23, 42, 0.08);
  border-radius: 12px;
  font-size: 0.9rem;
}

.perm-item strong {
  display: block;
}

.perm-item small {
  display: block;
  margin-top: 0.15rem;
  color: #64748b;
  font-size: 0.78rem;
  line-height: 1.35;
}

.field-hint {
  margin: 0 0 0.55rem;
  color: #64748b;
  font-size: 0.84rem;
}
</style>
