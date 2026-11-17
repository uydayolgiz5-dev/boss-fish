<template>
  <DirectorShell>
    <div class="page-head page-head--toolbar">
      <button
        v-if="scopeReady"
        class="btn btn-outline btn-sm"
        type="button"
        @click="goBackStep"
      >
        {{ $t('back') }}
      </button>
      <router-link
        v-else
        class="btn btn-outline btn-sm"
        :to="{ name: 'director-finance' }"
      >
        {{ $t('back') }}
      </router-link>
    </div>

    <div v-if="loading" class="panel empty">{{ $t('loading') }}</div>

    <template v-else>
      <section class="panel">
        <FinanceQuickEntry
          ref="quickEntry"
          :farm-id="farmId"
          :category-tabs="categoryTabs"
          :selected-category="entryCategory"
          :custom-sections="customSectionsMap"
          :ponds="ponds"
          compact
          fast
          @select-category="selectEntryCategory"
          @saved="onEntrySaved"
          @scope-change="onScopeChange"
        />

        <button
          v-if="scopeReady && !entryCategory"
          class="btn btn-outline btn-sm finance-add-section-tail"
          type="button"
          @click="openSectionModal"
        >
          {{ $t('financeAddSection') }}
        </button>
      </section>

      <details v-if="recentEntries.length" class="panel finance-recent-panel finance-recent-fold">
        <summary class="finance-recent-fold__summary">
          <span>{{ $t('financeRecentEntries') }}</span>
          <em>{{ recentEntries.length }}</em>
        </summary>
        <ul class="finance-recent-list">
          <li v-for="row in recentEntries" :key="row.id" class="finance-recent-row">
            <div class="finance-recent-meta">
              <strong>{{ row.title }}</strong>
              <small>{{ formatDisplayDate(row.expense_date) }} · {{ formatMoney(row.amount) }}</small>
            </div>
          </li>
        </ul>

        <details class="finance-recent-manage">
          <summary>{{ $t('financeRecentManage') }}</summary>
          <p class="finance-recent-manage__hint">{{ $t('financeRecentManageHint') }}</p>
          <ul class="finance-recent-list finance-recent-list--manage">
            <li v-for="row in recentEntries" :key="`del-${row.id}`" class="finance-recent-row">
              <div class="finance-recent-meta">
                <strong>{{ row.title }}</strong>
                <small>{{ formatDisplayDate(row.expense_date) }} · {{ formatMoney(row.amount) }}</small>
              </div>
              <button
                class="btn btn-outline btn-sm btn-danger-outline"
                type="button"
                :disabled="deletingId === row.id"
                @click="removeEntry(row)"
              >
                {{ $t('delete') }}
              </button>
            </li>
          </ul>
        </details>
      </details>
    </template>

    <AppModal v-model="sectionModal" :title="$t('financeAddSection')">
      <form id="finance-section-create-form" class="form-grid one-col" @submit.prevent="createSection">
        <div class="field">
          <label>{{ $t('financeSectionName') }}</label>
          <input
            v-model.trim="sectionName"
            type="text"
            maxlength="120"
            required
            autocomplete="off"
            :placeholder="$t('financeSectionNamePlaceholder')"
          />
          <small class="field-hint">{{ $t('financeSectionNameHint') }}</small>
        </div>
        <p v-if="sectionError" class="error">{{ sectionError }}</p>
      </form>
      <template #footer>
        <div class="actions">
          <button
            class="btn btn-primary"
            type="submit"
            form="finance-section-create-form"
            :disabled="sectionSaving"
          >
            {{ $t('save') }}
          </button>
          <button class="btn btn-outline" type="button" @click="sectionModal = false">
            {{ $t('cancel') }}
          </button>
        </div>
      </template>
    </AppModal>
  </DirectorShell>
</template>

<script>
import { computed, onMounted, ref, watch } from 'vue';
import { ensureDirectorFarmId, useDirectorFarmId } from '../../utils/directorFarm';
import { useRoute, useRouter } from 'vue-router';
import AppModal from '../../components/AppModal.vue';
import DirectorShell from '../../components/DirectorShell.vue';
import FinanceQuickEntry from '../../components/FinanceQuickEntry.vue';
import { showSaved } from '../../flash';
import {
  FINANCE_ENTRY_CATEGORIES,
  financeCategoryHint,
  financeCategoryIcon,
  financeCategoryLabel as labelCategory,
  isFinanceManualEntryCategory,
  isWarehouseFinanceCategory,
  toApiCategory,
  toRouteCategory,
} from '../../utils/finance';
import { formatMoney } from '../../utils/number';
import { formatDisplayDate } from '../../utils/date';
import { t } from '../../i18n';

const WAREHOUSE_CATEGORIES = new Set(['feed', 'medicine', 'equipment']);

