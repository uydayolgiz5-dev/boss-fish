<template>
  <DirectorShell>
    <div class="page director-boss-chat">
      <header class="page-head">
        <div>
          <h1>{{ $t('bossChatTitle') }}</h1>
          <p class="muted">{{ $t('bossChatClientHint') }}</p>
        </div>
      </header>

      <div class="panel">
        <BossCompanyChat
          :messages="messages"
          :loading="loading"
          :sending="sending"
          :error="error"
          :hint="$t('bossChatClientHint')"
          :placeholder="$t('bossChatReplyPlaceholder')"
          :my-label="$t('bossChatYou')"
          :their-label="$t('bossMessageTitle')"
          @send="send"
        />
      </div>
    </div>
  </DirectorShell>
</template>

<script>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import BossCompanyChat from '../../components/BossCompanyChat.vue';
import DirectorShell from '../../components/DirectorShell.vue';
import { t } from '../../i18n';

export default {
  components: { DirectorShell, BossCompanyChat },
  setup() {
    const messages = ref([]);
    const loading = ref(true);
    const sending = ref(false);
    const error = ref('');
    let pollTimer = null;

    async function load() {
      loading.value = true;
      error.value = '';
      try {
        const { data } = await window.axios.get('/director/boss-chat');
        messages.value = data.messages || [];
      } catch (e) {
        error.value = e.response?.data?.message || t('errorOccurred');
      } finally {
        loading.value = false;
      }
    }

    async function refreshQuiet() {
      try {
        const { data } = await window.axios.get('/director/boss-chat');
        messages.value = data.messages || [];
      } catch (e) {
        /* ignore poll errors */
      }
    }

    async function send(payload) {
      const body = typeof payload === 'string' ? payload : (payload?.body || '');
      const image = typeof payload === 'object' ? payload?.image : null;
      if ((!body && !image) || sending.value) return;
      sending.value = true;
      error.value = '';
      try {
        const form = new FormData();
        if (body) form.append('body', body);
        if (image) form.append('image', image);
        const { data } = await window.axios.post('/director/boss-chat', form, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });
        if (data?.item) messages.value = [...messages.value, data.item];
        else await refreshQuiet();
      } catch (e) {
        error.value = e.response?.data?.message
          || Object.values(e.response?.data?.errors || {})?.[0]?.[0]
          || t('errorOccurred');
      } finally {
        sending.value = false;
      }
    }

    onMounted(() => {
      load();
      pollTimer = window.setInterval(refreshQuiet, 15000);
    });
    onBeforeUnmount(() => {
      if (pollTimer) clearInterval(pollTimer);
    });

    return {
      messages,
      loading,
      sending,
      error,
      send,
    };
  },
};
</script>

<style scoped>
.director-boss-chat .page-head {
  margin-bottom: 0.85rem;
}

.director-boss-chat .page-head h1 {
  margin: 0 0 0.25rem;
}

.director-boss-chat .page-head .muted {
  margin: 0;
  font-size: 0.9rem;
}
</style>
