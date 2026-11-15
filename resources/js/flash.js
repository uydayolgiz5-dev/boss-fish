import { reactive } from 'vue';

const state = reactive({
  message: '',
  visible: false,
});

let hideTimer = null;

export function showSaved(message = '') {
  if (hideTimer) {
    clearTimeout(hideTimer);
    hideTimer = null;
  }

  state.message = message;
  state.visible = true;

  hideTimer = setTimeout(() => {
    state.visible = false;
    hideTimer = null;
  }, 4200);
}

export function clearFlash() {
  if (hideTimer) {
    clearTimeout(hideTimer);
    hideTimer = null;
  }
  state.visible = false;
  state.message = '';
}

export default { state, showSaved, clearFlash };