export default {
  components: { AppModal, DirectorShell, FinanceQuickEntry },
  setup() {
    const route = useRoute();
    const router = useRouter();
    const farmId = useDirectorFarmId();
    const loading = ref(true);
    const ponds = ref([]);
    const entryCategory = ref('');
    const scopeReady = ref(false);
    const overview = ref({
      categories: {},
      custom_sections: [],
      ponds: [],
    });
    const sectionModal = ref(false);
    const sectionName = ref('');
    const sectionError = ref('');
    const sectionSaving = ref(false);
    const recentEntries = ref([]);
    const deletingId = ref(null);
    const quickEntry = ref(null);

    function goBackStep() {
      // Forma → bo‘lim tanlash; bo‘lim → kol/umumiy savol
      if (entryCategory.value) {
        entryCategory.value = '';
        router.replace({ name: 'director-finance-kirim', query: {} });
        return;
      }
      quickEntry.value?.restartQuiz?.();
      scopeReady.value = false;
      router.replace({ name: 'director-finance-kirim', query: {} });
    }

    async function loadRecent() {
      if (!farmId.value) {
        recentEntries.value = [];
        return;
      }
      // Bo‘lim tanlanmaguncha ro‘yxatni ko‘rsatmaymiz — chalkashlik bo‘lmasin
      if (!entryCategory.value) {
        recentEntries.value = [];
        return;
      }
      try {
        const { data } = await window.axios.get(`/director/farms/${farmId.value}/finance/expenses`, {
          params: { category: toApiCategory(entryCategory.value) },
        });
        recentEntries.value = (data.data || [])
          .filter((row) => !WAREHOUSE_CATEGORIES.has(row.category) && !row.allocated_from_farm)
          .slice(0, 8);
      } catch (e) {
        recentEntries.value = [];
      }
    }

    async function removeEntry(row) {
      if (!confirm(t('financeConfirmDeleteExpense', { name: row.title }))) return;
      if (!confirm(t('financeConfirmDeleteExpenseAgain'))) return;
      deletingId.value = row.id;
      try {
        await window.axios.delete(`/director/farms/${farmId.value}/finance/expenses/${row.id}`);
        showSaved(t('deleted'));
        await loadRecent();
      } catch (e) {
        const errors = e.response?.data?.errors;
        window.alert(errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message || t('noData')));
      } finally {
        deletingId.value = null;
      }
    }

    const customSectionsMap = computed(() => Object.fromEntries(
      (overview.value.custom_sections || []).map((section) => [
        String(section.id),
        { name: section.name },
      ]),
    ));

    const categoryTabs = computed(() => {
      const fixed = FINANCE_ENTRY_CATEGORIES.map((category) => ({
        routeKey: category,
        label: labelCategory(category, t),
        icon: financeCategoryIcon(category),
        hint: financeCategoryHint(category, t),
        warehouse: false,
        section: null,
      }));

      const custom = (overview.value.custom_sections || []).map((section) => ({
        routeKey: toRouteCategory(section.category),
        label: section.name,
        icon: financeCategoryIcon(section.category),
        hint: t('financeCatCustomHint'),
        warehouse: false,
        section,
      }));

      return [...fixed, ...custom];
    });

    function selectEntryCategory(routeKey) {
      entryCategory.value = routeKey;
      router.replace({
        name: 'director-finance-kirim',
        query: { entry: routeKey },
      });
    }

    async function load() {
      loading.value = true;
      try {
        const id = await ensureDirectorFarmId();
        if (!id) {
          overview.value = { categories: {}, custom_sections: [], ponds: [] };
          ponds.value = [];
          return;
        }
        const overviewRes = await window.axios.get(`/director/farms/${id}/finance/overview`);
        overview.value = overviewRes.data;
        ponds.value = overviewRes.data.ponds || [];

        if (entryCategory.value) {
          const sectionIds = (overview.value.custom_sections || []).map((section) => section.id);
          if (
            isWarehouseFinanceCategory(entryCategory.value)
            || !isFinanceManualEntryCategory(entryCategory.value, sectionIds)
          ) {
            entryCategory.value = '';
            router.replace({ name: 'director-finance-kirim', query: {} });
          }
        }
        await loadRecent();
      } catch (e) {
        overview.value = { categories: {}, custom_sections: [], ponds: [] };
        ponds.value = [];
        recentEntries.value = [];
      } finally {
        loading.value = false;
      }
    }

    async function onEntrySaved() {
      const overviewRes = await window.axios.get(`/director/farms/${farmId.value}/finance/overview`);
      overview.value = overviewRes.data;
      ponds.value = overviewRes.data.ponds || [];
      await loadRecent();
    }

    function onScopeChange(mode) {
      scopeReady.value = !!mode;
      if (!mode) return;
      const queryEntry = route.query.entry;
      const sectionIds = (overview.value.custom_sections || []).map((section) => section.id);
      if (queryEntry && isFinanceManualEntryCategory(String(queryEntry), sectionIds)) {
        entryCategory.value = String(queryEntry);
      }
    }

    function openSectionModal() {
      sectionName.value = '';
      sectionError.value = '';
      sectionModal.value = true;
    }

    async function createSection() {
      sectionError.value = '';
      sectionSaving.value = true;
      try {
        const { data } = await window.axios.post(`/director/farms/${farmId.value}/finance/sections`, {
          name: sectionName.value,
        });
        sectionModal.value = false;
        showSaved(t('saved'));
        await load();
        const routeKey = toRouteCategory(data.category);
        entryCategory.value = routeKey;
        router.replace({
          name: 'director-finance-kirim',
          query: { entry: routeKey },
        });
      } catch (e) {
        const errors = e.response?.data?.errors;
        sectionError.value = errors
          ? Object.values(errors).flat().join(' ')
          : (e.response?.data?.message || t('noData'));
      } finally {
        sectionSaving.value = false;
      }
    }

    watch(() => route.query.entry, (value) => {
      if (!value || loading.value || !scopeReady.value) {
        if (!value) entryCategory.value = '';
        return;
      }
      const sectionIds = (overview.value.custom_sections || []).map((section) => section.id);
      if (isFinanceManualEntryCategory(String(value), sectionIds)) {
        entryCategory.value = String(value);
      }
    });

    watch(entryCategory, () => {
      loadRecent();
    });

    onMounted(load);

    return {
      farmId,
      loading,
      ponds,
      categoryTabs,
      customSectionsMap,
      entryCategory,
      scopeReady,
      quickEntry,
      goBackStep,
      onScopeChange,
      sectionModal,
      sectionName,
      sectionError,
      sectionSaving,
      selectEntryCategory,
      onEntrySaved,
      openSectionModal,
      createSection,
      recentEntries,
      deletingId,
      formatDisplayDate,
      formatMoney,
      removeEntry,
    };
  },
};
</script>

