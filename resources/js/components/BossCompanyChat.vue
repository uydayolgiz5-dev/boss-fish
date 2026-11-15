<template>
  <div
    class="boss-chat"
    :class="{ 'boss-chat--tall': tall, 'boss-chat--lock': lockViewport }"
    data-chat-lock
  >
    <p v-if="hint" class="boss-chat__hint">{{ hint }}</p>

    <div ref="scroller" class="boss-chat__thread">
      <div v-if="loading" class="boss-chat__empty">{{ $t('loading') }}</div>
      <div v-else-if="!messages.length" class="boss-chat__empty">{{ $t('bossChatEmpty') }}</div>
      <template v-else>
        <div
          v-for="(row, index) in rows"
          :key="row.msg.id"
          class="boss-chat__row"
          :class="[
            row.msg.mine ? 'is-mine' : 'is-theirs',
            row.showName ? 'has-name' : 'is-continued',
          ]"
        >
          <div v-if="!row.msg.mine" class="boss-chat__avatar" aria-hidden="true">
            <span v-if="row.showName">{{ avatarLetter(theirLabel) }}</span>
          </div>

          <div class="boss-chat__stack">
            <div v-if="!row.msg.mine && row.showName" class="boss-chat__name">
              {{ theirLabel }}
            </div>

            <div class="boss-chat__bubble" :class="{ 'has-image': !!row.msg.image_url }">
              <a
                v-if="row.msg.image_url"
                class="boss-chat__image"
                :href="row.msg.image_url"
                target="_blank"
                rel="noopener"
              >
                <img :src="row.msg.image_url" alt="" />
              </a>
              <p v-if="row.msg.body">{{ row.msg.body }}</p>
            </div>

            <time v-if="row.showTime" class="boss-chat__time">{{ formatTime(row.msg.created_at) }}</time>
          </div>
        </div>
      </template>
    </div>

    <div v-if="previewUrl" class="boss-chat__preview">
      <img :src="previewUrl" alt="" />
      <button type="button" class="boss-chat__preview-remove" :aria-label="$t('removePhoto')" @click="clearImage">
        ×
      </button>
    </div>

    <form class="boss-chat__composer" @submit.prevent="onSubmit">
      <input
        ref="fileInput"
        type="file"
        accept="image/jpeg,image/png,image/webp,image/jpg"
        class="boss-chat__file"
        @change="onFilePicked"
      />
      <button
        class="boss-chat__attach"
        type="button"
        :disabled="sending"
        :aria-label="$t('bossChatAttachImage')"
        @click="fileInput?.click()"
      >
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <rect x="3" y="3" width="18" height="18" rx="2" />
          <circle cx="8.5" cy="8.5" r="1.5" />
          <path d="M21 15l-5-5L5 21" />
        </svg>
      </button>
      <textarea
        ref="input"
        v-model.trim="draft"
        rows="1"
        maxlength="2000"
        data-chat-lock
        :placeholder="placeholder"
        :disabled="sending"
        @keydown.enter.exact.prevent="onSubmit"
        @input="autoGrow"
        @focus="scrollBottom"
      />
      <button
        class="boss-chat__send"
        type="submit"
        :disabled="sending || (!draft && !imageFile)"
        :aria-label="$t('companySendMessageAction')"
      >
        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true">
          <path d="M2.01 21 23 12 2.01 3 2 10l15 2-15 2z" />
        </svg>
      </button>
    </form>
    <p v-if="error" class="error boss-chat__error">{{ error }}</p>
  </div>
</template>

<script>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { localeTag, t } from '../i18n';

