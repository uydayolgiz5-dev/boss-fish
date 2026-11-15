<template>
  <div class="owner-planner-page">
    <div class="page-head owner-planner-head">
      <div>
        <h1>{{ $t('ownerPlannerTitle') }}</h1>
        <p>{{ activeTabHint }}</p>
      </div>
    </div>

    <OwnerPlannerDaily v-if="section === 'daily'" />
    <OwnerPlannerWeekly v-else-if="section === 'weekly'" />
    <OwnerPlannerMonthly v-else-if="section === 'monthly'" />
    <OwnerPlannerYearly v-else />

    <nav class="owner-planner-hub-tabs owner-planner-hub-tabs--bottom panel" role="tablist">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        type="button"
        class="owner-planner-hub-tab"
        :class="{ 'owner-planner-hub-tab--active': section === tab.key }"
        role="tab"
        :aria-selected="section === tab.key"
        @click="section = tab.key"
      >
        {{ $t(tab.labelKey) }}
      </button>
    </nav>
  </div>
</template>

<script>
import { computed, ref } from 'vue';
import { t } from '../../i18n';
import OwnerPlannerDaily from './OwnerPlannerDaily.vue';
import OwnerPlannerWeekly from './OwnerPlannerWeekly.vue';
import OwnerPlannerMonthly from './OwnerPlannerMonthly.vue';
import OwnerPlannerYearly from './OwnerPlannerYearly.vue';

export default {
  components: { OwnerPlannerDaily, OwnerPlannerWeekly, OwnerPlannerMonthly, OwnerPlannerYearly },
  setup() {
    const section = ref('daily');
    const tabs = [
      { key: 'daily', labelKey: 'ownerPlannerTabDaily' },
      { key: 'weekly', labelKey: 'ownerPlannerTabWeekly' },
      { key: 'monthly', labelKey: 'ownerPlannerTabMonthly' },
      { key: 'yearly', labelKey: 'ownerPlannerTabYearly' },
    ];
    const activeTabHint = computed(() => {
      const active = tabs.find((tab) => tab.key === section.value);
      return active ? t(active.labelKey) : t('ownerPlannerHubHint');
    });

    return { section, tabs, activeTabHint };
  },
};
</script>
