<template>
  <div class="director-shell crm-shell">
    <div class="director-inner">
      <header v-if="isOwner && actingDirector" class="director-top owner-acting-top">
        <div class="director-brand">
          <BrandLogo :variant="logoVariant" compact />
          <div class="director-brand-meta">
            <strong>{{ $t('ownerActingAsDirector', { name: actingDirector.name }) }}</strong>
            <small v-if="actingDirector.phone">{{ actingDirector.phone }}</small>
          </div>
        </div>
        <router-link
          v-if="!isActingHubPage"
          class="btn btn-outline btn-sm"
          :to="{ name: 'owner-companies' }"
          @click="clearActing"
        >
          {{ $t('companies') }}
        </router-link>
      </header>

      <!-- Kun qoldi / ogohlantirishlar — CrmTopbar da -->

      <Transition name="flash-toast">
        <div v-if="flash.visible" class="flash-toast" role="status" aria-live="polite">
          <span class="flash-toast-dot" aria-hidden="true" />
          <span>{{ flash.message || $t('saved') }}</span>
        </div>
      </Transition>

      <slot />
    </div>
  </div>
</template>

<script>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import auth from '../auth';
import flash from '../flash';
import BrandLogo from './BrandLogo.vue';
import { clearActingDirector, getActingDirector } from '../utils/ownerContext';
import { themeState } from '../utils/theme';

export default {
  components: { BrandLogo },
  setup() {
    const route = useRoute();
    const user = computed(() => auth.state.user);
    const isOwner = computed(() => auth.isOwner());
    const logoVariant = computed(() => (themeState.theme === 'dark' ? 'dark' : 'light'));
    const actingDirector = ref(getActingDirector());
    const isActingHubPage = computed(() => route.name === 'owner-director-hub');

    function clearActing() {
      clearActingDirector();
    }

    onMounted(() => {
      actingDirector.value = getActingDirector();
    });

    return {
      user,
      isOwner,
      logoVariant,
      actingDirector,
      isActingHubPage,
      clearActing,
      flash: flash.state,
    };
  },
};
</script>
