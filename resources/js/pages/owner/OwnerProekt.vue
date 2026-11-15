<template>
  <div class="page owner-page owner-proekt">
    <header class="page-head">
      <div class="page-head__text">
        <h1>{{ $t('ownerProektTitle') }}</h1>
        <p>{{ view === 'edit' ? $t('ownerProektEditHint') : $t('ownerProektHint') }}</p>
      </div>
      <button
        v-if="view !== 'list'"
        type="button"
        class="btn btn-outline"
        @click="backToList"
      >
        ← {{ $t('ownerProektBackList') }}
      </button>
    </header>

    <div v-if="loading" class="muted">{{ $t('loading') }}</div>
    <div v-else-if="error" class="error">{{ error }}</div>

    <template v-else-if="view === 'list'">
      <section class="panel op-list-head">
        <div>
          <strong>{{ $t('ownerProektSavedTitle') }}</strong>
          <p class="muted">{{ $t('ownerProektSavedHint') }}</p>
        </div>
        <button type="button" class="btn btn-primary" @click="startNew">
          {{ $t('ownerProektNew') }}
        </button>
      </section>

      <section v-if="projects.length" class="panel op-list">
        <button
          v-for="p in projects"
          :key="p.id"
          type="button"
          class="op-list__row"
          @click="openSaved(p.id)"
        >
          <span class="op-list__main">
            <strong>{{ p.title }}</strong>
            <span class="muted">
              {{ p.client_name || $t('ownerProektNoClient') }}
              · {{ p.region || '—' }}
              · {{ formatNumber(p.area_ha) }} {{ $t('ownerProektHa') }}
            </span>
          </span>
          <span class="op-list__meta">
            <span class="op-list__badge">{{ $t('ownerProektOpenDossier') }}</span>
            <span class="op-list__chev" aria-hidden="true">›</span>
          </span>
        </button>
      </section>
      <p v-else class="panel op-empty">{{ $t('ownerProektEmpty') }}</p>
    </template>

    <OwnerProektDossier
      v-else-if="view === 'dossier'"
      :inputs="inputs"
      :result="result"
      :mode-label="modeLabel"
      @edit="view = 'edit'"
      @back="backToList"
      @delete="removeProject"
    />

    <template v-else>
      <div class="op-layout">
        <div class="op-forms">
          <section class="panel op-form">
            <h2>{{ $t('ownerProektSecPond') }}</h2>
            <div class="op-grid">
              <label>
                <span>{{ $t('ownerProektFieldTitle') }}</span>
                <input v-model.trim="inputs.title" type="text" maxlength="180">
              </label>
              <label>
                <span>{{ $t('ownerProektFieldClient') }}</span>
                <input v-model.trim="inputs.clientName" type="text" maxlength="180">
              </label>
              <label>
                <span>{{ $t('ownerProektFieldRegion') }}</span>
                <select v-model="inputs.region">
                  <option v-for="r in regions" :key="r" :value="r">{{ r }}</option>
                </select>
              </label>
              <label>
                <span>{{ $t('ownerProektFieldArea') }}</span>
                <FormattedNumberInput v-model="inputs.areaHa" :max-decimals="2" />
              </label>
              <label class="op-span2">
                <span>{{ $t('ownerProektFieldPondNote') }}</span>
                <input v-model.trim="inputs.pondNote" type="text" maxlength="240" :placeholder="$t('ownerProektFieldPondNotePh')">
              </label>
              <p class="op-note op-span2">{{ $t('ownerProektCapacityAutoNote') }}</p>
            </div>

            <div class="op-mode" role="group" :aria-label="$t('ownerProektFieldMode')">
              <button
                type="button"
                class="op-mode__btn"
                :class="{ 'is-active': inputs.mode === 'tovar' }"
                @click="inputs.mode = 'tovar'"
              >
                {{ $t('ownerProektModeTovar') }}
              </button>
              <button
                type="button"
                class="op-mode__btn"
                :class="{ 'is-active': inputs.mode === 'chavoq' }"
                @click="inputs.mode = 'chavoq'"
              >
                {{ $t('ownerProektModeChavoq') }}
              </button>
            </div>
            <p class="op-note">{{ inputs.mode === 'chavoq' ? $t('ownerProektModeChavoqHint') : $t('ownerProektModeTovarHint') }}</p>
          </section>

          <section class="panel op-form op-form--fish">
            <h2>{{ $t('ownerProektSecFish') }}</h2>
            <p class="op-lead">{{ $t('ownerProektFishLead') }}</p>
            <div class="op-fish-cards">
              <article
                v-for="key in fishKeys"
                :key="key"
                class="op-fish-card"
                :class="{ 'op-fish-card--feed': fishMeta[key].usesFeed }"
              >
                <header class="op-fish-card__head">
                  <strong>{{ $t(fishMeta[key].labelKey) }}</strong>
                  <small v-if="fishMeta[key].usesFeed" class="op-feed-tag">FCR</small>
                </header>
                <div class="op-fish-card__grid">
                  <label>
                    <span>{{ $t('ownerProektColCount') }}</span>
                    <FormattedNumberInput v-model="inputs.fish[key].count" integer-only />
                  </label>
                  <label>
                    <span>{{ $t('ownerProektColStartG') }}</span>
                    <FormattedNumberInput v-model="inputs.fish[key].startG" integer-only />
                  </label>
                  <label>
                    <span>{{ $t('ownerProektColEndG') }}</span>
                    <FormattedNumberInput v-model="inputs.fish[key].endG" integer-only />
                  </label>
                  <label>
                    <span>{{ $t('ownerProektColSurvival') }}</span>
                    <FormattedNumberInput v-model="inputs.fish[key].survivalPct" integer-only />
                  </label>
                  <label>
                    <span>{{ buyPriceLabel }}</span>
                    <FormattedNumberInput v-model="inputs.fish[key].buyPriceEach" integer-only />
                  </label>
                  <label>
                    <span>{{ sellPriceLabel }}</span>
                    <FormattedNumberInput v-model="inputs.fish[key].sellPrice" integer-only />
                  </label>
                </div>
              </article>
            </div>
            <p class="op-note">{{ $t('ownerProektFishPriceNote') }}</p>
          </section>

          <section class="panel op-form">
            <h2>{{ $t('ownerProektSecCosts') }}</h2>
            <p class="op-lead">{{ $t('ownerProektCostsLead') }}</p>
            <div class="op-feed-auto">
              <div>
                <span>{{ $t('ownerProektAutoFeedLabel') }}</span>
                <strong>{{ formatNumber(result.production.feedKg) }} {{ $t('ownerProektKg') }}</strong>
              </div>
              <p>
                {{ $t('ownerProektAutoFeedHint', {
                  gain: formatNumber(result.production.feedGainKg),
                  fcr: result.fcr,
                  mode: modeLabel,
                }) }}
              </p>
            </div>
            <div class="op-grid">
              <label>
                <span>{{ $t('ownerProektFieldFeedPrice') }}</span>
                <FormattedNumberInput v-model="inputs.feedPricePerKg" integer-only />
              </label>
              <label>
                <span>{{ $t('ownerProektFieldLabor') }}</span>
                <FormattedNumberInput v-model="inputs.laborCost" integer-only />
              </label>
              <label>
                <span>{{ $t('ownerProektFieldElectric') }}</span>
                <FormattedNumberInput v-model="inputs.electricCost" integer-only />
              </label>
            </div>

            <h3 class="op-sub">{{ $t('ownerProektCustomCostsTitle') }}</h3>
            <p class="op-note">{{ $t('ownerProektCustomCostsHint') }}</p>
            <div class="op-custom">
              <div v-for="(c, idx) in inputs.customCosts" :key="c.id" class="op-custom__row">
                <input
                  v-model.trim="c.name"
                  type="text"
                  class="op-custom__name"
                  :placeholder="$t('ownerProektCustomCostNamePh')"
                >
                <FormattedNumberInput v-model="c.amount" integer-only class="op-custom__amount" />
                <button type="button" class="btn btn-outline btn-sm" @click="removeCustomCost(idx)">×</button>
              </div>
              <button type="button" class="btn btn-outline btn-sm" @click="addCustomCost">
                + {{ $t('ownerProektAddCustomCost') }}
              </button>
            </div>

            <h3 class="op-sub">{{ $t('ownerProektExtrasTitle') }}</h3>
            <p class="op-note">{{ $t('ownerProektExtrasHint') }}</p>
            <div class="op-extras">
              <div v-for="(ex, idx) in inputs.extras" :key="ex.id" class="op-extras__row">
                <input
                  v-model.trim="ex.label"
                  type="text"
                  class="op-extras__name"
                  :placeholder="ex.labelKey ? $t(ex.labelKey) : $t('ownerProektExtraName')"
                >
                <FormattedNumberInput v-model="ex.pct" :max-decimals="1" class="op-extras__pct" />
                <span class="op-extras__unit">%</span>
                <button type="button" class="btn btn-outline btn-sm" @click="removeExtra(idx)">×</button>
              </div>
              <button type="button" class="btn btn-outline btn-sm" @click="addExtra">
                + {{ $t('ownerProektAddExtra') }}
              </button>
            </div>
          </section>
        </div>

        <aside class="op-result-side">
          <section class="panel op-result-card">
            <h2>{{ $t('ownerProektResultTitle') }}</h2>
            <p class="op-invest-mini">{{ $t('ownerProektEditPreviewHint') }}</p>

            <ul v-if="result.warnings.length" class="op-warns">
              <li
                v-for="(w, i) in result.warnings"
                :key="i"
                :class="`is-${w.tone}`"
              >
                {{ $t(w.key, w.params || {}) }}
              </li>
            </ul>

            <div class="op-summary">
              <p>
                <strong>{{ inputs.title || $t('ownerProektTitle') }}</strong>
                — {{ formatNumber(result.production.areaHa) }} {{ $t('ownerProektHa') }}
                · {{ modeLabel }}
              </p>
            </div>

            <dl class="op-dl">
              <div>
                <dt>{{ $t('ownerProektFinalHarvest') }}</dt>
                <dd>{{ formatNumber(result.production.harvestKg) }} {{ $t('ownerProektKg') }}</dd>
              </div>
              <div>
                <dt>{{ $t('ownerProektCapacityAuto') }}</dt>
                <dd>{{ formatNumber(result.production.pondCapacityKg) }} {{ $t('ownerProektKg') }}</dd>
              </div>
              <div>
                <dt>{{ $t('ownerProektAutoFeedLabel') }}</dt>
                <dd>{{ formatNumber(result.production.feedKg) }} {{ $t('ownerProektKg') }} (FCR {{ result.fcr }})</dd>
              </div>
              <div>
                <dt>{{ $t('ownerProektTotalCost') }}</dt>
                <dd>{{ formatMoney(result.costs.total) }}</dd>
              </div>
              <div>
                <dt>{{ $t('ownerProektRevenue') }}</dt>
                <dd>{{ formatMoney(result.finance.revenue) }}</dd>
              </div>
              <div>
                <dt>{{ $t('ownerProektProfit') }}</dt>
                <dd :class="result.finance.profit >= 0 ? 'is-plus' : 'is-minus'">
                  {{ formatMoney(result.finance.profit) }}
                </dd>
              </div>
              <div>
                <dt>{{ $t('ownerProektMargin') }}</dt>
                <dd :class="result.finance.marginPct >= 0 ? 'is-plus' : 'is-minus'">
                  {{ result.finance.marginPct }}%
                </dd>
              </div>
            </dl>

            <ul class="op-costs op-costs--compact">
              <li>
                <span>{{ $t('ownerProektCostStock') }}</span>
                <strong>{{ formatMoney(result.costs.stock) }}</strong>
              </li>
              <li>
                <span>{{ $t('ownerProektCostFeed') }}</span>
                <strong>{{ formatMoney(result.costs.feed) }}</strong>
              </li>
              <li>
                <span>{{ $t('ownerProektFieldLabor') }}</span>
                <strong>{{ formatMoney(result.costs.labor) }}</strong>
              </li>
              <li>
                <span>{{ $t('ownerProektFieldElectric') }}</span>
                <strong>{{ formatMoney(result.costs.electric) }}</strong>
              </li>
              <li v-for="c in result.costs.custom" :key="c.id">
                <span>{{ c.name || $t('ownerProektCustomCostName') }}</span>
                <strong>{{ formatMoney(c.amount) }}</strong>
              </li>
              <li v-for="ex in result.costs.extras" :key="ex.id">
                <span>{{ extraLabel(ex) }} ({{ ex.pct }}%)</span>
                <strong>{{ formatMoney(ex.amount) }}</strong>
              </li>
            </ul>

            <div class="op-actions">
              <button type="button" class="btn btn-primary" :disabled="saving || !canSave" @click="saveProject">
                {{ saving ? $t('loading') : (projectId ? $t('ownerProektFinishUpdate') : $t('ownerProektFinishCreate')) }}
              </button>
              <button
                v-if="projectId"
                type="button"
                class="btn btn-outline"
                @click="view = 'dossier'"
              >
                {{ $t('ownerProektOpenDossier') }}
              </button>
              <span v-if="saveMsg" class="op-save-msg">{{ saveMsg }}</span>
            </div>
            <p class="op-note op-note--tight">{{ $t('ownerProektFinishHint') }}</p>
          </section>
        </aside>
      </div>
    </template>
  </div>
