<template>
  <DirectorShell>
    <div class="page-head page-head--toolbar">
      <strong class="page-head__context">{{ categoryTitle }}</strong>
      <router-link class="btn btn-outline btn-sm" :to="{ name: 'director-finance-overview' }">{{ $t('back') }}</router-link>
    </div>

    <div v-if="loading" class="panel empty">{{ $t('loading') }}</div>
    <div v-else-if="invalidCategory" class="panel empty">{{ $t('noData') }}</div>

    <template v-else>
      <div v-if="financeLocked" class="panel pond-closed-banner">
        <p>{{ $t('financeLockedBanner') }}</p>
      </div>
      <div class="finance-breakdown-head panel">
        <h2>{{ categoryTitle }}</h2>
        <strong>{{ formatMoney(categoryTotal) }}</strong>
      </div>

      <div class="panel finance-breakdown-panel">
        <div v-if="!entries.length" class="empty">{{ $t('noData') }}</div>
        <div v-else class="table-scroll finance-breakdown-scroll">
          <table class="report-table finance-table finance-breakdown-table">
            <thead>
              <tr>
                <th>{{ $t('date') }}</th>
                <th>{{ $t('financeExpenseName') }}</th>
                <th>{{ $t('pond') }}</th>
                <th>{{ $t('financeQuantity') }}</th>
                <th>{{ $t('financeUnitPrice') }}</th>
                <th>{{ $t('amount') }}</th>
                <th>{{ $t('financeEnteredBy') }}</th>
                <th>{{ $t('notes') }}</th>
                <th />
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in entries" :key="`${row.id}-${row.allocated_from_farm ? 'a' : 'd'}`">
                <td>{{ formatDisplayDate(row.expense_date) }}</td>
                <td>
                  <strong>{{ row.title }}</strong>
                  <small v-if="row.allocated_from_farm" class="finance-alloc-badge">{{ allocBadge(row) }}</small>
                </td>
                <td>{{ row.pond?.name || $t('financeScopeAllPondsShort') }}</td>
                <td class="num">{{ row.quantity != null ? formatQty(row.quantity) : '—' }}</td>
                <td class="num">{{ row.unit_price != null ? formatMoney(row.unit_price) : '—' }}</td>
                <td class="num"><strong>{{ formatMoney(row.amount) }}</strong></td>
                <td>{{ row.user?.name || '—' }}</td>
                <td>{{ row.description || '—' }}</td>
                <td class="finance-row-actions">
                  <template v-if="canMutateRow(row)">
                    <router-link
                      class="btn btn-outline btn-sm"
                      :to="{ name: 'director-finance-entry', params: { category: routeCategory }, query: { edit: row.id } }"
                    >
                      {{ $t('edit') }}
                    </router-link>
                    <button
                      class="btn btn-outline btn-sm"
                      type="button"
                      @click="remove(row)"
                    >{{ $t('delete') }}</button>
                  </template>
                  <small v-else-if="isWarehouseRow(row)" class="finance-warehouse-only">
                    {{ $t('financeWarehouseManagedHint') }}
                  </small>
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="5"><strong>{{ $t('financeCategoryTotal') }}</strong></td>
                <td class="num"><strong>{{ formatMoney(categoryTotal) }}</strong></td>
                <td colspan="3" />
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </template>
  </DirectorShell>
</template>

<script>
import { computed, ref, watch } from 'vue';
import { useDirectorFarmId } from '../../utils/directorFarm';
import { useRoute } from 'vue-router';
import DirectorShell from '../../components/DirectorShell.vue';
import auth from '../../auth';
import { showSaved } from '../../flash';
import {
  financeCategoryLabel as labelCategory,
  isValidFinanceCategory,
  isWarehouseFinanceCategory,
  toApiCategory,
} from '../../utils/finance';
import { formatMoney, formatNumber } from '../../utils/number';
import { formatDisplayDate } from '../../utils/date';
import { t } from '../../i18n';

export default {
  components: { DirectorShell },
  setup() {
    const route = useRoute();
    const farmId = useDirectorFarmId();
    const routeCategory = computed(() => route.params.category);
    const canEditFinance = computed(() => auth.can('finance'));
    const loading = ref(true);
    const invalidCategory = ref(false);
    const customSections = ref({});
    const entries = ref([]);
    const categoryTotal = ref(0);
    const financeLocked = ref(false);

    const categoryTitle = computed(() => (
      labelCategory(routeCategory.value, t, customSections.value)
    ));

    function formatQty(value) {
      return formatNumber(value, { maxDecimals: 3 });
    }

    function allocBadge(row) {
      if (row.allocated_from_usage && row.category === 'medicine') return t('financeMedicineShareBadge');
      if (row.allocated_from_usage && row.category === 'feed') return t('financeFeedShareBadge');
      if (row.category === 'medicine') return t('financeMedicineShareBadge');
      if (row.category === 'equipment') return t('financeEquipmentShareBadge');
      return t('financeFeedShareBadge');
    }

    function isWarehouseRow(row) {
      return !!(
        row?.warehouse_item_id
        || row?.allocated_from_farm
        || isWarehouseFinanceCategory(row?.category || routeCategory.value)
      );
    }

    function canMutateRow(row) {
      return canEditFinance.value && !financeLocked.value && !isWarehouseRow(row);
    }

    async function load() {
      loading.value = true;
      invalidCategory.value = false;
      try {
        const overviewRes = await window.axios.get(`/director/farms/${farmId.value}/finance/overview`);
        const sections = overviewRes.data.custom_sections || [];
        customSections.value = Object.fromEntries(
          sections.map((section) => [String(section.id), { name: section.name }]),
        );

        const sectionIds = sections.map((section) => section.id);
        if (!isValidFinanceCategory(routeCategory.value, sectionIds)) {
          invalidCategory.value = true;
          return;
        }

        const expenseParams = { category: toApiCategory(routeCategory.value) };
        if (route.query.pond) expenseParams.pond_id = route.query.pond;

        const entriesRes = await window.axios.get(`/director/farms/${farmId.value}/finance/expenses`, {
          params: expenseParams,
        });
        entries.value = entriesRes.data.data || [];
        categoryTotal.value = entriesRes.data.category_total || 0;
        financeLocked.value = !!entriesRes.data.finance_locked;
      } finally {
        loading.value = false;
      }
    }

    async function remove(row) {
      if (!confirm(t('confirmDeleteEntry', { name: row.title }))) return;
      try {
        await window.axios.delete(`/director/farms/${farmId.value}/finance/expenses/${row.id}`);
        showSaved(t('deleted'));
        await load();
      } catch (e) {
        const errors = e.response?.data?.errors;
        window.alert(errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message || t('noData')));
      }
    }

    watch(() => [routeCategory.value, route.query.pond], load, { immediate: true });

    return {
      farmId,
      routeCategory,
      canEditFinance,
      loading,
      invalidCategory,
      entries,
      categoryTotal,
      financeLocked,
      categoryTitle,
      formatDisplayDate,
      formatMoney,
      formatQty,
      allocBadge,
      isWarehouseRow,
      canMutateRow,
      remove,
    };
  },
};
</script>
