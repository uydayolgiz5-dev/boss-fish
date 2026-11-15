<template>
  <div class="page owner-page owner-experiences">
    <header class="page-head">
      <div class="page-head__text">
        <h1>{{ currentFolder ? currentFolder.title : $t('ownerExperienceTitle') }}</h1>
        <p>{{ pageHint }}</p>
      </div>
    </header>

    <nav v-if="currentFolder" class="ox-back">
      <button class="btn btn-outline btn-sm" type="button" @click="goBack">
        ← {{ parentLabel }}
      </button>
    </nav>

    <div v-if="loading" class="muted">{{ $t('loading') }}</div>
    <div v-else-if="error" class="error">{{ error }}</div>

    <template v-else>
      <!-- Ichki bo‘lim: faqat matn -->
      <section v-if="isLeaf" class="panel ox-note">
        <input
          v-model.trim="titleDraft"
          class="ox-note__title"
          type="text"
          maxlength="180"
          :placeholder="$t('ownerExperienceFolderName')"
        >
        <textarea
          v-model="noteDraft"
          class="ox-note__body"
          rows="12"
          :placeholder="$t('ownerExperienceNotesPlaceholder')"
        />
        <div class="ox-note__foot">
          <span v-if="dirty" class="ox-note__hint">{{ $t('ownerExperienceUnsaved') }}</span>
          <span v-else-if="justSaved" class="ox-note__hint is-ok">{{ $t('ownerExperienceSaved') }}</span>
          <button class="btn btn-primary" type="button" :disabled="saving || !dirty" @click="saveCurrent">
            {{ saving ? $t('loading') : $t('save') }}
          </button>
        </div>
        <button class="ox-delete-link" type="button" @click="showDelete = true">
          {{ $t('ownerExperienceDeleteLink') }}
        </button>
      </section>

      <!-- Ildiz yoki asosiy papka: faqat ro‘yxat -->
      <section v-else class="panel ox-list-panel">
        <ul v-if="folders.length" class="ox-list">
          <li v-for="folder in folders" :key="folder.id">
            <button type="button" class="ox-list__row" @click="openFolder(folder.id)">
              <strong>{{ folder.title }}</strong>
              <span aria-hidden="true">›</span>
            </button>
          </li>
        </ul>
        <p v-else class="ox-empty">
          {{ isContainer ? $t('ownerExperienceNoSubs') : $t('ownerExperienceEmpty') }}
        </p>

        <form class="ox-add" @submit.prevent="createFolder">
          <input
            v-model.trim="createTitle"
            type="text"
            maxlength="180"
            :placeholder="isContainer ? $t('ownerExperienceAddSub') : $t('ownerExperienceAddFolder')"
          >
          <button class="btn btn-primary" type="submit" :disabled="creating || !createTitle">
            {{ creating ? $t('loading') : $t('add') }}
          </button>
        </form>

        <button
          v-if="isContainer"
          class="ox-delete-link"
          type="button"
          @click="showDelete = true"
        >
          {{ $t('ownerExperienceDeleteLink') }}
        </button>
      </section>

      <div v-if="showDelete && currentFolder" class="ox-modal-backdrop" @click.self="closeDelete">
        <div class="ox-modal panel" role="dialog" aria-modal="true">
          <h3>{{ $t('ownerExperienceDeleteTitle') }}</h3>
          <p class="ox-modal__text">
            {{ $t('ownerExperienceDeleteTypeHint', { title: currentFolder.title }) }}
          </p>
          <input
            v-model.trim="deleteConfirm"
            type="text"
            class="ox-modal__input"
            :placeholder="currentFolder.title"
            autofocus
          >
          <div class="ox-modal__actions">
            <button class="btn btn-outline" type="button" @click="closeDelete">{{ $t('cancel') }}</button>
            <button
              class="btn btn-primary"
              type="button"
              :disabled="deleting || deleteConfirm !== currentFolder.title"
              @click="removeCurrent"
            >
              {{ deleting ? $t('loading') : $t('delete') }}
            </button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script>
import { computed, onMounted, ref, watch } from 'vue';
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import { t } from '../../i18n';