</template>

<script>
import { computed, onMounted, reactive, ref } from 'vue';
import { t } from '../../i18n';
import { formatMoney, formatNumber } from '../../utils/number';
import FormattedNumberInput from '../../components/FormattedNumberInput.vue';
import OwnerProektDossier from '../../components/OwnerProektDossier.vue';
import {
  FISH_KEYS,
  FISH_META,
  PROEKT_REGIONS,
  emptyCustomCost,
  emptyProektInputs,
  normalizeInputs,
  runProekt,
} from '../../config/ownerProekt';
import { logOwnerCompletedWork } from '../../utils/ownerCompletedLog';

export default {
  name: 'OwnerProekt',
  components: { FormattedNumberInput, OwnerProektDossier },
  setup() {
    const loading = ref(true);
    const error = ref('');
    const view = ref('list');
    const projects = ref([]);
    const projectId = ref(null);
    const inputs = reactive(emptyProektInputs());
    const saving = ref(false);
    const deleting = ref(false);
    const saveMsg = ref('');

    const result = computed(() => runProekt(inputs));

    const canSave = computed(() => Boolean(String(inputs.title || '').trim()) && Number(inputs.areaHa) > 0);

    const buyPriceLabel = computed(() => (
      inputs.mode === 'chavoq' ? t('ownerProektColBuyEach') : t('ownerProektColBuyKg')
    ));

    const sellPriceLabel = computed(() => t('ownerProektFieldSellTovar'));

    const modeLabel = computed(() => (
      inputs.mode === 'chavoq' ? t('ownerProektModeChavoq') : t('ownerProektModeTovar')
    ));

    function extraLabel(ex) {
      if (ex.label) return ex.label;
      if (ex.labelKey) return t(ex.labelKey);
      return t('ownerProektExtraName');
    }

    function addExtra() {
      inputs.extras.push({
        id: `extra_${Date.now()}`,
        labelKey: null,
        label: '',
        pct: 1,
      });
    }

    function removeExtra(idx) {
      inputs.extras.splice(idx, 1);
    }

    function addCustomCost() {
      inputs.customCosts.push(emptyCustomCost());
    }

    function removeCustomCost(idx) {
      inputs.customCosts.splice(idx, 1);
    }

    function resetForm() {
      const fresh = emptyProektInputs();
      Object.keys(inputs).forEach((k) => {
        if (k === 'fish' || k === 'extras' || k === 'layout' || k === 'customCosts') return;
        inputs[k] = fresh[k];
      });
      inputs.fish.sazan = { ...fresh.fish.sazan };
      inputs.fish.amur = { ...fresh.fish.amur };
      inputs.fish.tolstolob = { ...fresh.fish.tolstolob };
      inputs.layout = { ...fresh.layout };
      inputs.extras.splice(0, inputs.extras.length, ...fresh.extras.map((x) => ({ ...x })));
      inputs.customCosts.splice(0, inputs.customCosts.length);
      projectId.value = null;
      saveMsg.value = '';
    }

    function applyInputs(raw) {
      const n = normalizeInputs(raw);
      Object.keys(n).forEach((k) => {
        if (k === 'fish' || k === 'extras' || k === 'layout' || k === 'customCosts') return;
        inputs[k] = n[k];
      });
      FISH_KEYS.forEach((key) => {
        inputs.fish[key] = { ...n.fish[key] };
      });
      inputs.layout = { ...n.layout };
      inputs.extras.splice(0, inputs.extras.length, ...n.extras.map((x) => ({ ...x })));
      inputs.customCosts.splice(
        0,
        inputs.customCosts.length,
        ...n.customCosts.map((x) => ({ ...x })),
      );
    }

    function startNew() {
      resetForm();
      view.value = 'edit';
    }

    function backToList() {
      view.value = 'list';
      loadList();
    }

    async function loadList() {
      loading.value = true;
      error.value = '';
      try {
        const { data } = await window.axios.get('/owner/projects');
        projects.value = data.projects || [];
      } catch (e) {
        error.value = e?.response?.data?.message || t('errorGeneric');
      } finally {
        loading.value = false;
      }
    }

    async function openSaved(id) {
      loading.value = true;
      error.value = '';
      try {
        const { data } = await window.axios.get(`/owner/projects/${id}`);
        const p = data.project;
        projectId.value = p.id;
        applyInputs({
          ...(p.inputs || {}),
          title: p.title || p.inputs?.title || '',
          clientName: p.client_name || p.inputs?.clientName || '',
          region: p.region || p.inputs?.region || '',
          areaHa: p.area_ha ?? p.inputs?.areaHa ?? 5,
        });
        view.value = 'dossier';
      } catch (e) {
        error.value = e?.response?.data?.message || t('errorGeneric');
      } finally {
        loading.value = false;
      }
    }

    async function saveProject() {
      saving.value = true;
      saveMsg.value = '';
      const res = result.value;
      const payload = {
        title: String(inputs.title || '').trim() || t('ownerProektTitle'),
        client_name: inputs.clientName || null,
        region: inputs.region,
        area_ha: Number(inputs.areaHa) || 0,
        recommended_method: inputs.mode,
        site_score: null,
        status: 'ready',
        inputs: JSON.parse(JSON.stringify(normalizeInputs(inputs))),
        results: res,
      };
      try {
        const isNew = !projectId.value;
        if (projectId.value) {
          const { data } = await window.axios.put(`/owner/projects/${projectId.value}`, payload);
          projectId.value = data.project.id;
        } else {
          const { data } = await window.axios.post('/owner/projects', payload);
          projectId.value = data.project.id;
        }

        applyInputs(payload.inputs);

        const logLine = t('ownerProektCompletedLogLine', {
          title: payload.title,
          area: formatNumber(payload.area_ha),
          mode: modeLabel.value,
          profit: formatMoney(res.finance?.profit || 0),
          margin: res.finance?.marginPct ?? 0,
          action: isNew ? t('ownerProektLogActionCreated') : t('ownerProektLogActionUpdated'),
        });
        try {
          await logOwnerCompletedWork(logLine, { color: 'mint' });
          saveMsg.value = `${t('ownerProektSavedOk')} · ${t('ownerProektLoggedToPlanner')}`;
        } catch (_) {
          saveMsg.value = t('ownerProektSavedOk');
        }
        view.value = 'dossier';
      } catch (e) {
        saveMsg.value = e?.response?.data?.message || t('errorGeneric');
      } finally {
        saving.value = false;
      }
    }

    async function removeProject() {
      if (!projectId.value) return;
      if (!window.confirm(t('ownerProektDeleteConfirm'))) return;
      deleting.value = true;
      try {
        await window.axios.delete(`/owner/projects/${projectId.value}`);
        backToList();
      } catch (e) {
        saveMsg.value = e?.response?.data?.message || t('errorGeneric');
        error.value = saveMsg.value;
      } finally {
        deleting.value = false;
      }
    }

    onMounted(loadList);

    return {
      loading,
      error,
      view,
      projects,
      projectId,
      inputs,
      result,
      saving,
      deleting,
      saveMsg,
      canSave,
      buyPriceLabel,
      sellPriceLabel,
      modeLabel,
      regions: PROEKT_REGIONS,
      fishKeys: FISH_KEYS,
      fishMeta: FISH_META,
      formatMoney,
      formatNumber,
      extraLabel,
      addExtra,
      removeExtra,
      addCustomCost,
      removeCustomCost,
      startNew,
      backToList,
      openSaved,
      saveProject,
      removeProject,
    };
  },
};
</script>
