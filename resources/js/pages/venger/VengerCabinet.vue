<template>
  <div class="venger-clients venger-readable">
    <OwnerVengerNav v-if="isOwnerView" />
    <div class="page-head">
      <div>
        <h1>{{ $t('vengerClientsTitle') }}</h1>
        <p>{{ isOwnerView ? $t('ownerVengerClientsHint') : $t('vengerClientsOnlyHint') }}</p>
      </div>
      <button class="btn btn-primary" type="button" @click="openCreate"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> {{ $t('addVengerClient') }}</button>
    </div>

    <div class="panel venger-search">
      <div class="form-grid venger-search__grid">
        <div class="field">
          <label>{{ $t('vengerSearchClients') }}</label>
          <input
            v-model="searchQ"
            type="search"
            :placeholder="$t('vengerSearchPlaceholder')"
            @input="onSearchInput"
          />
        </div>
        <div class="field">
          <label>{{ $t('region') }}</label>
          <select v-model="searchRegion" @change="load">
            <option value="">{{ $t('allRegions') }}</option>
            <option v-for="r in regions" :key="r" :value="r">{{ r }}</option>
          </select>
        </div>
      </div>
      <p class="field-hint">
        {{ $t('vengerSearchResultCount', { n: filteredCount, total: stats.clients_count }) }}
      </p>
    </div>

    <div v-if="loading" class="empty">{{ $t('loading') }}</div>
    <div v-else-if="error" class="panel empty">{{ error }}</div>

    <template v-else>
      <div class="panel">
        <div class="venger-mlist">
          <article v-for="c in clients" :key="`m-${c.id}`" class="venger-mcard">
            <strong class="venger-mcard__name">{{ c.name }}</strong>
            <p class="venger-mcard__line">{{ c.region || '—' }}<template v-if="c.district"> · {{ c.district }}</template></p>
            <p class="venger-mcard__line">{{ $t('vengerPondAreaHa') }}: <strong>{{ fmtArea(c.pond_area_ha) }}</strong></p>
            <p class="venger-mcard__line mono">{{ formatPhone(c.phone) }}</p>
            <p v-if="c.phone2" class="venger-mcard__line mono">{{ formatPhone(c.phone2) }}</p>
            <p v-if="isOwnerView" class="venger-mcard__line">{{ c.staff?.name || '—' }}</p>
            <router-link class="btn btn-primary btn-sm" :to="clientRoute(c.id)">{{ $t('open') }}</router-link>
          </article>
          <p v-if="!clients.length" class="empty">{{ searchActive ? $t('vengerSearchEmpty') : $t('noVengerClients') }}</p>
        </div>
        <div class="table-scroll venger-desktop-table">
          <table>
            <thead>
              <tr>
                <th>{{ $t('fullName') }}</th>
                <th>{{ $t('region') }}</th>
                <th>{{ $t('district') }}</th>
                <th class="num">{{ $t('vengerPondAreaHa') }}</th>
                <th>{{ $t('phone') }}</th>
                <th v-if="isOwnerView">{{ $t('vengerStaff') }}</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in clients" :key="c.id">
                <td><strong>{{ c.name }}</strong></td>
                <td>{{ c.region || '—' }}</td>
                <td>{{ c.district || '—' }}</td>
                <td class="num"><strong>{{ fmtArea(c.pond_area_ha) }}</strong></td>
                <td class="mono">
                  {{ formatPhone(c.phone) }}
                  <template v-if="c.phone2"><br>{{ formatPhone(c.phone2) }}</template>
                </td>
                <td v-if="isOwnerView">{{ c.staff?.name || '—' }}</td>
                <td>
                  <router-link class="btn btn-outline btn-sm" :to="clientRoute(c.id)">
                    {{ $t('open') }}
                  </router-link>
                </td>
              </tr>
              <tr v-if="!clients.length">
                <td :colspan="isOwnerView ? 7 : 6" class="empty">{{ searchActive ? $t('vengerSearchEmpty') : $t('noVengerClients') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <Teleport to="body">
    <div v-if="modal" class="modal-backdrop" @click.self="modal = false">
      <div class="modal">
        <h2 class="modal-title">{{ $t('addVengerClient') }}</h2>
        <form class="form-grid one-col" @submit.prevent="save">
          <div class="field">
            <label>{{ $t('firstName') }} <span class="req">*</span></label>
            <input v-model="form.first_name" required maxlength="120" :placeholder="$t('firstNamePlaceholder')" />
          </div>
          <div class="field">
            <label>{{ $t('lastName') }} <span class="req">*</span></label>
            <input v-model="form.last_name" required maxlength="120" :placeholder="$t('lastNamePlaceholder')" />
          </div>
          <RegionDistrictPicker
            :region="form.region"
            :district="form.district"
            @update:region="form.region = $event"
            @update:district="form.district = $event"
          />
          <div class="field">
            <label>{{ $t('vengerPondAreaHa') }} <span class="req">*</span></label>
            <FormattedNumberInput v-model="form.pond_area_ha" :max-decimals="3" required />
            <p class="field-hint">{{ $t('vengerPondAreaHaHint') }}</p>
          </div>
          <div class="field">
            <label>{{ $t('address') }}</label>
            <input v-model="form.address" maxlength="500" :placeholder="$t('addressDetailPlaceholder')" />
          </div>
          <div class="field">
            <label>{{ $t('phone') }} <span class="req">*</span></label>
            <PhoneInput v-model="form.phone" required />
          </div>
          <div class="field">
            <label>{{ $t('phone2Optional') }}</label>
            <PhoneInput v-model="form.phone2" />
          </div>
          <div class="field">
            <label>{{ $t('notes') }}</label>
            <textarea v-model="form.notes" rows="3" maxlength="2000" />
          </div>
          <p v-if="formError" class="error">{{ formError }}</p>
          <div class="actions">
            <button class="btn btn-primary" type="submit">{{ $t('save') }}</button>
            <button class="btn btn-outline" type="button" @click="modal = false">{{ $t('cancel') }}</button>
          </div>
        </form>
      </div>
    </div>
    </Teleport>
  </div>
</template>

<script>
import { computed, onMounted, reactive, ref } from 'vue';
import PhoneInput from '../../components/PhoneInput.vue';
import FormattedNumberInput from '../../components/FormattedNumberInput.vue';
import RegionDistrictPicker from '../../components/RegionDistrictPicker.vue';
import OwnerVengerNav from '../../components/OwnerVengerNav.vue';
import auth from '../../auth';
import { uzbekistanDistricts } from '../../data/uzbekistanDistricts';
import { formatPhoneDisplay, isCompletePhone } from '../../utils/phone';
import { t } from '../../i18n';

const emptyForm = () => ({
  first_name: '',
  last_name: '',
  address: '',
  region: '',
  district: '',
  pond_area_ha: '',
  phone: '+998',
  phone2: '+998',
  notes: '',
});

export default {
  components: { PhoneInput, FormattedNumberInput, RegionDistrictPicker, OwnerVengerNav },
  setup() {
    const loading = ref(true);
    const error = ref('');
    const formError = ref('');
    const clients = ref([]);
    const stats = ref({ clients_count: 0, results_count: 0, filtered_count: 0 });
    const modal = ref(false);
    const form = reactive(emptyForm());
    const searchQ = ref('');
    const searchRegion = ref('');
    const regions = Object.keys(uzbekistanDistricts);
    const isOwnerView = computed(() => auth.isOwner());
    const apiBase = computed(() => (auth.isOwner() ? '/owner/venger' : '/venger'));
    let searchTimer = null;

    const filteredCount = computed(() => Number(stats.value.filtered_count ?? clients.value.length));
    const searchActive = computed(() => !!searchQ.value.trim() || !!searchRegion.value);

    function clientRoute(clientId) {
      return auth.isOwner()
        ? { name: 'owner-venger-client', params: { clientId } }
        : { name: 'venger-client', params: { clientId } };
    }

    function formatPhone(phone) {
      return formatPhoneDisplay(phone || '');
    }

    function fmtArea(v) {
      const n = Number(v);
      if (!Number.isFinite(n) || n <= 0) return '—';
      return `${n.toLocaleString(undefined, { maximumFractionDigits: 3 })} ha`;
    }

    async function load() {
      loading.value = true;
      error.value = '';
      try {
        const params = {};
        if (searchQ.value.trim()) params.q = searchQ.value.trim();
        if (searchRegion.value) params.region = searchRegion.value;
        const { data } = await window.axios.get(`${apiBase.value}/clients`, { params });
        clients.value = data.clients || [];
        stats.value = data.stats || { clients_count: 0, results_count: 0, filtered_count: clients.value.length };
      } catch (e) {
        error.value = e.response?.data?.message || t('errorGeneric');
      } finally {
        loading.value = false;
      }
    }

    function onSearchInput() {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(load, 280);
    }

    function openCreate() {
      Object.assign(form, emptyForm());
      formError.value = '';
      modal.value = true;
    }

    async function save() {
      formError.value = '';
      if (!form.first_name.trim() || !form.last_name.trim()) {
        formError.value = t('fullNameRequired');
        return;
      }
      if (!form.region) {
        formError.value = t('selectRegionFirst');
        return;
      }
      if (!form.district) {
        formError.value = t('districtRequired');
        return;
      }
      const pondArea = Number(form.pond_area_ha);
      if (!Number.isFinite(pondArea) || pondArea <= 0) {
        formError.value = t('vengerPondAreaRequired');
        return;
      }
      if (!isCompletePhone(form.phone)) {
        formError.value = t('phoneIncompleteFull');
        return;
      }
      const phone2 = isCompletePhone(form.phone2) ? form.phone2 : null;
      if (form.phone2 && form.phone2 !== '+998' && !phone2) {
        formError.value = t('phoneIncompleteFull');
        return;
      }
      try {
        await window.axios.post(`${apiBase.value}/clients`, {
          first_name: form.first_name.trim(),
          last_name: form.last_name.trim(),
          address: form.address || null,
          region: form.region,
          district: form.district,
          pond_area_ha: pondArea,
          phone: form.phone,
          phone2,
          notes: form.notes || null,
        });
        modal.value = false;
        await load();
      } catch (e) {
        const errors = e.response?.data?.errors;
        formError.value = errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message || t('errorGeneric'));
      }
    }

    onMounted(load);

    return {
      loading, error, formError, clients, stats, modal, form,
      searchQ, searchRegion, regions, filteredCount, searchActive, isOwnerView,
      formatPhone, fmtArea, clientRoute, openCreate, save, load, onSearchInput,
    };
  },
};
</script>

<style scoped>
.venger-search { margin-bottom: 1rem; }
.venger-search__grid {
  grid-template-columns: 1.6fr 1fr;
}
.num { text-align: right; font-variant-numeric: tabular-nums; }
@media (max-width: 720px) {
  .venger-search__grid { grid-template-columns: 1fr; }
}
</style>