export default {
  name: 'OwnerExperiences',
  setup() {
    const route = useRoute();
    const router = useRouter();

    const loading = ref(true);
    const error = ref('');
    const folders = ref([]);
    const breadcrumb = ref([]);
    const currentFolder = ref(null);

    const titleDraft = ref('');
    const noteDraft = ref('');
    const titleSaved = ref('');
    const noteSaved = ref('');
    const saving = ref(false);
    const justSaved = ref(false);
    let savedTimer = null;

    const createTitle = ref('');
    const creating = ref(false);
    const showDelete = ref(false);
    const deleteConfirm = ref('');
    const deleting = ref(false);

    const currentId = computed(() => {
      const raw = route.params.folderId;
      return raw ? Number(raw) : null;
    });

    /** Asosiy papka (Baliqchilik): faqat ichki ro‘yxat */
    const isContainer = computed(() => breadcrumb.value.length === 1);
    /** Ichki bo‘lim: faqat matn */
    const isLeaf = computed(() => breadcrumb.value.length >= 2);

    const dirty = computed(() => (
      isLeaf.value
      && (titleDraft.value !== titleSaved.value || noteDraft.value !== noteSaved.value)
    ));

    const pageHint = computed(() => {
      if (isLeaf.value) return t('ownerExperienceLeafHint');
      if (isContainer.value) return t('ownerExperienceFolderHint');
      return t('ownerExperienceHint');
    });

    const parentLabel = computed(() => {
      if (breadcrumb.value.length > 1) {
        return breadcrumb.value[breadcrumb.value.length - 2].title;
      }
      return t('ownerExperienceRoot');
    });

    async function load() {
      loading.value = true;
      error.value = '';
      try {
        if (currentId.value) {
          const { data } = await window.axios.get(`/owner/experiences/${currentId.value}`);
          currentFolder.value = data.folder;
          folders.value = data.folder?.children || [];
          breadcrumb.value = data.breadcrumb || [];
          titleDraft.value = data.folder?.title || '';
          noteDraft.value = data.folder?.body || '';
          titleSaved.value = titleDraft.value;
          noteSaved.value = noteDraft.value;
        } else {
          const { data } = await window.axios.get('/owner/experiences', {
            params: { parent_id: null },
          });
          currentFolder.value = null;
          folders.value = data.folders || [];
          breadcrumb.value = [];
          titleDraft.value = '';
          noteDraft.value = '';
          titleSaved.value = '';
          noteSaved.value = '';
        }
        justSaved.value = false;
        createTitle.value = '';
      } catch (e) {
        error.value = e.response?.data?.message
          || Object.values(e.response?.data?.errors || {})?.[0]?.[0]
          || t('errorOccurred');
        currentFolder.value = null;
        folders.value = [];
      } finally {
        loading.value = false;
      }
    }

    function openFolder(id) {
      if (dirty.value && !window.confirm(t('ownerExperienceLeaveUnsaved'))) return;
      router.push({ name: 'owner-experience-folder', params: { folderId: id } });
    }

    function goBack() {
      if (dirty.value && !window.confirm(t('ownerExperienceLeaveUnsaved'))) return;
      const parentId = currentFolder.value?.parent_id;
      if (parentId) {
        router.push({ name: 'owner-experience-folder', params: { folderId: parentId } });
      } else {
        router.push({ name: 'owner-experiences' });
      }
    }

    async function createFolder() {
      if (!createTitle.value || isLeaf.value) return;
      creating.value = true;
      try {
        await window.axios.post('/owner/experiences', {
          title: createTitle.value,
          parent_id: currentId.value || null,
        });
        createTitle.value = '';
        await load();
      } catch (e) {
        window.alert(e.response?.data?.message
          || Object.values(e.response?.data?.errors || {})?.[0]?.[0]
          || t('errorOccurred'));
      } finally {
        creating.value = false;
      }
    }

    async function saveCurrent() {
      if (!currentFolder.value || !isLeaf.value || !dirty.value) return;
      if (!titleDraft.value) {
        window.alert(t('ownerExperienceFolderName'));
        return;
      }
      saving.value = true;
      try {
        const { data } = await window.axios.put(`/owner/experiences/${currentFolder.value.id}`, {
          title: titleDraft.value,
          body: noteDraft.value,
        });
        titleSaved.value = titleDraft.value;
        noteSaved.value = noteDraft.value;
        if (data.folder) {
          currentFolder.value = { ...currentFolder.value, ...data.folder };
        }
        justSaved.value = true;
        clearTimeout(savedTimer);
        savedTimer = setTimeout(() => { justSaved.value = false; }, 1800);
      } catch (e) {
        window.alert(e.response?.data?.message
          || Object.values(e.response?.data?.errors || {})?.[0]?.[0]
          || t('errorOccurred'));
      } finally {
        saving.value = false;
      }
    }

    function closeDelete() {
      showDelete.value = false;
      deleteConfirm.value = '';
    }

    async function removeCurrent() {
      if (!currentFolder.value) return;
      if (deleteConfirm.value !== currentFolder.value.title) return;
      deleting.value = true;
      try {
        const folder = currentFolder.value;
        await window.axios.delete(`/owner/experiences/${folder.id}`);
        closeDelete();
        const parentId = folder.parent_id;
        if (parentId) {
          await router.push({ name: 'owner-experience-folder', params: { folderId: parentId } });
        } else {
          await router.push({ name: 'owner-experiences' });
        }
      } catch (e) {
        window.alert(e.response?.data?.message
          || Object.values(e.response?.data?.errors || {})?.[0]?.[0]
          || t('errorOccurred'));
      } finally {
        deleting.value = false;
      }
    }

    watch(() => route.params.folderId, () => {
      closeDelete();
      load();
    });
    onMounted(load);

    onBeforeRouteLeave((to, from, next) => {
      if (dirty.value && !window.confirm(t('ownerExperienceLeaveUnsaved'))) {
        next(false);
        return;
      }
      next();
    });

    return {
      loading,
      error,
      folders,
      currentFolder,
      isContainer,
      isLeaf,
      titleDraft,
      noteDraft,
      dirty,
      saving,
      justSaved,
      createTitle,
      creating,
      showDelete,
      deleteConfirm,
      deleting,
      pageHint,
      parentLabel,
      openFolder,
      goBack,
      createFolder,
      saveCurrent,
      closeDelete,
      removeCurrent,
    };
  },
};
</script>

