<template>
  <div class="owner-home">
    <div v-if="loading" class="empty">{{ $t('loading') }}</div>
    <div v-else-if="loadError" class="panel empty">{{ loadError }}</div>

    <template v-else-if="data">
      <section class="owner-home__section" aria-labelledby="owner-home-scale">
        <div class="owner-home__section-head">
          <h2 id="owner-home-scale">{{ $t('ownerHomeScaleTitle') }}</h2>
        </div>

        <div class="owner-home__stats">
          <router-link class="owner-home-stat owner-home-stat--clients" :to="{ name: 'owner-companies' }">
            <span>{{ $t('companies') }}</span>
            <strong>{{ number(data.companies_count) }}</strong>
          </router-link>
          <router-link class="owner-home-stat owner-home-stat--farms" :to="{ name: 'owner-companies' }">
            <span>{{ $t('active') }}</span>
            <strong>{{ number(data.active_companies) }}</strong>
          </router-link>
          <div class="owner-home-stat owner-home-stat--workers">
            <span>{{ $t('inactive') }}</span>
            <strong>{{ number(data.inactive_companies) }}</strong>
          </div>
          <router-link
            class="owner-home-stat owner-home-stat--warn"
            :class="{ 'is-alert': (data.expiring_count || 0) > 0 }"
            :to="{ name: 'owner-companies' }"
          >
            <span>{{ $t('companyExpiringTitle') }}</span>
            <strong>{{ number(data.expiring_count) }}</strong>
          </router-link>
          <a
            v-if="(data.due_checks_count || 0) > 0"
            class="owner-home-stat owner-home-stat--ponds is-alert"
            href="#owner-home-checks"
          >
            <span>{{ $t('ownerClientsDue') }}</span>
            <strong>{{ number(data.due_checks_count) }}</strong>
          </a>
          <div
            v-else
            class="owner-home-stat owner-home-stat--ponds"
          >
            <span>{{ $t('ownerClientsDue') }}</span>
            <strong>0</strong>
          </div>
          <div class="owner-home-stat owner-home-stat--area">
            <span>{{ $t('ponds') }}</span>
            <strong>{{ number(regionFilter ? activeRegionRow?.ponds_count : data.ponds_count) }}</strong>
          </div>
          <div class="owner-home-stat owner-home-stat--fish">
            <span>{{ $t('totalPondArea') }}</span>
            <strong>{{ number(regionFilter ? activeRegionRow?.area_ha : data.total_area_ha) }}</strong>
            <small>{{ $t('hectare') }}</small>
          </div>
          <div class="owner-home-stat owner-home-stat--directors">
            <span>{{ $t('totalFishCount') }}</span>
            <strong>{{ number(regionFilter ? activeRegionRow?.fish_count : data.total_fish_count) }}</strong>
          </div>
        </div>
      </section>

      <section
        v-if="(data.expiring_companies || []).length"
        class="owner-home__section panel owner-home__expiring"
        aria-labelledby="owner-home-expiring"
      >
        <div class="owner-home__section-head owner-home__section-head--row">
          <h2 id="owner-home-expiring">{{ $t('companyExpiringTitle') }}</h2>
          <router-link class="btn btn-outline btn-sm" :to="{ name: 'owner-companies' }">
            {{ $t('companies') }}
          </router-link>
        </div>
        <ul class="owner-home__expire-list">
          <li v-for="c in data.expiring_companies" :key="c.id">
            <div>
              <strong>{{ c.name }}</strong>
              <small>{{ c.admin_name || '—' }} · {{ c.admin_phone || '—' }}</small>
            </div>
            <em>{{ $t('companyDaysLeft', { n: c.access_days_left }) }}</em>
            <router-link class="btn btn-outline btn-sm" :to="{ name: 'owner-companies' }">
              {{ $t('companyRenewPlan') }}
            </router-link>
          </li>
        </ul>
      </section>

      <section
        v-if="(data.due_check_companies || []).length"
        id="owner-home-checks"
        class="owner-home__section panel owner-home__checks"
        aria-labelledby="owner-home-checks-title"
      >
        <div class="owner-home__section-head owner-home__section-head--row">
          <h2 id="owner-home-checks-title">{{ $t('ownerClientsDue') }}</h2>
        </div>
        <ul class="owner-home__expire-list owner-home__check-list">
          <li v-for="c in data.due_check_companies" :key="c.id">
            <div>
              <strong>{{ c.name }}</strong>
              <small>{{ c.admin_name || '—' }} · {{ c.admin_phone || '—' }}</small>
            </div>
            <em class="is-due">
              {{ c.next_check_at || $t('ownerClientsCheckMissing') }}
            </em>
            <router-link
              class="btn btn-primary btn-sm"
              :to="{ name: 'owner-companies', query: { inspect: c.id } }"
            >
              {{ $t('ownerClientsStartCheck') }}
            </router-link>
          </li>
        </ul>
      </section>

      <section class="owner-home__section panel owner-home__regions" aria-labelledby="owner-home-regions">
        <div class="owner-home__section-head owner-home__section-head--row">
          <h2 id="owner-home-regions">
            {{ regionFilter ? $t('regionDetailTitle', { region: regionFilter }) : $t('regionBreakdown') }}
          </h2>
          <div class="owner-home__region-filter">
            <label class="visually-hidden" for="owner-home-region">{{ $t('filterByRegion') }}</label>
            <select id="owner-home-region" v-model="regionFilter">
              <option value="">{{ $t('allRegions') }}</option>
              <option v-for="r in allRegionNames" :key="r" :value="r">{{ r }}</option>
            </select>
            <button
              v-if="regionFilter"
              class="btn btn-outline btn-sm"
              type="button"
              @click="regionFilter = ''"
            >
              {{ $t('showAllRegions') }}
            </button>
          </div>
        </div>

        <div v-if="!tableRows.length" class="empty">{{ $t('noPondsInRegion') }}</div>
        <div v-else class="table-scroll">
          <table class="report-table owner-region-table">
            <thead>
              <tr>
                <th>{{ $t('region') }}</th>
                <th>{{ $t('ponds') }}</th>
                <th>{{ $t('companies') }}</th>
                <th>{{ $t('totalPondArea') }}</th>
                <th>{{ $t('totalFishCount') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in tableRows"
                :key="row.region"
                :class="{ 'owner-region-row--active': row.region === regionFilter }"
              >
                <td>
                  <button
                    v-if="!regionFilter"
                    type="button"
                    class="owner-region-link"
                    @click="regionFilter = row.region"
                  >
                    {{ row.region }}
                  </button>
                  <strong v-else>{{ row.region }}</strong>
                </td>
                <td class="num">{{ number(row.ponds_count) }}</td>
                <td class="num">{{ number(row.directors_count) }}</td>
                <td class="num">{{ number(row.area_ha) }} {{ $t('hectare') }}</td>
                <td class="num">{{ number(row.fish_count) }} {{ $t('pieces') }}</td>
              </tr>
            </tbody>
            <tfoot v-if="!regionFilter && tableRows.length > 1">
              <tr>
                <td><strong>{{ $t('total') }}</strong></td>
                <td class="num">{{ number(data.ponds_count) }}</td>
                <td class="num">{{ number(data.companies_count) }}</td>
                <td class="num">{{ number(data.total_area_ha) }} {{ $t('hectare') }}</td>
                <td class="num">{{ number(data.total_fish_count) }} {{ $t('pieces') }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </section>
    </template>
  </div>
</template>

<script>
import { computed, onMounted, ref } from 'vue';
import { i18nState, localeTag, t } from '../../i18n';
import { regionCoordinates } from '../../utils/regionCoordinates';

export default {
  name: 'OwnerCabinet',
  setup() {
    const data = ref(null);
    const loading = ref(true);
    const loadError = ref('');
    const regionFilter = ref('');
    const number = (value) => {
      if (value === null || value === undefined || value === '') return '0';
      return new Intl.NumberFormat(localeTag(i18nState.locale)).format(Number(value));
    };

    async function loadDashboard() {
      const res = await window.axios.get('/owner/dashboard');
      data.value = res.data;
    }

    const allRegionNames = computed(() => (
      Object.keys(regionCoordinates).sort((a, b) => a.localeCompare(b, 'uz'))
    ));

    const regionStatsByName = computed(() => {
      const map = {};
      (data.value?.by_region || []).forEach((row) => {
        map[row.region] = row;
      });
      return map;
    });

    const activeRegionRow = computed(() => {
      if (!regionFilter.value) return null;
      return regionStatsByName.value[regionFilter.value] || {
        region: regionFilter.value,
        ponds_count: 0,
        directors_count: 0,
        area_ha: 0,
        fish_count: 0,
      };
    });

    const tableRows = computed(() => {
      const stats = regionStatsByName.value;
      if (regionFilter.value) {
        const row = stats[regionFilter.value];
        return row ? [row] : [{
          region: regionFilter.value,
          ponds_count: 0,
          directors_count: 0,
          area_ha: 0,
          fish_count: 0,
        }];
      }
      return Object.values(stats).sort((a, b) => a.region.localeCompare(b.region, 'uz'));
    });

    onMounted(async () => {
      loading.value = true;
      loadError.value = '';
      try {
        await loadDashboard();
      } catch (e) {
        loadError.value = e.response?.data?.message || t('loadFailed');
      } finally {
        loading.value = false;
      }
    });

    return {
      data,
      loading,
      loadError,
      regionFilter,
      allRegionNames,
      activeRegionRow,
      tableRows,
      number,
    };
  },
};
</script>