export default {
  name: 'BossCompanyChat',
  props: {
    messages: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    sending: { type: Boolean, default: false },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    myLabel: { type: String, default: 'BOSS' },
    theirLabel: { type: String, default: '' },
    tall: { type: Boolean, default: false },
    lockViewport: { type: Boolean, default: false },
  },
  emits: ['send'],
  setup(props, { emit }) {
    const draft = ref('');
    const scroller = ref(null);
    const input = ref(null);
    const fileInput = ref(null);
    const imageFile = ref(null);
    const previewUrl = ref('');

    const rows = computed(() => {
      const list = props.messages || [];
      return list.map((msg, index) => {
        const prev = list[index - 1];
        const next = list[index + 1];
        const sameAsPrev = prev && !!prev.mine === !!msg.mine;
        const sameAsNext = next && !!next.mine === !!msg.mine;
        return {
          msg,
          showName: !msg.mine && !sameAsPrev,
          showTime: !sameAsNext,
        };
      });
    });

    function avatarLetter(label) {
      const text = String(label || '?').trim();
      return (text[0] || '?').toUpperCase();
    }

    function formatTime(iso) {
      if (!iso) return '';
      try {
        return new Intl.DateTimeFormat(localeTag(), {
          hour: '2-digit',
          minute: '2-digit',
        }).format(new Date(iso));
      } catch (e) {
        return String(iso).slice(11, 16);
      }
    }

    async function scrollBottom() {
      await nextTick();
      const el = scroller.value;
      if (el) el.scrollTop = el.scrollHeight;
    }

    function autoGrow(event) {
      const el = event?.target || input.value;
      if (!el) return;
      el.style.height = 'auto';
      el.style.height = `${Math.min(el.scrollHeight, 120)}px`;
    }

    function clearImage() {
      if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
      previewUrl.value = '';
      imageFile.value = null;
      if (fileInput.value) fileInput.value.value = '';
    }

    function onFilePicked(event) {
      const file = event.target.files?.[0];
      clearImage();
      if (!file) return;
      imageFile.value = file;
      previewUrl.value = URL.createObjectURL(file);
    }

    function onSubmit() {
      if (props.sending) return;
      if (!draft.value && !imageFile.value) return;
      emit('send', {
        body: draft.value || '',
        image: imageFile.value || null,
      });
      draft.value = '';
      clearImage();
      nextTick(() => {
        if (input.value) {
          input.value.style.height = 'auto';
          input.value.focus();
        }
      });
    }

    watch(() => props.messages.length, () => scrollBottom());
    watch(() => props.loading, (v) => { if (!v) scrollBottom(); });

    onMounted(() => {
      scrollBottom();
      if (!props.lockViewport) {
        input.value?.focus?.();
      }
    });

    onBeforeUnmount(() => {
      if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
    });

    return {
      draft,
      scroller,
      input,
      fileInput,
      imageFile,
      previewUrl,
      rows,
      avatarLetter,
      formatTime,
      autoGrow,
      onFilePicked,
      clearImage,
      onSubmit,
      t,
    };
  },
};
</script>

<style scoped>
.boss-chat {
  display: flex;
  flex-direction: column;
  gap: 0.7rem;
  min-height: 0;
  flex: 1;
}

.boss-chat--tall,
.boss-chat--lock {
  height: 100%;
  gap: 0;
}

.boss-chat--tall .boss-chat__thread,
.boss-chat--lock .boss-chat__thread {
  min-height: 0;
  max-height: none;
  flex: 1 1 auto;
  border: 0;
  border-radius: 0;
  background: transparent;
}

.boss-chat--lock .boss-chat__composer,
.boss-chat--tall .boss-chat__composer {
  margin: 0.45rem 0.55rem 0.55rem;
  flex: 0 0 auto;
}

.boss-chat--lock .boss-chat__preview,
.boss-chat--tall .boss-chat__preview {
  margin: 0.45rem 0.75rem 0;
}

.boss-chat--lock .boss-chat__error,
.boss-chat--tall .boss-chat__error {
  margin: 0 0.75rem 0.45rem;
}

.boss-chat__hint {
  margin: 0;
  font-size: 0.84rem;
  line-height: 1.4;
  color: #667085;
}

.boss-chat__thread {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  min-height: 260px;
  max-height: min(54vh, 440px);
  overflow: auto;
  padding: 0.85rem 0.7rem;
  border: 1px solid #eceff3;
  border-radius: 16px;
  background:
    radial-gradient(circle at top left, rgba(13, 107, 63, 0.04), transparent 42%),
    #fafbfc;
}

.boss-chat__empty {
  margin: auto;
  padding: 1.5rem 0.75rem;
  text-align: center;
  color: #98a2b3;
  font-size: 0.9rem;
}

.boss-chat__row {
  display: flex;
  align-items: flex-end;
  gap: 0.45rem;
  max-width: 100%;
}

