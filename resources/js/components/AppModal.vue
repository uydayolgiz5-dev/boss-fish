<template>
  <Teleport to="body">
    <div
      v-if="modelValue"
      class="modal-backdrop"
      @click.self="onBackdrop"
      @keydown.esc.prevent="onBackdrop"
    >
      <div
        class="modal"
        :class="{ 'modal-wide': wide || xl, 'modal-xl': xl, 'modal--has-footer': !!$slots.footer }"
        role="dialog"
        aria-modal="true"
        :aria-label="title || undefined"
      >
        <h2 v-if="title" class="modal-title">{{ title }}</h2>
        <div class="modal-body">
          <slot />
        </div>
        <div v-if="$slots.footer" class="modal-footer">
          <slot name="footer" />
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script>
import { watch, onBeforeUnmount } from 'vue';

let lockCount = 0;

function setBodyLock(lock) {
  if (typeof document === 'undefined') return;
  if (lock) {
    lockCount += 1;
    document.body.style.overflow = 'hidden';
    return;
  }
  lockCount = Math.max(0, lockCount - 1);
  if (lockCount === 0) {
    document.body.style.overflow = '';
  }
}

export default {
  name: 'AppModal',
  props: {
    modelValue: { type: Boolean, default: false },
    title: { type: String, default: '' },
    wide: { type: Boolean, default: false },
    xl: { type: Boolean, default: false },
    closeOnBackdrop: { type: Boolean, default: true },
  },
  emits: ['update:modelValue', 'close'],
  setup(props, { emit }) {
    function close() {
      emit('update:modelValue', false);
      emit('close');
    }

    function onBackdrop() {
      if (!props.closeOnBackdrop) return;
      close();
    }

    watch(
      () => props.modelValue,
      (open, wasOpen) => {
        if (open && !wasOpen) setBodyLock(true);
        if (!open && wasOpen) setBodyLock(false);
      },
      { immediate: true },
    );

    onBeforeUnmount(() => {
      if (props.modelValue) setBodyLock(false);
    });

    return { onBackdrop, close };
  },
};
</script>
