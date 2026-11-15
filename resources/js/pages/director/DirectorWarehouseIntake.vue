<template>
  <DirectorShell>
    <div class="page-head page-head--toolbar">
      <button
        v-if="step !== 'category'"
        class="btn btn-outline btn-sm"
        type="button"
        @click="goBackStep"
      >
        {{ $t('back') }}
      </button>
      <router-link
        v-else
        class="btn btn-outline btn-sm"
        :to="{ name: 'director-warehouse' }"
      >
        {{ $t('back') }}
      </router-link>
    </div>

    <div class="panel warehouse-intake-panel">
      <!-- 1: nima kirim qilamiz? -->
      <div
        v-if="step === 'category'"
        class="warehouse-pond-pick finance-report-quiz"
        role="radiogroup"
        :aria-label="$t('warehouseIntakeCategoryQuestion')"
      >
        <p class="finance-report-quiz__q">{{ $t('warehouseIntakeCategoryQuestion') }}</p>
        <p class="finance-report-quiz__hint">{{ $t('warehouseIntakeCategoryHint') }}</p>

        <div class="warehouse-pond-pick__list finance-section-stack">
          <button
            v-for="cat in categories"
            :key="cat.id"
            type="button"
            role="radio"
            class="warehouse-pond-chip warehouse-pond-chip--all finance-expense-quiz__answer"
            :aria-checked="false"
            @click="chooseCategory(cat.id)"
          >
            <strong>{{ cat.label }}</strong>
            <small>{{ cat.hint }}</small>
          </button>
        </div>
      </div>

      <!-- 2: uskuna qayerga? -->
      <div
        v-else-if="step === 'equipment-scope'"
        class="warehouse-pond-pick finance-report-quiz"
        role="radiogroup"
        :aria-label="$t('warehouseEquipmentQuestion')"
      >
        <div class="finance-expense-quiz-picked" style="margin-bottom: 1rem">
          <div class="finance-expense-quiz-picked__text">
            <span class="finance-expense-quiz-picked__label">{{ $t('financeExpenseQuizPickedLabel') }}</span>
            <strong>{{ $t('warehouseEquipment') }}</strong>
            <p class="finance-expense-quiz-picked__info">{{ $t('warehouseIntakeWritingEquipment') }}</p>
          </div>
        </div>

        <p class="finance-report-quiz__q">{{ $t('warehouseEquipmentQuestion') }}</p>
        <p class="finance-report-quiz__hint">{{ $t('warehouseEquipmentHint') }}</p>

        <button
          type="button"
          role="radio"
          class="warehouse-pond-chip warehouse-pond-chip--all finance-expense-quiz__answer"
          :aria-checked="false"
          @click="chooseEquipmentFarm"
        >
          <strong>{{ $t('warehouseEquipmentSplitAll') }}</strong>
          <small>{{ $t('warehouseEquipmentSplitAllHint') }}</small>
        </button>

        <template v-if="openPonds.length">
          <p class="warehouse-pond-pick__or">{{ $t('warehouseEquipmentPickOne') }}</p>
          <div class="warehouse-pond-pick__list finance-section-stack">
            <button
              v-for="pond in openPonds"
              :key="pond.id"
              type="button"
              role="radio"
              class="warehouse-pond-chip warehouse-pond-chip--all"
              :aria-checked="false"
              @click="chooseEquipmentPond(pond.id)"
            >
              <strong>{{ pond.name }}</strong>
              <small>{{ $t('warehouseEquipmentOnePondHint') }}</small>
            </button>
          </div>
        </template>
        <p v-else class="finance-expense-quiz__empty">{{ $t('noPondYet') }}</p>
      </div>

      <!-- 3: ma’lumot + saqlash -->
      <div v-else class="warehouse-intake-block">
        <div class="finance-expense-quiz-picked">
          <div class="finance-expense-quiz-picked__text">
            <span class="finance-expense-quiz-picked__label">{{ categoryLabel }}</span>
            <strong>{{ scopeBannerTitle }}</strong>
            <p class="finance-expense-quiz-picked__info">{{ scopeBannerInfo }}</p>
          </div>
        </div>

        <form class="warehouse-intake-form-wrap" @submit.prevent="save">
          <div class="field warehouse-intake-date-first">
            <label>{{ $t('date') }}</label>
            <input
              v-model="form.expense_date"
              type="date"
              required
              class="warehouse-date-input"
              enterkeyhint="next"
              @focus="onDateFocus"
            />
          </div>

          <div class="form-grid warehouse-intake-form">
            <div class="field warehouse-intake-name">
              <label>{{ $t('name') }}</label>
              <input
                v-model="form.name"
                type="text"
                maxlength="120"
                required
                autocomplete="off"
                enterkeyhint="next"
                :placeholder="namePlaceholder"
              />
            </div>
            <div class="field warehouse-intake-qty">
              <label>{{ quantityLabel }}</label>
              <FormattedNumberInput
                v-model="form.quantity"
                :max-decimals="2"
                :placeholder="quantityPlaceholder"
                @input="syncMoneyFields('quantity')"
              />
            </div>
            <div v-if="form.category !== 'equipment'" class="field">
              <label>{{ $t('unit') }}</label>
              <select v-model="form.unit" required :disabled="unitLocked">
                <option value="kg">kg</option>
                <option value="piece">{{ $t('pieces') }}</option>
              </select>
            </div>
            <div class="field">
              <label>{{ $t('price') }}</label>
              <FormattedNumberInput
                v-model="form.unit_price"
                :max-decimals="2"
                @input="syncMoneyFields('unit_price')"
              />
            </div>
            <div class="field">
              <label>{{ $t('amount') }}</label>
              <FormattedNumberInput
                v-model="form.amount"
                :max-decimals="2"
                @input="syncMoneyFields('amount')"
              />
            </div>
            <p v-if="form.category !== 'equipment'" class="field-hint warehouse-money-hint" style="grid-column: 1 / -1">
              {{ $t('warehouseQtyPriceSumHint') }}
            </p>
            <div class="field warehouse-photo-field">
              <label>{{ $t('warehousePhoto') }}</label>
              <input
                ref="photoInput"
                type="file"
                accept="image/*"
                capture="environment"
                class="warehouse-photo-input"
                @change="onPhotoSelected"
              />
              <p class="muted">{{ $t('warehousePhotoHint') }}</p>
              <div v-if="photoPreview" class="warehouse-photo-preview">
                <img :src="photoPreview" :alt="form.name || $t('warehousePhoto')" />
                <button class="btn btn-sm btn-outline" type="button" @click="clearPhoto">{{ $t('removePhoto') }}</button>
              </div>
            </div>
            <div class="actions" style="grid-column: 1 / -1">
              <button class="btn btn-primary" type="submit" :disabled="saving">
                {{ saving ? $t('loading') : $t('addToWarehouse') }}
              </button>
            </div>
          </div>
        </form>
        <p v-if="error" class="error">{{ error }}</p>
      </div>
    </div>
  </DirectorShell>