.boss-chat__row.is-mine { justify-content: flex-end; }
.boss-chat__row.is-theirs { justify-content: flex-start; }
.boss-chat__row.is-continued { margin-top: 0.08rem; }
.boss-chat__row.has-name { margin-top: 0.7rem; }
.boss-chat__row.has-name:first-child { margin-top: 0; }

.boss-chat__avatar {
  flex: 0 0 28px;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  background: #e8f1ff;
  color: #026aa2;
  font-size: 0.72rem;
  font-weight: 800;
}

.boss-chat__row.is-continued .boss-chat__avatar { visibility: hidden; }

.boss-chat__stack {
  display: flex;
  flex-direction: column;
  max-width: min(78%, 28rem);
  min-width: 0;
}

.boss-chat__row.is-mine .boss-chat__stack { align-items: flex-end; }
.boss-chat__row.is-theirs .boss-chat__stack { align-items: flex-start; }

.boss-chat__name {
  margin: 0 0 0.2rem 0.35rem;
  font-size: 0.72rem;
  font-weight: 700;
  color: #667085;
}

.boss-chat__bubble {
  padding: 0.55rem 0.8rem;
  border-radius: 18px;
  max-width: 100%;
  overflow: hidden;
}

.boss-chat__bubble.has-image {
  padding: 0.35rem;
}

.boss-chat__bubble p {
  margin: 0;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
  font-size: 0.94rem;
  line-height: 1.35;
}

.boss-chat__bubble.has-image p {
  padding: 0.4rem 0.55rem 0.25rem;
}

.boss-chat__image {
  display: block;
  border-radius: 14px;
  overflow: hidden;
}

.boss-chat__image img {
  display: block;
  width: 100%;
  max-width: 240px;
  max-height: 280px;
  object-fit: cover;
}

.boss-chat__row.is-mine .boss-chat__bubble {
  background: #0d6b3f;
  color: #fff;
  border-bottom-right-radius: 6px;
}

.boss-chat__row.is-theirs .boss-chat__bubble {
  background: #fff;
  color: #101828;
  border: 1px solid #e8ebf0;
  border-bottom-left-radius: 6px;
}

.boss-chat__time {
  margin-top: 0.18rem;
  padding: 0 0.35rem;
  font-size: 0.68rem;
  color: #98a2b3;
  line-height: 1;
}

.boss-chat__preview {
  position: relative;
  width: 5.5rem;
  height: 5.5rem;
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid #e4e7ec;
}

.boss-chat__preview img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.boss-chat__preview-remove {
  position: absolute;
  top: 0.2rem;
  right: 0.2rem;
  width: 1.4rem;
  height: 1.4rem;
  border: 0;
  border-radius: 50%;
  background: rgba(15, 23, 42, 0.7);
  color: #fff;
  font-size: 1rem;
  line-height: 1;
  cursor: pointer;
}

.boss-chat__composer {
  display: grid;
  grid-template-columns: auto 1fr auto;
  gap: 0.35rem;
  align-items: end;
  padding: 0.35rem;
  border: 1px solid #e4e7ec;
  border-radius: 999px;
  background: #fff;
}

.boss-chat__file {
  display: none;
}

.boss-chat__attach {
  width: 2.45rem;
  height: 2.45rem;
  border: 0;
  border-radius: 50%;
  background: #f2f4f7;
  color: #344054;
  display: grid;
  place-items: center;
  cursor: pointer;
}

.boss-chat__composer textarea {
  width: 100%;
  min-height: 2.4rem;
  max-height: 7.5rem;
  resize: none;
  padding: 0.55rem 0.55rem;
  border: 0;
  border-radius: 999px;
  font: inherit;
  line-height: 1.35;
  background: transparent;
  outline: none;
}

.boss-chat__send {
  width: 2.45rem;
  height: 2.45rem;
  border: 0;
  border-radius: 50%;
  background: #0d6b3f;
  color: #fff;
  display: grid;
  place-items: center;
  cursor: pointer;
  flex-shrink: 0;
}

.boss-chat__send:disabled {
  opacity: 0.4;
  cursor: default;
}

.boss-chat__error {
  margin: 0;
}
</style>