<style scoped>
.owner-experiences .page-head {
  margin-bottom: 0.75rem;
}
.owner-experiences .page-head h1 {
  margin: 0 0 0.2rem;
}
.owner-experiences .page-head p {
  margin: 0;
  color: #667085;
}

.ox-back {
  margin: 0 0 0.85rem;
}

.ox-note {
  margin-bottom: 1rem;
}
.ox-note__title {
  width: 100%;
  margin-bottom: 0.65rem;
  padding: 0.55rem 0.75rem;
  border: 1px solid #d0d5dd;
  border-radius: 10px;
  font-size: 1.05rem;
  font-weight: 700;
  color: #0f172a;
}
.ox-note__body {
  width: 100%;
  min-height: 14rem;
  padding: 0.85rem 1rem;
  border: 1px solid #d0d5dd;
  border-radius: 12px;
  font-size: 0.98rem;
  line-height: 1.55;
  color: #0f172a;
  resize: vertical;
}
.ox-note__title:focus,
.ox-note__body:focus {
  outline: none;
  border-color: #0090f0;
  box-shadow: 0 0 0 3px rgba(0, 144, 240, 0.14);
}
.ox-note__foot {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.55rem;
  margin-top: 0.75rem;
}
.ox-note__foot .btn-primary {
  margin-left: auto;
}
.ox-note__hint {
  font-size: 0.85rem;
  font-weight: 650;
  color: #b54708;
}
.ox-note__hint.is-ok {
  color: #027a48;
}

.ox-list-panel {
  padding: 0;
  overflow: hidden;
}
.ox-list {
  list-style: none;
  margin: 0;
  padding: 0;
}
.ox-list__row {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.95rem 1rem;
  border: none;
  border-bottom: 1px solid #eef2f6;
  background: #fff;
  text-align: left;
  cursor: pointer;
  color: #0f172a;
}
.ox-list__row:hover {
  background: #f8fafc;
}
.ox-list__row strong {
  font-size: 0.98rem;
}
.ox-list__row span {
  color: #98a2b3;
  font-size: 1.25rem;
}
.ox-empty {
  margin: 0;
  padding: 1.15rem 1rem;
  color: #667085;
  text-align: center;
}

.ox-add {
  display: flex;
  gap: 0.5rem;
  padding: 0.75rem;
  border-top: 1px solid #e4e7ec;
  background: #f8fafc;
}
.ox-add input {
  flex: 1 1 auto;
  min-width: 0;
  padding: 0.55rem 0.7rem;
  border: 1px solid #d0d5dd;
  border-radius: 10px;
}

.ox-delete-link {
  display: block;
  width: 100%;
  margin: 0;
  padding: 0.65rem 1rem 0.85rem;
  border: none;
  background: transparent;
  color: #98a2b3;
  font-size: 0.82rem;
  text-align: center;
  cursor: pointer;
  text-decoration: underline;
  text-underline-offset: 2px;
}
.ox-delete-link:hover {
  color: #b42318;
}

.ox-modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 40;
  display: grid;
  place-items: center;
  padding: 1rem;
  background: rgba(15, 23, 42, 0.45);
}
.ox-modal {
  width: min(100%, 400px);
  padding: 1.15rem 1.2rem;
}
.ox-modal h3 {
  margin: 0 0 0.5rem;
}
.ox-modal__text {
  margin: 0 0 0.75rem;
  color: #667085;
  font-size: 0.9rem;
  line-height: 1.45;
}
.ox-modal__input {
  width: 100%;
  padding: 0.55rem 0.7rem;
  border: 1px solid #d0d5dd;
  border-radius: 10px;
}
.ox-modal__actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 1rem;
}

@media (max-width: 720px) {
  .ox-note__foot .btn-primary {
    width: 100%;
    margin-left: 0;
  }
  .ox-add {
    flex-direction: column;
  }
}
</style>