<style scoped>
.finance-add-section-tail {
  margin-top: 1.25rem;
}

.finance-recent-panel {
  margin-top: 1rem;
}

.finance-recent-fold {
  padding: 0.65rem 0.85rem;
}

.finance-recent-fold__summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  cursor: pointer;
  list-style: none;
  user-select: none;
  font-size: 0.95rem;
  font-weight: 750;
  color: #475569;
}

.finance-recent-fold__summary::-webkit-details-marker {
  display: none;
}

.finance-recent-fold__summary::before {
  content: '▸';
  margin-right: 0.35rem;
  color: #94a3b8;
  font-size: 0.8rem;
}

.finance-recent-fold[open] > .finance-recent-fold__summary {
  margin-bottom: 0.75rem;
  padding-bottom: 0.55rem;
  border-bottom: 1px solid rgba(15, 23, 42, 0.08);
  color: #0f172a;
}

.finance-recent-fold[open] > .finance-recent-fold__summary::before {
  content: '▾';
}

.finance-recent-fold__summary em {
  font-style: normal;
  min-width: 1.5rem;
  padding: 0.1rem 0.45rem;
  border-radius: 999px;
  background: #e2e8f0;
  color: #475569;
  font-size: 0.78rem;
  font-weight: 800;
  text-align: center;
}

.finance-recent-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.55rem;
}

.finance-recent-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.55rem 0;
  border-top: 1px solid rgba(15, 23, 42, 0.08);
}

.finance-recent-row:first-child {
  border-top: 0;
  padding-top: 0;
}

.finance-recent-meta {
  display: grid;
  gap: 0.15rem;
  min-width: 0;
}

.finance-recent-meta strong {
  font-size: 0.95rem;
}

.finance-recent-meta small {
  color: #64748b;
}

.finance-recent-manage {
  margin-top: 0.85rem;
  padding-top: 0.65rem;
  border-top: 1px dashed rgba(15, 23, 42, 0.12);
}

.finance-recent-manage > summary {
  cursor: pointer;
  list-style: none;
  font-size: 0.82rem;
  font-weight: 650;
  color: #94a3b8;
  user-select: none;
}

.finance-recent-manage > summary::-webkit-details-marker {
  display: none;
}

.finance-recent-manage > summary::before {
  content: '▸ ';
  font-size: 0.75rem;
}

.finance-recent-manage[open] > summary {
  color: #64748b;
  margin-bottom: 0.45rem;
}

.finance-recent-manage[open] > summary::before {
  content: '▾ ';
}

.finance-recent-manage__hint {
  margin: 0 0 0.65rem;
  font-size: 0.8rem;
  line-height: 1.35;
  color: #94a3b8;
}

.finance-recent-list--manage {
  margin-top: 0.25rem;
}
</style>
