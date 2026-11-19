<template>
  <div class="owner-venger venger-readable vfp">
    <OwnerVengerNav />

    <div class="page-head">
      <div>
        <h1>{{ $t('vengerFeedPlanTitle') }}</h1>
        <p class="muted">{{ $t('vengerFeedPlanHint') }}</p>
      </div>
    </div>

    <div v-if="loading" class="panel empty">{{ $t('loading') }}</div>
    <div v-else-if="error" class="panel empty">{{ error }}</div>

    <template v-else>
      <section class="panel vfp-years">
        <div class="vfp-years__head">
          <h2>{{ $t('vengerFeedPlanYears') }}</h2>
          <form class="vfp-year-form" @submit.prevent="createPlan">
            <input
              v-model.number="newYear"
              type="number"
              min="2020"
              max="2100"
              required
              :placeholder="$t('vengerFeedPlanYearPlaceholder')"
            />
            <button class="btn btn-primary" type="submit" :disabled="creating">
              {{ creating ? $t('loading') : $t('vengerFeedPlanAddYear') }}
            </button>
          </form>
        </div>
        <div v-if="!plans.length" class="empty">{{ $t('vengerFeedPlanNoYears') }}</div>
        <div v-else class="vfp-year-tabs">
          <button
            v-for="p in plans"
            :key="p.id"
            type="button"
            class="vfp-year-tab"
            :class="{ on: plan?.id === p.id }"
            @click="selectPlan(p.id)"
          >
            <strong>{{ p.year }}</strong>
            <small>{{ p.rows_count }} {{ $t('vengerFeedPlanClientsShort') }}</small>
          </button>
        </div>
      </section>

      <template v-if="plan">
        <section class="panel vfp-report">
          <div class="vfp-report__head">
            <div>
              <p class="vfp-report__eyebrow">VENGER CARP</p>
              <h2>{{ $t('vengerFeedPlanOfficialTitle', { year: plan.year }) }}</h2>
              <p class="muted">{{ $t('vengerFeedPlanSummary', { year: plan.year }) }}</p>
            </div>
            <button
              class="btn btn-outline"
              type="button"
              :disabled="exportingExcel || !plan.rows.length"
              @click="exportExcel"
            >
              {{ exportingExcel ? $t('exportingExcel') : $t('exportExcel') }}
            </button>
          </div>
          <div class="vfp-totals__grid">
            <div><span>{{ $t('vengerFeedPlanTotalClients') }}</span><strong>{{ plan.totals.clients_count }}</strong></div>
            <div><span>{{ $t('vengerFeedPlanTotalArea') }}</span><strong>{{ fmtNum(plan.totals.pond_area_ha, 2) }}</strong></div>
            <div><span>{{ $t('vengerFeedPlanTotalLastFeed') }}</span><strong>{{ fmtNum(plan.totals.last_year_feed_kg, 2) }}</strong></div>
            <div><span>{{ $t('vengerFeedPlanTotalCarp') }}</span><strong>{{ fmtNum(plan.totals.next_year_carp_count, 0) }}</strong></div>
            <div><span>{{ $t('vengerFeedPlanTotalExtruder') }}</span><strong>{{ fmtNum(plan.totals.next_year_extruder_kg, 2) }}</strong></div>
            <div><span>{{ $t('vengerFeedPlanTotalGranule') }}</span><strong>{{ fmtNum(plan.totals.next_year_granule_kg, 2) }}</strong></div>
          </div>
        </section>

        <section v-if="comparison.length" class="panel vfp-compare">
          <h2>{{ $t('vengerFeedPlanCompareTitle') }}</h2>
          <p class="field-hint">{{ $t('vengerFeedPlanCompareHint') }}</p>
          <div v-if="comparison.length < 2" class="empty">{{ $t('vengerFeedPlanCompareEmpty') }}</div>
          <div v-else class="table-scroll">
            <table class="vfp-table vfp-table--compare">
              <thead>
                <tr>
                  <th>{{ $t('vengerFeedPlanCompareYear') }}</th>
                  <th>{{ $t('vengerFeedPlanCompareClients') }}</th>
                  <th>{{ $t('vengerFeedPlanCompareArea') }}</th>
                  <th>{{ $t('vengerFeedPlanCompareCarp') }}</th>
                  <th>{{ $t('vengerFeedPlanCompareExtruder') }}</th>
                  <th>{{ $t('vengerFeedPlanCompareGranule') }}</th>
                  <th>{{ $t('vengerFeedPlanCompareTotalFeed') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in comparison" :key="row.year">
                  <td>
                    <strong>{{ row.year }}</strong>
                    <small v-if="row.vs_year" class="vfp-vs">{{ $t('vengerFeedPlanCompareVs', { year: row.vs_year }) }}</small>
                    <small v-else class="vfp-vs">{{ $t('vengerFeedPlanCompareFirst') }}</small>
                  </td>
                  <td class="num">
                    {{ fmtNum(row.totals.clients_count, 0) }}
                    <span class="vfp-growth" :class="growthClass(row.growth.clients_count)">{{ growthLabel(row.growth.clients_count) }}</span>
                  </td>
                  <td class="num">
                    {{ fmtNum(row.totals.pond_area_ha, 2) }}
                    <span class="vfp-growth" :class="growthClass(row.growth.pond_area_ha)">{{ growthLabel(row.growth.pond_area_ha) }}</span>
                  </td>
                  <td class="num">
                    {{ fmtNum(row.totals.next_year_carp_count, 0) }}
                    <span class="vfp-growth" :class="growthClass(row.growth.next_year_carp_count)">{{ growthLabel(row.growth.next_year_carp_count) }}</span>
                  </td>
                  <td class="num">
                    {{ fmtNum(row.totals.next_year_extruder_kg, 2) }} t
                    <span class="vfp-growth" :class="growthClass(row.growth.next_year_extruder_kg)">{{ growthLabel(row.growth.next_year_extruder_kg) }}</span>
                  </td>
                  <td class="num">
                    {{ fmtNum(row.totals.next_year_granule_kg, 2) }} t
                    <span class="vfp-growth" :class="growthClass(row.growth.next_year_granule_kg)">{{ growthLabel(row.growth.next_year_granule_kg) }}</span>
                  </td>
                  <td class="num">
                    {{ fmtNum((row.totals.next_year_extruder_kg || 0) + (row.totals.next_year_granule_kg || 0), 2) }} t
                    <span class="vfp-growth" :class="growthClass(row.growth.total_feed_plan_t)">{{ growthLabel(row.growth.total_feed_plan_t) }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <section class="panel vfp-add">
          <h2>{{ $t('vengerFeedPlanAddRow') }}</h2>
          <p class="field-hint">{{ $t('vengerFeedPlanAddRowHint') }}</p>

          <div v-if="clients.length" class="field vfp-pick">
            <label>{{ $t('vengerFeedPlanPickClient') }}</label>
            <select v-model="pickClientId" @change="applyPickedClient">
              <option value="">{{ $t('vengerFeedPlanPickClientEmpty') }}</option>
              <option v-for="c in clients" :key="c.id" :value="String(c.id)">
                {{ c.name }} — {{ formatPhone(c.phone) }}
              </option>
            </select>
          </div>

          <form class="vfp-form-grid" @submit.prevent="addRow">
            <div class="field">
              <label>{{ $t('vengerFeedPlanColName') }} *</label>
              <input v-model="draft.client_name" required maxlength="255" />
            </div>
            <div class="field">
              <label>{{ $t('phone') }} *</label>
              <PhoneInput v-model="draft.phone" required />
            </div>
            <div class="field">
              <label>{{ $t('phone2Optional') }}</label>
              <PhoneInput v-model="draft.phone2" />
            </div>
            <div class="field">
              <label>{{ $t('vengerFeedPlanColLocation') }}</label>
              <input v-model="draft.location_text" maxlength="500" :placeholder="$t('vengerFeedPlanLocationPh')" />
            </div>
            <div class="field">
              <label>{{ $t('vengerFeedPlanColArea') }}</label>
              <FormattedNumberInput v-model="draft.pond_area_ha" :max-decimals="3" />
            </div>
            <div class="field">
              <label>{{ $t('vengerFeedPlanColLastFeed') }}</label>
              <FormattedNumberInput v-model="draft.last_year_feed_kg" :max-decimals="2" />
            </div>
            <div class="field">
              <label>{{ $t('vengerFeedPlanColRating') }}</label>
              <input v-model.number="draft.rating_10" type="number" min="1" max="10" />
            </div>
            <div class="field form-wide">
              <label>{{ $t('vengerFeedPlanColFeedback') }}</label>
              <input v-model="draft.feedback" maxlength="5000" />
            </div>
            <div class="field">
              <label>{{ $t('vengerFeedPlanColCarp') }}</label>
              <FormattedNumberInput v-model="draft.next_year_carp_count" :max-decimals="0" />
            </div>
            <div class="field">
              <label>{{ $t('vengerFeedPlanColExtruder') }}</label>
              <FormattedNumberInput v-model="draft.next_year_extruder_kg" :max-decimals="2" />
            </div>
            <div class="field">
              <label>{{ $t('vengerFeedPlanColGranule') }}</label>
              <FormattedNumberInput v-model="draft.next_year_granule_kg" :max-decimals="2" />
            </div>
            <div class="actions form-wide">
              <button class="btn btn-primary" type="submit" :disabled="savingRow">
                {{ savingRow ? $t('loading') : $t('save') }}
              </button>
            </div>
            <p v-if="rowError" class="error form-wide">{{ rowError }}</p>
          </form>
        </section>

        <section class="panel vfp-table-panel">
          <h2>{{ $t('vengerFeedPlanTableTitle', { year: plan.year }) }}</h2>
          <div v-if="!plan.rows.length" class="empty">{{ $t('vengerFeedPlanEmptyRows') }}</div>
          <div v-else class="table-scroll">
            <table class="vfp-table">
              <thead>
                <tr>
                  <th>№</th>
                  <th>{{ $t('vengerFeedPlanColName') }}</th>
                  <th>{{ $t('phone') }}</th>
                  <th>{{ $t('phone2Optional') }}</th>
                  <th>{{ $t('vengerFeedPlanColLocation') }}</th>
                  <th class="num">{{ $t('vengerFeedPlanColArea') }}</th>
                  <th class="num">{{ $t('vengerFeedPlanColLastFeed') }}</th>
                  <th class="num">{{ $t('vengerFeedPlanColRating') }}</th>
                  <th>{{ $t('vengerFeedPlanColFeedback') }}</th>
                  <th class="num">{{ $t('vengerFeedPlanColCarp') }}</th>
                  <th class="num">{{ $t('vengerFeedPlanColExtruder') }}</th>
                  <th class="num">{{ $t('vengerFeedPlanColGranule') }}</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, idx) in plan.rows" :key="row.id">
                  <td>{{ idx + 1 }}</td>
                  <td>
                    <input
                      class="vfp-cell"
                      :value="row.client_name"
                      @change="patchRow(row, { client_name: $event.target.value })"
                    />
                  </td>
                  <td class="mono">{{ formatPhone(row.phone) }}</td>
                  <td>
                    <input
                      class="vfp-cell mono"
                      :value="row.phone2 || ''"
                      :placeholder="'+998'"
                      @change="patchPhone2(row, $event.target.value)"
                    />
                  </td>
                  <td>
                    <input
                      class="vfp-cell"
                      :value="row.location_text || ''"
                      @change="patchRow(row, { location_text: $event.target.value })"
                    />
                  </td>
                  <td class="num">
                    <input
                      class="vfp-cell vfp-cell--num"
                      type="number"
                      step="0.001"
                      :value="row.pond_area_ha ?? ''"
                      @change="patchRow(row, { pond_area_ha: numOrNull($event.target.value) })"
                    />
                  </td>
                  <td class="num">
                    <input
                      class="vfp-cell vfp-cell--num"
                      type="number"
                      step="0.01"
                      :value="row.last_year_feed_kg ?? ''"
                      @change="patchRow(row, { last_year_feed_kg: numOrNull($event.target.value) })"
                    />
                  </td>
                  <td class="num">
                    <input
                      class="vfp-cell vfp-cell--num"
                      type="number"
                      min="1"
                      max="10"
                      :value="row.rating_10 ?? ''"
                      @change="patchRow(row, { rating_10: intOrNull($event.target.value) })"
                    />
                  </td>
                  <td>
                    <input
                      class="vfp-cell"
                      :value="row.feedback || ''"
                      @change="patchRow(row, { feedback: $event.target.value })"
                    />
                  </td>
                  <td class="num">
                    <input
                      class="vfp-cell vfp-cell--num"
                      type="number"
                      step="1"
                      :value="row.next_year_carp_count ?? ''"
                      @change="patchRow(row, { next_year_carp_count: intOrNull($event.target.value) })"
                    />
                  </td>
                  <td class="num">
                    <input
                      class="vfp-cell vfp-cell--num"
                      type="number"
                      step="0.01"
                      :value="row.next_year_extruder_kg ?? ''"
                      @change="patchRow(row, { next_year_extruder_kg: numOrNull($event.target.value) })"
                    />
                  </td>
                  <td class="num">
                    <input
                      class="vfp-cell vfp-cell--num"
                      type="number"
                      step="0.01"
                      :value="row.next_year_granule_kg ?? ''"
                      @change="patchRow(row, { next_year_granule_kg: numOrNull($event.target.value) })"
                    />
                  </td>
                  <td>
                    <button class="btn btn-outline btn-sm" type="button" @click="removeRow(row)">
                      {{ $t('delete') }}
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </template>
    </template>
  </div>
</template>

<script>
import { computed, onMounted, reactive, ref } from 'vue';
import OwnerVengerNav from '../../components/OwnerVengerNav.vue';
import PhoneInput from '../../components/PhoneInput.vue';
import FormattedNumberInput from '../../components/FormattedNumberInput.vue';
import auth from '../../auth';
import { showSaved } from '../../flash';
import { formatPhoneDisplay, isCompletePhone } from '../../utils/phone';
import { formatNumber } from '../../utils/number';
import {
  downloadExcelWorkbook,
  excelBlankRow,
  excelCell,
  excelDateStamp,
  excelMoney,
} from '../../utils/exportExcel';
import { t } from '../../i18n';

function emptyDraft() {
  return {
    client_name: '',
    phone: '+998',
    phone2: '+998',
    location_text: '',
    pond_area_ha: '',
    last_year_feed_kg: '',
    rating_10: '',
    feedback: '',
    next_year_carp_count: '',
    next_year_extruder_kg: '',
    next_year_granule_kg: '',
    venger_client_id: null,
  };
}

export default {
  components: { OwnerVengerNav, PhoneInput, FormattedNumberInput },
  setup() {
    const apiBase = computed(() => (auth.isOwner() ? '/owner/venger' : '/venger'));
    const loading = ref(true);
    const error = ref('');
    const plans = ref([]);
    const comparison = ref([]);
    const plan = ref(null);
    const clients = ref([]);
    const newYear = ref(new Date().getFullYear() + 1);
    const creating = ref(false);
    const savingRow = ref(false);
    const exportingExcel = ref(false);
    const rowError = ref('');
    const draft = reactive(emptyDraft());
    const pickClientId = ref('');

    function formatPhone(phone) {
      return formatPhoneDisplay(phone || '');
    }

    function fmtNum(v, maxDecimals = 0) {
      if (v == null || v === '') return '0';
      return formatNumber(v, { maxDecimals });
    }

    function numOrNull(v) {
      if (v === '' || v == null) return null;
      const n = Number(v);
      return Number.isFinite(n) ? n : null;
    }

    function intOrNull(v) {
      const n = numOrNull(v);
      return n == null ? null : Math.round(n);
    }

    async function loadPlans() {
      loading.value = true;
      error.value = '';
      try {
        const [{ data: planData }, { data: clientData }] = await Promise.all([
          window.axios.get(`${apiBase.value}/feed-plans`),
          window.axios.get(`${apiBase.value}/clients`),
        ]);
        plans.value = planData.plans || [];
        comparison.value = planData.comparison || [];
        clients.value = clientData.clients || [];
        if (plans.value.length) {
          await selectPlan(plans.value[0].id);
        } else {
          plan.value = null;
        }
      } catch (e) {
        error.value = e.response?.data?.message || t('noData');
      } finally {
        loading.value = false;
      }
    }

    async function selectPlan(id) {
      const { data } = await window.axios.get(`${apiBase.value}/feed-plans/${id}`);
      plan.value = data;
      const list = plans.value.find((p) => p.id === id);
      if (list) list.rows_count = data.totals?.clients_count ?? data.rows?.length ?? 0;
    }

    async function createPlan() {
      creating.value = true;
      rowError.value = '';
      try {
        const { data } = await window.axios.post(`${apiBase.value}/feed-plans`, {
          year: Number(newYear.value),
          title: t('vengerFeedPlanDefaultTitle', { year: newYear.value }),
        });
        showSaved(t('saved'));
        await refreshPlansMeta();
        await selectPlan(data.id);
      } catch (e) {
        const errors = e.response?.data?.errors;
        window.alert(errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message || t('noData')));
      } finally {
        creating.value = false;
      }
    }

    function applyPickedClient() {
      const c = clients.value.find((x) => String(x.id) === String(pickClientId.value));
      if (!c) {
        draft.venger_client_id = null;
        return;
      }
      draft.venger_client_id = c.id;
      draft.client_name = c.name || `${c.first_name || ''} ${c.last_name || ''}`.trim();
      draft.phone = c.phone || '+998';
      draft.phone2 = c.phone2 || '+998';
      draft.location_text = [c.region, c.district].filter(Boolean).join(', ') || c.address || '';
    }

    function resetDraft() {
      Object.assign(draft, emptyDraft());
      pickClientId.value = '';
    }

    async function addRow() {
      rowError.value = '';
      if (!draft.client_name.trim()) {
        rowError.value = t('vengerFeedPlanColName');
        return;
      }
      if (!isCompletePhone(draft.phone)) {
        rowError.value = t('phoneIncompleteFull');
        return;
      }
      const phone2 = isCompletePhone(draft.phone2) ? draft.phone2 : null;
      if (draft.phone2 && draft.phone2 !== '+998' && !phone2) {
        rowError.value = t('phoneIncompleteFull');
        return;
      }
      savingRow.value = true;
      try {
        const payload = {
          client_name: draft.client_name.trim(),
          phone: draft.phone,
          phone2,
          location_text: draft.location_text || null,
          pond_area_ha: numOrNull(draft.pond_area_ha),
          last_year_feed_kg: numOrNull(draft.last_year_feed_kg),
          rating_10: intOrNull(draft.rating_10),
          feedback: draft.feedback || null,
          next_year_carp_count: intOrNull(draft.next_year_carp_count),
          next_year_extruder_kg: numOrNull(draft.next_year_extruder_kg),
          next_year_granule_kg: numOrNull(draft.next_year_granule_kg),
        };
        if (draft.venger_client_id) payload.venger_client_id = draft.venger_client_id;

        await window.axios.post(`${apiBase.value}/feed-plans/${plan.value.id}/rows`, payload);
        showSaved(t('saved'));
        resetDraft();
        await refreshPlansMeta();
        await selectPlan(plan.value.id);
        const { data: clientData } = await window.axios.get(`${apiBase.value}/clients`);
        clients.value = clientData.clients || [];
      } catch (e) {
        const errors = e.response?.data?.errors;
        rowError.value = errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message || t('noData'));
      } finally {
        savingRow.value = false;
      }
    }

    async function patchRow(row, patch) {
      try {
        await window.axios.put(`${apiBase.value}/feed-plans/${plan.value.id}/rows/${row.id}`, patch);
        await refreshPlansMeta();
        await selectPlan(plan.value.id);
      } catch (e) {
        const errors = e.response?.data?.errors;
        window.alert(errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message || t('noData')));
        await selectPlan(plan.value.id);
      }
    }

    async function patchPhone2(row, raw) {
      const value = String(raw || '').trim();
      if (!value || value === '+998') {
        await patchRow(row, { phone2: null });
        return;
      }
      if (!isCompletePhone(value)) {
        window.alert(t('phoneIncompleteFull'));
        await selectPlan(plan.value.id);
        return;
      }
      await patchRow(row, { phone2: value });
    }

    async function refreshPlansMeta() {
      const { data } = await window.axios.get(`${apiBase.value}/feed-plans`);
      plans.value = data.plans || [];
      comparison.value = data.comparison || [];
    }

    function growthLabel(pct) {
      if (pct == null) return '—';
      const n = Number(pct);
      if (!Number.isFinite(n)) return '—';
      const sign = n > 0 ? '+' : '';
      return `${sign}${n}%`;
    }

    function growthClass(pct) {
      if (pct == null) return 'is-flat';
      const n = Number(pct);
      if (n > 0) return 'is-up';
      if (n < 0) return 'is-down';
      return 'is-flat';
    }

    async function removeRow(row) {
      if (!confirm(t('vengerFeedPlanConfirmDelete', { name: row.client_name }))) return;
      await window.axios.delete(`${apiBase.value}/feed-plans/${plan.value.id}/rows/${row.id}`);
      showSaved(t('deleted'));
      await refreshPlansMeta();
      await selectPlan(plan.value.id);
    }

    function exportExcel() {
      if (!plan.value) return;
      exportingExcel.value = true;
      try {
        const year = plan.value.year;
        const totals = plan.value.totals || {};
        const stamp = excelDateStamp();
        const summaryRows = [
          [excelCell(t('vengerFeedPlanOfficialTitle', { year }), 'title', 3)],
          excelBlankRow(),
          [excelCell(t('financeExcelGeneratedAt'), 'metaLabel'), excelCell(stamp, 'metaValue', 2)],
          [excelCell(t('vengerFeedPlanExcelNote'), 'metaValue', 3)],
          excelBlankRow(),
          [excelCell(t('vengerFeedPlanExcelSheetSummary'), 'section', 3)],
          [excelCell(t('financeExcelMetric'), 'header'), excelCell(t('amount'), 'header')],
          [excelCell(t('vengerFeedPlanTotalClients'), 'text'), excelCell(Number(totals.clients_count || 0), 'money')],
          [excelCell(t('vengerFeedPlanTotalArea'), 'text'), excelCell(excelMoney(totals.pond_area_ha), 'money')],
          [excelCell(t('vengerFeedPlanTotalLastFeed'), 'text'), excelCell(excelMoney(totals.last_year_feed_kg), 'money')],
          [excelCell(t('vengerFeedPlanTotalCarp'), 'text'), excelCell(Number(totals.next_year_carp_count || 0), 'money')],
          [excelCell(t('vengerFeedPlanTotalExtruder'), 'text'), excelCell(excelMoney(totals.next_year_extruder_kg), 'money')],
          [excelCell(t('vengerFeedPlanTotalGranule'), 'text'), excelCell(excelMoney(totals.next_year_granule_kg), 'money')],
        ];

        const clientHeader = [
          excelCell('№', 'header'),
          excelCell(t('vengerFeedPlanColName'), 'header'),
          excelCell(t('phone'), 'header'),
          excelCell(t('vengerFeedPlanColLocation'), 'header'),
          excelCell(t('vengerFeedPlanColArea'), 'header'),
          excelCell(t('vengerFeedPlanColLastFeed'), 'header'),
          excelCell(t('vengerFeedPlanColRating'), 'header'),
          excelCell(t('vengerFeedPlanColFeedback'), 'header'),
          excelCell(t('vengerFeedPlanColCarp'), 'header'),
          excelCell(t('vengerFeedPlanColExtruder'), 'header'),
          excelCell(t('vengerFeedPlanColGranule'), 'header'),
        ];

        const clientRows = [
          [excelCell(t('vengerFeedPlanOfficialTitle', { year }), 'title', 10)],
          excelBlankRow(),
          [excelCell(t('vengerFeedPlanTableTitle', { year }), 'section', 10)],
          clientHeader,
          ...(plan.value.rows || []).map((row, idx) => [
            excelCell(idx + 1, 'text'),
            excelCell(row.client_name || '', 'text'),
            excelCell(formatPhone(row.phone), 'text'),
            excelCell(row.location_text || '', 'text'),
            excelCell(row.pond_area_ha == null ? '' : excelMoney(row.pond_area_ha), 'money'),
            excelCell(row.last_year_feed_kg == null ? '' : excelMoney(row.last_year_feed_kg), 'money'),
            excelCell(row.rating_10 == null ? '' : Number(row.rating_10), 'text'),
            excelCell(row.feedback || '', 'text'),
            excelCell(row.next_year_carp_count == null ? '' : Number(row.next_year_carp_count), 'money'),
            excelCell(row.next_year_extruder_kg == null ? '' : excelMoney(row.next_year_extruder_kg), 'money'),
            excelCell(row.next_year_granule_kg == null ? '' : excelMoney(row.next_year_granule_kg), 'money'),
          ]),
        ];

        downloadExcelWorkbook(t('vengerFeedPlanExcelFile', { year }), [
          {
            name: t('vengerFeedPlanExcelSheetClients'),
            columns: [36, 140, 110, 140, 90, 100, 90, 160, 100, 100, 100],
            freezeRow: 4,
            rows: clientRows,
          },
          {
            name: t('vengerFeedPlanExcelSheetSummary'),
            columns: [280, 100, 100, 100],
            rows: summaryRows,
          },
        ]);
      } finally {
        exportingExcel.value = false;
      }
    }

    onMounted(loadPlans);

    return {
      loading,
      error,
      plans,
      comparison,
      plan,
      clients,
      newYear,
      creating,
      savingRow,
      exportingExcel,
      rowError,
      draft,
      pickClientId,
      formatPhone,
      fmtNum,
      numOrNull,
      intOrNull,
      selectPlan,
      createPlan,
      applyPickedClient,
      addRow,
      patchRow,
      patchPhone2,
      growthLabel,
      growthClass,
      removeRow,
      exportExcel,
    };
  },
};
</script>

