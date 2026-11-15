<template>
  <div
    class="finance-quick-entry finance-quick-entry--simple"
    :class="{
      'finance-quick-entry--has-pick': !!selectedCategory,
      'finance-quick-entry--quiz': showQuiz,
    }"
  >
    <!-- Savol + bitta bosishda tanlash: umumiy yoki kol (hisobot uslubi) -->
    <div
      v-if="showQuiz"
      class="warehouse-pond-pick finance-expense-quiz finance-report-quiz"
      role="radiogroup"
      :aria-label="$t('financeExpenseQuizQuestion')"
    >
      <p class="finance-report-quiz__q">{{ $t('financeExpenseQuizQuestion') }}</p>
      <p class="finance-report-quiz__hint">{{ $t('financeExpenseQuizHint') }}</p>

      <button
        type="button"
        role="radio"
        class="warehouse-pond-chip warehouse-pond-chip--all finance-expense-quiz__answer"
        :aria-checked="false"
        @click="chooseFarm"
      >
        <strong>{{ $t('financeExpenseQuizFarm') }}</strong>
        <small>{{ $t('financeExpenseQuizFarmHint') }}</small>
      </button>

      <template v-if="openPonds.length">
        <p class="warehouse-pond-pick__or">{{ $t('financeExpenseQuizOneOr') }}</p>
        <div class="warehouse-pond-pick__list finance-section-stack">
          <button
            v-for="pond in openPonds"
            :key="pond.id"
            type="button"
            role="radio"
            class="warehouse-pond-chip warehouse-pond-chip--all"
            :aria-checked="false"
            @click="choosePond(pond.id)"
          >
            <strong>{{ pond.name }}</strong>
            <small>{{ $t('financeExpenseQuizOneHint') }}</small>
          </button>
        </div>
      </template>
      <p v-else class="finance-expense-quiz__empty">{{ $t('noPondYet') }}</p>
    </div>

    <!-- 2-qadam: bo‘lim tanlash -->
    <template v-else-if="scopeReady && !selectedCategory">
      <div v-if="!isScopeLocked" class="finance-expense-quiz-picked">
        <div class="finance-expense-quiz-picked__text">
          <span class="finance-expense-quiz-picked__label">{{ $t('financeExpenseQuizPickedLabel') }}</span>
          <strong>{{ scopeLabel }}</strong>
          <p class="finance-expense-quiz-picked__info">
            {{ scopeMode === 'farm' ? $t('financeExpenseQuizWritingFarm') : $t('financeExpenseQuizWritingPond', { name: scopeLabel }) }}
          </p>
        </div>
      </div>

      <div
        class="warehouse-pond-pick finance-expense-quiz finance-report-quiz"
        role="radiogroup"
        :aria-label="$t('financeExpenseSectionQuestion')"
      >
        <p class="finance-report-quiz__q">{{ $t('financeExpenseSectionQuestion') }}</p>
        <p class="finance-report-quiz__hint">{{ $t('financeExpenseSectionHint') }}</p>

        <div class="warehouse-pond-pick__list finance-section-stack">
          <button
            v-for="tab in entryTabs"
            :key="tab.routeKey"
            type="button"
            role="radio"
            class="warehouse-pond-chip warehouse-pond-chip--all finance-expense-quiz__answer"
            :aria-checked="false"
            @click="$emit('select-category', tab.routeKey)"
          >
            <strong>{{ tab.label }}</strong>
            <small v-if="tab.hint">{{ tab.hint }}</small>
          </button>
        </div>
      </div>
    </template>

    <!-- 3-qadam: ma’lumot kiritish (yangi «oyna») -->
    <template v-else-if="scopeReady && selectedCategory">
      <div class="finance-expense-quiz-picked">
        <div class="finance-expense-quiz-picked__text">
          <span class="finance-expense-quiz-picked__label">{{ categoryTitle }}</span>
          <strong>{{ scopeLabel }}</strong>
          <p class="finance-expense-quiz-picked__info">
            {{
              scopeMode === 'farm'
                ? $t('financeExpenseQuizWritingFarmSection', { section: categoryTitle })
                : $t('financeExpenseQuizWritingPondSection', { name: scopeLabel, section: categoryTitle })
            }}
          </p>
        </div>
      </div>

      <p v-if="isWarehouseCategory" class="finance-quick-note">
        {{ $t('financeWarehouseUseIntake') }}
        <router-link :to="{ name: 'director-warehouse-intake' }">{{ $t('warehouseIntake') }}</router-link>
      </p>

      <form
        v-else
        class="finance-quick-form finance-quick-form--simple finance-quick-form--step"
        :class="{ 'finance-quick-form--fast': fast }"
        @submit.prevent="save"
      >
        <div class="field finance-quick-date-first">
          <label>{{ $t('date') }}</label>
          <input
            v-model="form.expense_date"
            type="date"
            required
            class="finance-quick-input"
            enterkeyhint="next"
            @focus="onDateFocus"
          />
        </div>

        <div class="finance-quick-grid finance-quick-grid--steps">
          <div class="finance-quick-field finance-quick-field--grow">
            <label>{{ $t('name') }}</label>
            <input
              v-model.trim="form.title"
              type="text"
              maxlength="255"
              required
              autocomplete="off"
              enterkeyhint="next"
              class="finance-quick-input"
              :placeholder="expenseNamePlaceholder"
            />
          </div>

          <div class="finance-quick-field">
            <label>{{ $t('financeExpenseCount') }}</label>
            <FormattedNumberInput
              v-model="form.quantity"
              :max-decimals="3"
              class="finance-quick-input"
              @input="syncMoneyFields('quantity')"
            />
          </div>
          <div class="finance-quick-field">
            <label>{{ $t('price') }}</label>
            <FormattedNumberInput
              v-model="form.unit_price"
              :max-decimals="2"
              class="finance-quick-input"
              @input="syncMoneyFields('unit_price')"
            />
          </div>
          <div class="finance-quick-field">
            <label>{{ $t('amount') }}</label>
            <FormattedNumberInput
              v-model="form.amount"
              :max-decimals="2"
              class="finance-quick-input finance-quick-input--amount"
              @input="syncMoneyFields('amount')"
            />
          </div>
        </div>

        <details v-if="fast" class="finance-quick-more">
          <summary>{{ $t('notes') }}</summary>
          <div class="finance-quick-field">
            <label>{{ $t('notes') }}</label>
            <textarea
              v-model="form.description"
              rows="2"
              class="finance-quick-input finance-quick-textarea"
              :placeholder="$t('optional')"
            />
          </div>
        </details>
        <div v-else class="finance-quick-field">
          <label>{{ $t('financeQuickStepNotes') }}</label>
          <textarea
            v-model="form.description"
            rows="2"
            class="finance-quick-input finance-quick-textarea"
            :placeholder="$t('optional')"
          />
        </div>

        <p v-if="error" class="error finance-quick-error">{{ error }}</p>

        <div class="finance-quick-actions">
          <button class="btn btn-primary finance-quick-save" type="submit" :disabled="saving">
            {{ saving ? $t('saving') : $t('save') }}
          </button>
          <button
            v-if="editingId"
            class="btn btn-outline finance-quick-cancel"
            type="button"
            @click="resetForm"
          >
            {{ $t('cancel') }}
          </button>
        </div>
      </form>
    </template>
  </div>
