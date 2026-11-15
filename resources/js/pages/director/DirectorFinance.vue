<template>
  <DirectorShell>
    <div class="branch-grid">
      <button
        v-if="canEntry"
        type="button"
        class="branch-card branch-finance-entry"
        @click="go('director-finance-kirim')"
      >
        <div class="boss-hub-card__body">
          <HubSectionMark section="expense" />
          <h2>{{ $t('financeHubEntry') }}</h2>
        </div>
        <span class="boss-nav-card__arrow" aria-hidden="true"><svg viewBox="0 0 12 12" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2l4 4-4 4"/></svg></span>
      </button>
      <button
        v-if="canReports"
        type="button"
        class="branch-card branch-finance-report"
        @click="go('director-finance-overview')"
      >
        <div class="boss-hub-card__body">
          <HubSectionMark section="report" />
          <h2>{{ $t('financeHubReports') }}</h2>
        </div>
        <span class="boss-nav-card__arrow" aria-hidden="true"><svg viewBox="0 0 12 12" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2l4 4-4 4"/></svg></span>
      </button>
    </div>
  </DirectorShell>
</template>

<script>
import { computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import DirectorShell from '../../components/DirectorShell.vue';
import HubSectionMark from '../../components/HubSectionMark.vue';
import auth from '../../auth';

export default {
  components: { DirectorShell, HubSectionMark },
  setup() {
    const router = useRouter();
    const canEntry = computed(() => auth.can('finance'));
    const canReports = computed(() => auth.can('finance_reports'));

    function go(name) {
      router.push({ name });
    }

    onMounted(() => {
      if (canEntry.value && !canReports.value) {
        router.replace({ name: 'director-finance-kirim' });
        return;
      }
      if (!canEntry.value && canReports.value) {
        router.replace({ name: 'director-finance-overview' });
      }
    });

    return { go, canEntry, canReports };
  },
};
</script>
