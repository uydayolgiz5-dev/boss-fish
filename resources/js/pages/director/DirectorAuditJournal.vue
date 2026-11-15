<template>
  <div class="page">
    <header class="page-head page-head--toolbar">
      <div>
        <h1>{{ $t('auditJournal') }}</h1>
        <p class="muted">{{ $t('auditJournalHint') }}</p>
      </div>
      <router-link class="btn btn-outline btn-sm" :to="{ name: 'director-settings' }">
        ← {{ $t('settings') }}
      </router-link>
    </header>

    <div v-if="loading" class="muted">{{ $t('loading') }}</div>
    <div v-else-if="!rows.length" class="employee-empty">{{ $t('noData') }}</div>
    <div v-else class="audit-list">
      <article v-for="row in rows" :key="row.id" class="audit-row">
        <div class="audit-row__top">
          <strong>{{ row.title }}</strong>
          <time>{{ formatDateTime(row.performed_at || row.created_at) }}</time>
        </div>
        <p v-if="row.description" class="audit-row__desc">{{ row.description }}</p>
        <div class="audit-row__meta">
          <span v-if="row.user?.name">{{ row.user.name }}</span>
          <span v-if="row.pond?.name && !row.details?.company_event">{{ row.pond.name }}</span>
          <span class="audit-row__action">{{ row.action }}</span>
        </div>
      </article>
      <button
        v-if="hasMore"
        class="btn btn-outline"
        type="button"
        :disabled="loadingMore"
        @click="loadMore"
      >
        {{ loadingMore ? $t('loading') : $t('loadMore') }}
      </button>
    </div>
  </div>
</template>

<script>
import { onMounted, ref } from 'vue';
import { t } from '../../i18n';
import { formatDisplayDateTime } from '../../utils/date';

export default {
  setup() {
    const loading = ref(true);
    const loadingMore = ref(false);
    const rows = ref([]);
    const page = ref(1);
    const lastPage = ref(1);
    const hasMore = ref(false);

    function formatDateTime(value) {
      if (!value) return '—';
      const formatted = formatDisplayDateTime(value);
      return formatted || '—';
    }

    async function fetchPage(p, append = false) {
      const { data } = await window.axios.get('/director/audit-journal', { params: { page: p } });
      const list = data.data || [];
      rows.value = append ? rows.value.concat(list) : list;
      page.value = data.current_page || p;
      lastPage.value = data.last_page || 1;
      hasMore.value = page.value < lastPage.value;
    }

    async function load() {
      loading.value = true;
      try {
        await fetchPage(1, false);
      } catch (e) {
        window.alert(e.response?.data?.message || t('errorOccurred'));
      } finally {
        loading.value = false;
      }
    }

    async function loadMore() {
      if (!hasMore.value) return;
      loadingMore.value = true;
      try {
        await fetchPage(page.value + 1, true);
      } finally {
        loadingMore.value = false;
      }
    }

    onMounted(load);
    return { loading, loadingMore, rows, hasMore, formatDateTime, loadMore };
  },
};
</script>

<style scoped>
.audit-list {
  display: grid;
  gap: 0.75rem;
}
.audit-row {
  padding: 0.9rem 1rem;
  border: 1px solid rgba(15, 23, 42, 0.08);
  border-radius: 12px;
  background: #fff;
}
.audit-row__top {
  display: flex;
  justify-content: space-between;
  gap: 0.75rem;
  align-items: baseline;
}
.audit-row__top time {
  font-size: 0.8rem;
  color: #64748b;
  white-space: nowrap;
}
.audit-row__desc {
  margin: 0.35rem 0 0;
  color: #334155;
  font-size: 0.92rem;
}
.audit-row__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem 0.85rem;
  margin-top: 0.5rem;
  font-size: 0.8rem;
  color: #64748b;
}
.audit-row__action {
  font-family: ui-monospace, monospace;
}
</style>