<style scoped>
.vfp .muted { color: #5b6b60; margin: 0.25rem 0 0; }
.vfp-years__head {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 0.85rem;
  margin-bottom: 0.85rem;
}
.vfp-years__head h2,
.vfp-report h2,
.vfp-add h2,
.vfp-table-panel h2 {
  margin: 0 0 0.35rem;
  font-size: 1.05rem;
}
.vfp-report__head {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.85rem;
  margin-bottom: 0.9rem;
}
.vfp-report__eyebrow {
  margin: 0 0 0.2rem;
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  color: #0d6b3f;
}
.vfp-year-form {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.vfp-year-form input {
  width: 7.5rem;
  min-height: 42px;
  padding: 0.45rem 0.65rem;
  border: 1px solid rgba(15, 23, 42, 0.22);
  border-radius: 10px;
}
.vfp-year-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
}
.vfp-year-tab {
  display: grid;
  gap: 0.1rem;
  min-width: 5.5rem;
  padding: 0.55rem 0.75rem;
  border-radius: 12px;
  border: 1px solid rgba(15, 23, 42, 0.18);
  background: #fff;
  cursor: pointer;
  text-align: left;
}
.vfp-year-tab strong { font-size: 1.05rem; }
.vfp-year-tab small { color: #667085; }
.vfp-year-tab.on {
  border-color: #0d6b3f;
  background: #0d6b3f;
  color: #fff;
}
.vfp-year-tab.on small { color: rgba(255,255,255,0.85); }
.vfp-totals__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 0.65rem;
}
.vfp-totals__grid > div {
  border: 1px solid rgba(15, 23, 42, 0.18);
  border-radius: 12px;
  padding: 0.7rem 0.8rem;
  display: grid;
  gap: 0.25rem;
}
.vfp-totals__grid span { font-size: 0.78rem; color: #5b6b60; }
.vfp-totals__grid strong { font-size: 1.05rem; font-variant-numeric: tabular-nums; }
.vfp-form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 0.75rem;
}
.vfp-form-grid .form-wide { grid-column: 1 / -1; }
.vfp-pick { margin-bottom: 0.85rem; }
.vfp-table {
  width: 100%;
  min-width: 1100px;
  border-collapse: collapse;
  border: 1.5px solid rgba(15, 23, 42, 0.28);
  font-size: 0.86rem;
}
.vfp-table th,
.vfp-table td {
  border: 1px solid rgba(15, 23, 42, 0.22);
  padding: 0.35rem 0.4rem;
  vertical-align: middle;
}
.vfp-table thead th {
  background: #f3f4f6;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.02em;
  white-space: normal;
  max-width: 7.5rem;
}
.vfp-table .num { text-align: right; font-variant-numeric: tabular-nums; }
.vfp-cell {
  width: 100%;
  min-width: 5rem;
  border: none;
  background: transparent;
  padding: 0.25rem 0.2rem;
  font: inherit;
  color: inherit;
}
.vfp-cell--num { text-align: right; min-width: 4.5rem; }
.vfp-cell:focus {
  outline: 2px solid rgba(13, 107, 63, 0.35);
  background: #fff;
}
.mono { font-variant-numeric: tabular-nums; white-space: nowrap; }
.vfp-compare h2 { margin: 0 0 0.35rem; font-size: 1.05rem; }
.vfp-table--compare { min-width: 820px; }
.vfp-vs { display: block; color: #667085; font-weight: 500; margin-top: 0.15rem; }
.vfp-growth {
  display: block;
  margin-top: 0.2rem;
  font-size: 0.78rem;
  font-weight: 800;
}
.vfp-growth.is-up { color: #0d6b3f; }
.vfp-growth.is-down { color: #b42318; }
.vfp-growth.is-flat { color: #667085; }
</style>