</template>

<script>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { ensureDirectorFarmId, useDirectorFarmId } from '../../utils/directorFarm';
import DirectorShell from '../../components/DirectorShell.vue';
import { showSaved } from '../../flash';
import { t } from '../../i18n';

function parsePositive(value) {
  const n = Number(value);
  return Number.isFinite(n) && n > 0 ? n : null;
}

function roundMoney(n) {
  return String(Math.round(n * 100) / 100);
}

function todayIso() {
  const d = new Date();
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function emptyForm(category = 'feed') {
  return {
    category,
    name: '',
    unit: category === 'equipment' ? 'piece' : 'kg',
    quantity: category === 'equipment' ? '1' : '',
    unit_price: '',
    amount: '',
    expense_date: todayIso(),
    pond_id: '',
    photoFile: null,
  };
}

export default {
  components: { DirectorShell },
  setup() {
    const router = useRouter();
    const farmId = useDirectorFarmId();
    const farm = ref(null);
    const ponds = ref([]);
    const saving = ref(false);
    const error = ref('');
    const photoInput = ref(null);
    const photoPreview = ref('');
    const form = reactive(emptyForm());
    /** category | equipment-scope | details */
    const step = ref('category');
    const equipmentScopePicked = ref(false);

    const categories = computed(() => ([
      { id: 'feed', label: t('warehouseFeed'), hint: t('warehouseIntakeHintFeed') },
      { id: 'medicine', label: t('warehouseMedicine'), hint: t('warehouseIntakeHintMedicine') },
      { id: 'equipment', label: t('warehouseEquipment'), hint: t('warehouseIntakeHintEquipment') },
    ]));

    const unitLocked = computed(() => form.category === 'feed');
    const openPonds = computed(() => (ponds.value || []).filter((p) => !p.is_closed && !p.closed_at));

    const categoryLabel = computed(() => {
      if (form.category === 'medicine') return t('warehouseMedicine');
      if (form.category === 'equipment') return t('warehouseEquipment');
      return t('warehouseFeed');
    });

    const scopeBannerTitle = computed(() => {
      if (form.category !== 'equipment') return t('warehouseIntakeFarmWide');
      if (!form.pond_id) return t('warehouseEquipmentSplitAll');
      const pond = openPonds.value.find((p) => String(p.id) === String(form.pond_id));
      return pond?.name || t('pond');
    });

    const scopeBannerInfo = computed(() => {
      if (form.category === 'feed') return t('warehouseIntakeWritingFeed');
      if (form.category === 'medicine') return t('warehouseIntakeWritingMedicine');
      if (form.pond_id) {
        return t('warehouseIntakeWritingEquipmentPond', { name: scopeBannerTitle.value });
      }
      return t('warehouseIntakeWritingEquipmentFarm');
    });

    const namePlaceholder = computed(() => {
      if (form.category === 'feed') return t('warehouseFeed');
      if (form.category === 'medicine') return t('warehouseMedicineName');
      return t('warehouseEquipmentExample');
    });

    const quantityLabel = computed(() => (
      form.unit === 'piece' ? t('warehouseQuantityPieces') : t('warehouseQuantityKg')
    ));

    const quantityPlaceholder = computed(() => (
      form.unit === 'piece' ? t('warehouseQtyPlaceholderPieces') : t('warehouseQtyPlaceholderKg')
    ));

    function onDateFocus(event) {
      const el = event.target;
      if (el && typeof el.showPicker === 'function') {
        try {
          el.showPicker();
        } catch (e) {
          // Brauzer klaviatura ochishi mumkin
        }
      }
    }

    function chooseCategory(id) {
      clearPhoto();
      error.value = '';
      Object.assign(form, emptyForm(id));
      equipmentScopePicked.value = false;
      if (id === 'equipment') {
        step.value = 'equipment-scope';
        return;
      }
      step.value = 'details';
    }

    function chooseEquipmentFarm() {
      form.pond_id = '';
      equipmentScopePicked.value = true;
      step.value = 'details';
    }

    function chooseEquipmentPond(pondId) {
      form.pond_id = String(pondId);
      equipmentScopePicked.value = true;
      step.value = 'details';
    }

    function goBackStep() {
      error.value = '';
      if (step.value === 'details') {
        if (form.category === 'equipment') {
          form.pond_id = '';
          equipmentScopePicked.value = false;
          step.value = 'equipment-scope';
          return;
        }
        step.value = 'category';
        return;
      }
      if (step.value === 'equipment-scope') {
        step.value = 'category';
      }
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

    async function loadFarm() {
      const id = await ensureDirectorFarmId();
      if (!id) return;
      const [farmRes, pondRes] = await Promise.all([
        window.axios.get(`/director/farms/${id}`),
        window.axios.get(`/director/farms/${id}/ponds`),
      ]);
      farm.value = farmRes.data;
      ponds.value = pondRes.data || [];
    }

    function clearPhoto() {
      if (photoPreview.value) {
        URL.revokeObjectURL(photoPreview.value);
      }
      photoPreview.value = '';
      form.photoFile = null;
      if (photoInput.value) {
        photoInput.value.value = '';
      }
    }

    function onPhotoSelected(event) {
      const file = event.target.files?.[0];
      if (photoPreview.value) {
        URL.revokeObjectURL(photoPreview.value);
      }
      photoPreview.value = '';
      form.photoFile = null;
      if (!file) {
        return;
      }
      form.photoFile = file;
      photoPreview.value = URL.createObjectURL(file);
    }

    async function save() {
      error.value = '';
      const qty = parsePositive(form.quantity);
      const price = parsePositive(form.unit_price);
      const amount = parsePositive(form.amount);
      const filled = [qty, price, amount].filter((v) => v != null).length;

      if (!form.name.trim()) {
        error.value = t('noData');
        return;
      }
      if (filled < 2) {
        error.value = t('warehouseQtyPriceSumHint');
        return;
      }
      if (form.category === 'equipment' && !equipmentScopePicked.value) {
        step.value = 'equipment-scope';
        return;
      }

      saving.value = true;
      try {
        const payload = new FormData();
        payload.append('category', form.category);
        payload.append('name', form.name.trim());
        payload.append('unit', form.unit);
        if (form.expense_date) payload.append('expense_date', form.expense_date);
        if (form.category === 'equipment' && form.pond_id) {
          payload.append('pond_id', form.pond_id);
        }
        if (qty != null) payload.append('quantity', String(qty));
        if (price != null) payload.append('unit_price', String(price));
        if (amount != null) payload.append('amount', String(amount));
        if (form.photoFile) {
          payload.append('photo', form.photoFile);
        }

        await window.axios.post(`/director/farms/${farmId.value}/warehouse`, payload, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });
        const unitLabel = form.unit === 'piece' ? t('pieces') : 'kg';
        const qtyLabel = qty != null ? `${qty} ${unitLabel}` : '';
        showSaved(t('warehouseIntakeSaved', {
          name: form.name.trim(),
          qty: qtyLabel,
        }));
        await router.replace({ name: 'director-warehouse' });
      } catch (e) {
        const errors = e.response?.data?.errors;
        error.value = errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message || t('noData'));
      } finally {
        saving.value = false;
      }
    }

    onMounted(loadFarm);

    onBeforeUnmount(() => {
      if (photoPreview.value) {
        URL.revokeObjectURL(photoPreview.value);
      }
    });

    return {
      farmId,
      farm,
      form,
      step,
      categories,
      unitLocked,
      openPonds,
      categoryLabel,
      scopeBannerTitle,
      scopeBannerInfo,
      namePlaceholder,
      quantityLabel,
      quantityPlaceholder,
      onDateFocus,
      saving,
      error,
      photoInput,
      photoPreview,
      chooseCategory,
      chooseEquipmentFarm,
      chooseEquipmentPond,
      goBackStep,
      save,
      onPhotoSelected,
      clearPhoto,
      syncMoneyFields,
    };
  },
};
</script>