</template>

<script>
import { computed, reactive, ref, watch } from 'vue';
import { showSaved } from '../flash';
import {
  financeCategoryLabel as labelCategory,
  financeCategoryPlaceholder,
  isWarehouseFinanceCategory,
  toApiCategory,
} from '../utils/finance';
import { t } from '../i18n';

export default {
  props: {
    farmId: { type: [String, Number], required: true },
    categoryTabs: { type: Array, required: true },
    selectedCategory: { type: String, default: null },
    customSections: { type: Object, default: () => ({}) },
    ponds: { type: Array, default: () => [] },
    editId: { type: [String, Number], default: null },
    compact: { type: Boolean, default: false },
    fast: { type: Boolean, default: false },
    /** Parent already chose farm/pond — hide second picker */
    lockedScopeMode: { type: String, default: null }, // 'farm' | 'pond' | null
    lockedPondId: { type: [String, Number], default: null },
  },
  emits: ['select-category', 'saved', 'cancel-edit', 'scope-change'],
  setup(props, { emit }) {
    const saving = ref(false);
    const error = ref('');
    const editingId = ref(null);
    const scopeMode = ref(null); // 'farm' | 'pond'
    const selectedPondId = ref(null);

    const isScopeLocked = computed(() => (
      props.lockedScopeMode === 'farm' || props.lockedScopeMode === 'pond'
    ));
    const scopeReady = computed(() => !!scopeMode.value || isScopeLocked.value);
    const showQuiz = computed(() => !isScopeLocked.value && !scopeMode.value);

    const today = new Date().toISOString().slice(0, 10);
    const form = reactive({
      expense_date: today,
      title: '',
      quantity: '',
      unit_price: '',
      amount: '',
      description: '',
    });

    const openPonds = computed(() => (props.ponds || []).filter((p) => !p.is_closed && !p.closed_at));

    const entryTabs = computed(() => (
      (props.categoryTabs || []).filter((tab) => !tab.warehouse)
    ));

    const isWarehouseCategory = computed(() => (
      props.selectedCategory ? isWarehouseFinanceCategory(props.selectedCategory) : false
    ));

    const requiresWarehouseQuantity = computed(() => isWarehouseCategory.value);

    const categoryTitle = computed(() => (
      props.selectedCategory
        ? labelCategory(props.selectedCategory, t, props.customSections)
        : ''
    ));

    const expenseNamePlaceholder = computed(() => (
      props.selectedCategory ? financeCategoryPlaceholder(props.selectedCategory, t) : ''
    ));

    const scopeLabel = computed(() => {
      if (scopeMode.value === 'farm') return t('financeExpenseAllPonds');
      const pond = (props.ponds || []).find((p) => Number(p.id) === Number(selectedPondId.value));
      return pond?.name || t('financeScopePondSelected', { name: '—' });
    });

    function setScope(mode, pondId = null) {
      if (isScopeLocked.value && !props.editId) return;
      if (mode === 'pond') {
        const open = openPonds.value.some((p) => Number(p.id) === Number(pondId));
        if (!open) return;
      }
      scopeMode.value = mode;
      selectedPondId.value = mode === 'pond' ? pondId : null;
      emit('scope-change', mode);
    }

    function chooseFarm() {
      setScope('farm');
    }

    function choosePond(pondId) {
      setScope('pond', pondId);
    }

    function restartQuiz() {
      scopeMode.value = null;
      selectedPondId.value = null;
      error.value = '';
      emit('scope-change', null);
    }

    function onDateFocus(event) {
      const el = event.target;
      if (el && typeof el.showPicker === 'function') {
        try {
          el.showPicker();
        } catch (e) {
          // iOS klaviatura ochishi mumkin
        }
      }
    }

    function applyLockedScope() {
      if (!isScopeLocked.value) return;
      if (props.lockedScopeMode === 'pond' && props.lockedPondId != null && props.lockedPondId !== '') {
        scopeMode.value = 'pond';
        selectedPondId.value = props.lockedPondId;
      } else {
        scopeMode.value = 'farm';
        selectedPondId.value = null;
      }
      emit('scope-change', scopeMode.value);
    }

    function dateOnly(value) {
      return typeof value === 'string' ? value.slice(0, 10) : value;
    }

    function parsePositive(value) {
      const n = Number(value);
      return Number.isFinite(n) && n > 0 ? n : null;
    }

    function roundMoney(n) {
      return String(Math.round(n * 100) / 100);
    }

    function syncMoneyFields(changed) {
      const qty = parsePositive(form.quantity);
      const price = parsePositive(form.unit_price);
      const amount = parsePositive(form.amount);

      if (changed === 'quantity' || changed === 'unit_price') {
        if (qty != null && price != null) {
          form.amount = roundMoney(qty * price);
          return;
        }
        if (changed === 'quantity' && qty != null && amount != null) {
          form.unit_price = roundMoney(amount / qty);
          return;
        }
        if (changed === 'unit_price' && price != null && amount != null) {
          form.quantity = roundMoney(amount / price);
          return;
        }
      }

      if (changed === 'amount') {
        if (qty != null && amount != null) {
          form.unit_price = roundMoney(amount / qty);
          return;
        }
        if (price != null && amount != null) {
          form.quantity = roundMoney(amount / price);
        }
      }
    }

    function syncAmount() {
      syncMoneyFields('unit_price');
    }

    function resetForm() {
      editingId.value = null;
      Object.assign(form, {
        expense_date: today,
        title: '',
        quantity: '',
        unit_price: '',
        amount: '',
        description: '',
      });
      error.value = '';
      emit('cancel-edit');
    }

    async function loadEditRow(editId) {
      if (!editId || !props.selectedCategory) return;
      const { data } = await window.axios.get(`/director/farms/${props.farmId}/finance/expenses`, {
        params: { category: toApiCategory(props.selectedCategory) },
      });
      const row = (data.data || []).find((item) => String(item.id) === String(editId));
      if (!row) return;
      editingId.value = row.id;
      if (row.pond_id) {
        setScope('pond', row.pond_id);
      } else {
        setScope('farm');
      }
      Object.assign(form, {
        expense_date: dateOnly(row.expense_date),
        title: row.title,
        quantity: row.quantity ?? '',
        unit_price: row.unit_price ?? '',
        amount: row.amount ?? '',
        description: row.description ?? '',
      });
    }

    async function save() {
      error.value = '';
      if (!form.expense_date) {
        error.value = t('date');
        return;
      }
      if (!scopeMode.value) {
        error.value = t('financeExpenseQuizQuestion');
        return;
      }
      if (!props.selectedCategory) {
        error.value = t('financeExpenseSectionQuestion');
        return;
      }
      syncAmount();

      const qty = parsePositive(form.quantity);
      const price = parsePositive(form.unit_price);
      const amount = parsePositive(form.amount);
      // Ombordan tashqari: faqat summa yoki miqdor×narx yetadi
      if (amount == null && !(qty != null && price != null)) {
        error.value = t('financeAmountOrQtyPrice');
        return;
      }

      saving.value = true;
      try {
        const payload = {
          category: toApiCategory(props.selectedCategory),
          expense_date: form.expense_date,
          title: form.title,
          description: form.description || null,
          scope: scopeMode.value === 'farm' ? 'farm' : 'pond',
          pond_id: scopeMode.value === 'pond' ? selectedPondId.value : null,
        };
        if (qty != null) payload.quantity = qty;
        if (price != null) payload.unit_price = price;
        if (amount != null) payload.amount = amount;
        if (editingId.value) {
          await window.axios.put(`/director/farms/${props.farmId}/finance/expenses/${editingId.value}`, payload);
        } else {
          await window.axios.post(`/director/farms/${props.farmId}/finance/expenses`, payload);
        }
        showSaved(t('saved'));
        resetForm();
        emit('saved');
      } catch (e) {
        const errors = e.response?.data?.errors;
        error.value = errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message || t('noData'));
      } finally {
        saving.value = false;
      }
    }

    watch(
      () => [props.selectedCategory, props.editId],
      async ([category, editId]) => {
        if (editId) {
          await loadEditRow(editId);
          return;
        }
        if (!category) {
          applyLockedScope();
          return;
        }
        if (!editingId.value) {
          error.value = '';
          applyLockedScope();
        }
      },
      { immediate: true },
    );

    watch(
      () => [props.lockedScopeMode, props.lockedPondId],
      () => {
        if (!editingId.value) applyLockedScope();
      },
      { immediate: true },
    );

    return {
      form,
      error,
      saving,
      editingId,
      scopeMode,
      selectedPondId,
      openPonds,
      isScopeLocked,
      scopeReady,
      showQuiz,
      scopeLabel,
      entryTabs,
      isWarehouseCategory,
      requiresWarehouseQuantity,
      categoryTitle,
      expenseNamePlaceholder,
      setScope,
      chooseFarm,
      choosePond,
      restartQuiz, // parent Orqaga shu orqali tanlovga qaytadi
      onDateFocus,
      syncAmount,
      syncMoneyFields,
      resetForm,
      save,
    };
  },
};
</script>
