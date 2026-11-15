<template>
  <div class="page owner-page owner-companies">
    <header class="page-head">
      <div class="page-head__text">
        <h1>{{ $t('companies') }}</h1>
        <p class="oc-season-hint">{{ $t('companyQuietHint') }}</p>
      </div>
      <button class="btn btn-primary" type="button" @click="openCreate">
        {{ $t('addCompany') }}
      </button>
    </header>

    <div v-if="!loading && companies.length" class="oc-toolbar">
      <label class="oc-search">
        <span class="sr-only">{{ $t('companySearchLabel') }}</span>
        <input
          v-model.trim="searchQuery"
          type="search"
          class="oc-search__input"
          :placeholder="$t('companySearchPlaceholder')"
          autocomplete="off"
          enterkeyhint="search"
        >
        <button
          v-if="searchQuery"
          class="oc-search__clear"
          type="button"
          :title="$t('clear')"
          @click="searchQuery = ''"
        >
          ×
        </button>
      </label>
      <p class="oc-toolbar__meta">
        {{ $t('companySearchCount', { shown: filteredCompanies.length, total: companies.length }) }}
      </p>
    </div>

    <div v-if="loading" class="muted">{{ $t('loading') }}</div>
    <div v-else-if="!companies.length" class="oc-empty">{{ $t('noData') }}</div>
    <div v-else-if="!filteredCompanies.length" class="oc-empty">{{ $t('companySearchEmpty') }}</div>
    <div v-else class="oc-grid">
      <article
        v-for="c in filteredCompanies"
        :key="c.id"
        class="oc-company-card"
        :class="{
          'is-off': !c.is_active,
          'is-quiet': c.is_active && c.quiet_mode,
          'is-due': c.is_active && !c.quiet_mode && c.needs_check,
          'is-check-soon': c.is_active && !c.quiet_mode && c.check_approaching && !c.needs_check,
        }"
      >
        <div class="oc-company-card__top">
          <span class="oc-company-card__id">#{{ c.id }}</span>
          <span
            class="oc-badge"
            :class="!c.is_active ? 'is-closed' : (c.quiet_mode ? 'is-warn' : 'is-open')"
          >
            {{ companyStatusLabel(c) }}
          </span>
        </div>

        <strong class="oc-company-card__name">{{ c.name }}</strong>
        <p class="oc-company-card__sub">{{ c.admin?.name || '—' }} · {{ c.admin?.phone || '—' }}</p>

        <dl class="oc-company-card__facts">
          <div>
            <dt>{{ $t('companyInspectionPlan') }}</dt>
            <dd :class="{ 'is-warn': c.expiring_soon }">
              {{ planLabel(c.plan) }}
              <template v-if="c.access_expires_at"> · {{ formatDate(c.access_expires_at) }}</template>
            </dd>
          </div>
          <div v-if="c.requires_checks && c.is_active && !c.quiet_mode">
            <dt>{{ $t('companyInspectionNextCheck') }}</dt>
            <dd :class="{ 'is-warn': c.needs_check || c.check_approaching }">
              {{ c.next_check_at || $t('ownerClientsCheckMissing') }}
              <span v-if="c.check_interval_days" class="oc-company-card__interval">
                · {{ $t('companyCheckEveryDays', { n: c.check_interval_days }) }}
              </span>
            </dd>
          </div>
          <div v-if="c.is_active && c.quiet_mode">
            <dt>{{ $t('companyQuietBadge') }}</dt>
            <dd>{{ $t('companyQuietCardHint') }}</dd>
          </div>
        </dl>

        <div
          class="oc-company-card__actions"
          :class="c.requires_checks && c.is_active && !c.quiet_mode ? 'oc-company-card__actions--4' : 'oc-company-card__actions--3'"
        >
          <button class="oc-btn oc-btn--primary" type="button" @click="enterCompany(c)">
            {{ $t('manageDirector') }}
          </button>
          <button
            v-if="c.requires_checks && c.is_active && !c.quiet_mode"
            class="oc-btn"
            :class="{ 'oc-btn--due': c.needs_check }"
            type="button"
            @click="openInspection(c)"
          >
            {{ $t('ownerClientsStartCheck') }}
          </button>
          <button class="oc-btn oc-btn--accent" type="button" @click="openEdit(c)">
            {{ $t('edit') }}
          </button>
          <button class="oc-btn oc-btn--message" type="button" @click="openMessage(c)">
            {{ $t('companySendMessage') }}
            <span v-if="c.chat_unread" class="oc-chat-badge">{{ c.chat_unread > 9 ? '9+' : c.chat_unread }}</span>
          </button>
        </div>
      </article>
    </div>

    <!-- Qo‘shish / tahrirlash -->
    <AppModal
      v-model="showModal"
      :title="editingId ? $t('editCompany') : $t('addCompany')"
      :close-on-backdrop="!saving"
    >
      <form class="oc-form" @submit.prevent="saveCompany">
        <div class="oc-form__grid">
          <div class="field">
            <label for="oc-name">{{ $t('companyName') }}</label>
            <input
              id="oc-name"
              ref="firstInput"
              v-model="form.name"
              type="text"
              required
              autocomplete="organization"
            />
          </div>
          <div class="field">
            <label for="oc-owner">{{ $t('companyOwnerName') }}</label>
            <input id="oc-owner" v-model="form.admin_name" type="text" required autocomplete="name" />
          </div>
          <div class="field">
            <label for="oc-phone">{{ $t('phone') }}</label>
            <input id="oc-phone" v-model="form.admin_phone" type="tel" required autocomplete="tel" />
          </div>
          <div class="field">
            <label for="oc-login">{{ $t('login') }}</label>
            <input
              id="oc-login"
              v-model="form.admin_login"
              type="text"
              required
              autocomplete="username"
              inputmode="text"
            />
          </div>
          <div class="field">
            <label for="oc-password">{{ editingId ? $t('newPassword') : $t('password') }}</label>
            <input
              id="oc-password"
              v-model="form.admin_password"
              type="password"
              :required="!editingId"
              minlength="4"
              autocomplete="new-password"
              :placeholder="editingId ? $t('leaveBlankUnchanged') : ''"
            />
          </div>
          <div class="field">
            <label for="oc-plan">{{ $t('companyPlan') }}</label>
            <select id="oc-plan" v-model="form.plan" required>
              <option v-for="p in pricingPlans" :key="p.id" :value="p.id">
                {{ planOptionLabel(p) }}
              </option>
            </select>
          </div>
          <div class="field">
            <label for="oc-period">{{ $t('companyPlanPeriod') }}</label>
            <select id="oc-period" v-model="form.billing_period" required>
              <option value="month">{{ $t('companyPeriodMonth') }}</option>
              <option value="year">{{ $t('companyPeriodYear') }}</option>
            </select>
          </div>
          <div v-if="!editingId" class="field oc-form__until">
            <label>{{ $t('companyPlanUntil') }}</label>
            <input :value="previewUntil" type="text" disabled />
          </div>
          <div class="field">
            <label for="oc-interval">{{ $t('ownerClientsInterval') }}</label>
            <div class="oc-interval">
              <input
                id="oc-interval"
                v-model.number="form.check_interval_days"
                type="number"
                min="1"
                max="90"
                step="1"
                required
              />
              <span>{{ $t('companyCheckIntervalUnit') }}</span>
            </div>
            <p class="field-hint">{{ $t('companyCheckIntervalHint') }}</p>
          </div>
          <div v-if="editingId" class="field">
            <label>{{ $t('companyQuietBadge') }}</label>
            <label
              class="status-switch"
              :class="{ 'is-on': form.quiet_mode }"
            >
              <span class="status-switch__text">
                {{ form.quiet_mode ? $t('companyQuietOn') : $t('companyQuietOff') }}
              </span>
              <input
                type="checkbox"
                role="switch"
                :checked="form.quiet_mode"
                @change="form.quiet_mode = !form.quiet_mode"
              />
              <span class="status-switch__track" aria-hidden="true"><span class="status-switch__thumb" /></span>
            </label>
            <p class="field-hint">
              {{ form.quiet_mode ? $t('companyQuietOnHint') : $t('companyQuietOffHint') }}
            </p>
          </div>
          <div v-if="editingCompany" class="field">
            <label>{{ $t('status') }}</label>
            <label
              class="status-switch"
              :class="{ 'is-on': editingCompany.is_active, 'is-busy': togglingId === editingCompany.id }"
            >
              <span class="status-switch__text">
                {{ editingCompany.is_active ? $t('active') : $t('inactive') }}
              </span>
              <input
                type="checkbox"
                role="switch"
                :checked="editingCompany.is_active"
                :disabled="togglingId === editingCompany.id"
                @change="toggleActive(editingCompany, $event)"
              />
              <span class="status-switch__track" aria-hidden="true"><span class="status-switch__thumb" /></span>
            </label>
          </div>
          <div v-if="editingCompany" class="field">
            <label>{{ $t('currentPassword') }}</label>
            <div class="oc-pass">
              <span class="oc-mono">{{ showEditPass ? (editingCompany.admin?.password_plain || '—') : '••••••••' }}</span>
              <button class="btn btn-outline btn-sm" type="button" @click="showEditPass = !showEditPass">
                {{ showEditPass ? $t('hide') : $t('show') }}
              </button>
            </div>
          </div>
          <div v-if="editingCompany" class="field oc-form__until-block">
            <label>{{ $t('companyPlanUntil') }}</label>
            <p class="oc-edit-meta" :class="{ 'is-warn': editingCompany.expiring_soon }">
              <template v-if="editingCompany.access_expires_at">
                {{ formatDate(editingCompany.access_expires_at) }}
                <small v-if="editingCompany.access_days_left != null && editingCompany.is_active">
                  ({{ $t('companyDaysLeft', { n: editingCompany.access_days_left }) }})
                </small>
              </template>
              <template v-else>—</template>
            </p>
            <button class="btn btn-outline btn-sm" type="button" @click="openExtendTime">
              {{ $t('companyAddPlanTime') }}
            </button>
          </div>
          <div
            v-if="editingCompany?.requires_checks && editingCompany.is_active && !editingCompany.quiet_mode"
            class="field"
          >
            <label>{{ $t('ownerClientsNextCheck') }}</label>
            <p
              class="oc-edit-meta"
              :class="{ 'is-warn': editingCompany.needs_check || editingCompany.check_approaching }"
            >
              {{ editingCompany.next_check_at || $t('ownerClientsCheckMissing') }}
            </p>
          </div>
        </div>

        <p v-if="editingCompany?.expiring_soon" class="oc-warn">
          {{ $t('companyExpiringWarn', { n: editingCompany.access_days_left }) }}
        </p>
        <p v-if="editingCompany?.is_active && !editingCompany.quiet_mode && editingCompany.needs_check" class="oc-warn">
          {{ $t('ownerClientsDueOnly') }}
        </p>
        <p v-else-if="editingCompany?.is_active && !editingCompany.quiet_mode && editingCompany.check_approaching" class="oc-warn oc-warn--soft">
          {{ $t('companyCheckApproaching', { date: editingCompany.next_check_at }) }}
        </p>
        <p v-if="editingCompany?.attention_note" class="oc-warn oc-warn--soft">
          {{ editingCompany.attention_note }}
        </p>

        <p v-if="error" class="error">{{ error }}</p>
        <div class="oc-form__actions">
          <button class="btn btn-primary" type="submit" :disabled="saving">
            {{ saving ? $t('loading') : $t('save') }}
          </button>
          <button class="btn btn-outline" type="button" :disabled="saving" @click="closeModal">
            {{ $t('cancel') }}
          </button>
        </div>
      </form>
    </AppModal>

    <!-- Muddat qo‘shish -->
    <AppModal
      v-model="showExtend"
      :title="$t('companyAddPlanTime')"
      :close-on-backdrop="!extendSaving"
    >
      <div class="oc-extend">
        <p class="oc-extend__hint">{{ $t('companyAddPlanTimeHint') }}</p>
        <p v-if="extendCompany?.access_expires_at" class="oc-extend__current">
          {{ $t('companyPlanUntil') }}:
          <strong>{{ formatDate(extendCompany.access_expires_at) }}</strong>
        </p>
        <div class="oc-extend__choices" role="radiogroup" :aria-label="$t('companyPlanPeriod')">
          <label class="oc-extend__choice" :class="{ 'is-on': extendPeriod === 'month' }">
            <input v-model="extendPeriod" type="radio" value="month" />
            <span>
              <strong>{{ $t('companyPeriodMonth') }}</strong>
              <small>+{{ periodDays('month') }} {{ $t('companyCheckIntervalUnit') }}</small>
            </span>
          </label>
          <label class="oc-extend__choice" :class="{ 'is-on': extendPeriod === 'year' }">
            <input v-model="extendPeriod" type="radio" value="year" />
            <span>
              <strong>{{ $t('companyPeriodYear') }}</strong>
              <small>+{{ periodDays('year') }} {{ $t('companyCheckIntervalUnit') }}</small>
            </span>
          </label>
        </div>
        <p class="oc-extend__result">
          {{ $t('companyPlanUntilAfter') }}:
          <strong>{{ extendPreviewUntil }}</strong>
        </p>
        <p v-if="extendError" class="error">{{ extendError }}</p>
        <div class="oc-form__actions">
          <button class="btn btn-primary" type="button" :disabled="extendSaving" @click="confirmExtendTime">
            {{ extendSaving ? $t('loading') : $t('confirm') }}
          </button>
          <button class="btn btn-outline" type="button" :disabled="extendSaving" @click="closeExtendTime">
            {{ $t('cancel') }}
          </button>
        </div>
      </div>
    </AppModal>

    <!-- Mijoz bilan chat -->
    <AppModal
      v-model="showMessage"
      :title="messageCompany ? $t('companySendMessageTo', { name: messageCompany.name }) : $t('companySendMessage')"
      :close-on-backdrop="!messageSending"
      wide
      @close="closeMessage"
    >
      <BossCompanyChat
        :messages="chatMessages"
        :loading="chatLoading"
        :sending="messageSending"
        :error="messageError"
        :hint="$t('companySendMessageHint')"
        :placeholder="$t('companyMessagePlaceholder')"
        :my-label="$t('bossMessageTitle')"
        :their-label="messageCompany?.admin?.name || $t('companyAdmin')"
        @send="sendCompanyMessage"
      />
    </AppModal>

    <!-- Oxirgi 10 kunlik kunlik hisobot kartochkalari -->
    <AppModal
      v-model="showInspection"
      :title="$t('companyInspectionSheetTitle')"
      :close-on-backdrop="!completingCheck"
      xl
    >
      <div v-if="inspectionLoading && !inspection" class="muted">{{ $t('loading') }}</div>
      <div v-else-if="inspection" class="oc-sheet oc-inspect">
        <header class="oc-sheet__head">
          <div>
            <strong class="oc-sheet__company">{{ inspection.company?.name }}</strong>
            <p class="oc-sheet__meta">
              {{ inspection.company?.admin?.name || '—' }}
              · {{ inspection.company?.admin?.phone || '—' }}
            </p>
          </div>
          <button class="btn btn-primary" type="button" @click="manageFromInspection('home')">
            {{ $t('companyInspectionOpenCabinet') }}
          </button>
        </header>

        <!-- Havza tanlash -->
        <section class="oc-block oc-pond-pick">
          <header class="oc-block__head">
            <div>
              <strong>{{ $t('companyInspectPondPickTitle') }}</strong>
              <span>{{ $t('companyInspectPondPickHint') }}</span>
            </div>
          </header>
          <div v-if="!(inspection.ponds || []).length" class="oc-extra__empty">
            {{ $t('companyInspectPondEmpty') }}
          </div>
          <div v-else class="oc-pond-pick__list">
            <button
              v-for="p in inspection.ponds"
              :key="`ip-${p.id}`"
              type="button"
              class="oc-pond-pick__btn"
              :class="{ 'is-active': inspectionPondId === p.id }"
              :disabled="inspectionLoading"
              @click="selectInspectionPond(p.id)"
            >
              {{ p.name }}
            </button>
          </div>
        </section>

        <div v-if="inspection.needs_pond || !inspectionPondId" class="oc-extra__empty">
          {{ $t('companyInspectPondSelectFirst') }}
        </div>

        <template v-else>
        <!-- Muammo ogohlantirishlari (ish eslatmalari emas) -->
        <section class="oc-block oc-problem-alerts">
          <header class="oc-block__head">
            <div>
              <strong>{{ $t('companyInspectAlertsTitle') }}</strong>
              <span>{{ $t('companyInspectAlertsHint') }}</span>
            </div>
            <em v-if="(inspection.problem_alerts || []).length" class="oc-problem-alerts__count">
              {{ (inspection.problem_alerts || []).length }}
            </em>
          </header>
          <div v-if="!(inspection.problem_alerts || []).length" class="oc-extra__empty">
            {{ $t('companyInspectAlertsEmpty') }}
          </div>
          <ul v-else class="oc-problem-alerts__list">
            <li
              v-for="(alert, ai) in inspection.problem_alerts"
              :key="`pa-${alert.type}-${alert.itemId || ai}`"
              class="oc-problem-alert"
              :class="`is-${alert.tone || 'warning'}`"
            >
              <header class="oc-problem-alert__head">
                <strong>{{ problemAlertTitle(alert) }}</strong>
                <time v-if="alert.occurred_at" class="oc-problem-alert__when">
                  {{ formatAlertWhen(alert.occurred_at) }}
                </time>
              </header>
              <span v-html="problemAlertDetail(alert)" />
            </li>
          </ul>
        </section>

        <!-- 1) Kunlik hisobot -->
        <section class="oc-block">
          <header class="oc-block__head">
            <div>
              <strong>{{ $t('companyInspectDailyBlock') }}</strong>
              <span>{{ $t('companyInspectDailyBlockHint') }}</span>
            </div>
            <div class="oc-block__dots">
              <em class="is-green">{{ inspection.counts?.green || 0 }}</em>
              <em class="is-yellow">{{ inspection.counts?.yellow || 0 }}</em>
              <em class="is-red">{{ inspection.counts?.red || 0 }}</em>
            </div>
          </header>
          <div class="oc-card-rail oc-card-rail--daily">
            <article
              v-for="day in (inspection.days || [])"
              :key="day.date"
              class="oc-card"
              :class="`is-${day.color}`"
              :title="dayStatusLabel(day)"
            >
              <span class="oc-card__accent" aria-hidden="true" />
              <div class="oc-card__body">
                <header class="oc-card__head">
                  <strong>{{ shortDate(day.date) }}</strong>
                  <span>{{ weekdayShort(day.weekday) }}</span>
                </header>
                <template v-if="day.has_report">
                  <div class="oc-card__metric">
                    <div class="oc-card__metric-top">
                      <span class="oc-card__metric-label">{{ $t('companyInspectFeedShort') }}</span>
                    </div>
                    <strong class="oc-card__metric-val">
                      {{ day.has_feed ? `${fmtNum(day.feed_kg)} kg` : '—' }}
                    </strong>
                  </div>
                  <div class="oc-card__metric">
                    <div class="oc-card__metric-top">
                      <span class="oc-card__metric-label">{{ $t('companyInspectMedicineShort') }}</span>
                    </div>
                    <template v-if="day.has_medicine">
                      <div
                        v-for="(med, mi) in medicineLines(day)"
                        :key="`med-${day.date}-${mi}`"
                        class="oc-card__med"
                      >
                        <span>{{ med.name }}</span>
                        <b>{{ med.qty }}</b>
                      </div>
                    </template>
                    <strong v-else class="oc-card__metric-val">—</strong>
                  </div>
                  <footer class="oc-card__foot">{{ dayMiniLabel(day) }}</footer>
                </template>
                <p v-else class="oc-card__empty">{{ $t('companyInspectDayMissingShort') }}</p>
              </div>
            </article>
          </div>
          <p class="oc-block__legend">
            <span class="is-green">● {{ $t('companyInspectLegendGreenShort') }}</span>
            <span class="is-yellow">● {{ $t('companyInspectLegendYellowShort') }}</span>
            <span class="is-red">● {{ $t('companyInspectLegendRedShort') }}</span>
          </p>
        </section>

        <!-- Yem dinamikasi — oxirgi 10 kun (alohida) -->
        <section class="oc-block oc-feed-algo">
          <header class="oc-block__head">
            <div>
              <strong>{{ $t('companyInspectFeedAlgoTitle') }}</strong>
              <span>{{ $t('companyInspectFeedAlgoHint') }}</span>
            </div>
            <div class="oc-feed-algo__stats" v-if="feedAlgo">
              <em class="is-up">+ {{ feedAlgo.upCount }}</em>
              <em class="is-down">− {{ feedAlgo.downCount }}</em>
              <em class="is-total">{{ fmtNum(feedAlgo.totalKg) }} kg</em>
            </div>
          </header>

          <div v-if="feedAlgo" class="oc-feed-algo__chart">
            <svg
              class="oc-feed-algo__svg"
              :viewBox="`0 0 ${feedAlgo.svg.w} ${feedAlgo.svg.h}`"
              preserveAspectRatio="xMidYMid meet"
              role="img"
              :aria-label="$t('companyInspectFeedAlgoTitle')"
            >
              <defs>
                <linearGradient id="ocFeedFillSoft" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="#7aa8c9" stop-opacity="0.22" />
                  <stop offset="100%" stop-color="#7aa8c9" stop-opacity="0" />
                </linearGradient>
              </defs>
              <line
                v-for="(gy, gi) in feedAlgo.svg.gridY"
                :key="`g-${gi}`"
                :x1="feedAlgo.svg.padL"
                :x2="feedAlgo.svg.w - feedAlgo.svg.padR"
                :y1="gy"
                :y2="gy"
                class="oc-feed-algo__grid"
              />
              <path :d="feedAlgo.svg.area" fill="url(#ocFeedFillSoft)" />
              <path :d="feedAlgo.svg.line" class="oc-feed-algo__line" fill="none" />
              <g v-for="p in feedAlgo.points" :key="`pt-${p.date}`">
                <circle
                  :cx="p.x"
                  :cy="p.y"
                  r="3.4"
                  class="oc-feed-algo__dot"
                  :class="`is-${p.dir}`"
                />
                <title>{{ p.label }}</title>
              </g>
            </svg>

            <div class="oc-feed-algo__axis">
              <div
                v-for="p in feedAlgo.points"
                :key="`ax-${p.date}`"
                class="oc-feed-algo__col"
                :class="`is-${p.dir}`"
              >
                <strong>{{ fmtNum(p.kg) }}</strong>
                <span class="oc-feed-algo__delta">
                  <template v-if="p.dir === 'up'">+{{ fmtNum(Math.abs(p.delta)) }}</template>
                  <template v-else-if="p.dir === 'down'">−{{ fmtNum(Math.abs(p.delta)) }}</template>
                  <template v-else>·</template>
                </span>
                <em>{{ shortDate(p.date) }}</em>
              </div>
            </div>
          </div>
        </section>

        <!-- 2a) Suv testlari -->
        <section class="oc-block">
          <header class="oc-block__head">
            <div>
              <strong>{{ $t('companyInspectWaterTitle') }}</strong>
              <span>{{ $t('companyInspectWaterHint') }}</span>
            </div>
            <small class="oc-block__limit">{{ $t('companyInspectWaterLimit') }}</small>
          </header>
          <div v-if="!(inspection.water_tests || []).length" class="oc-extra__empty">
            {{ $t('companyInspectWaterEmpty') }}
          </div>
          <div v-else class="oc-card-rail oc-card-rail--water">
            <article
              v-for="w in inspection.water_tests"
              :key="`wt-${w.id}`"
              class="oc-card oc-card--wide is-sky"
            >
              <span class="oc-card__accent" aria-hidden="true" />
              <div class="oc-card__body">
                <header class="oc-card__head">
                  <div>
                    <strong>{{ shortDate(w.date) }}</strong>
                    <span class="oc-card__sub">{{ weekdayShort(w.weekday) }}{{ w.test_time ? ` · ${w.test_time}` : '' }}</span>
                  </div>
                </header>
                    <div class="oc-card__grid">
                  <div
                    class="oc-card__cell"
                    :class="waterCellClass(w, 'do_mg_l')"
                    :title="waterCellTitle(w, 'do_mg_l')"
                  >
                    <span>{{ $t('companyInspectDo') }}</span>
                    <b>{{ w.do_mg_l != null ? fmtNum(w.do_mg_l) : '—' }}</b>
                  </div>
                  <div
                    class="oc-card__cell"
                    :class="waterCellClass(w, 'ph_level')"
                    :title="waterCellTitle(w, 'ph_level')"
                  >
                    <span>{{ $t('companyInspectPh') }}</span>
                    <b>{{ w.ph_level != null ? fmtNum(w.ph_level) : '—' }}</b>
                  </div>
                  <div
                    class="oc-card__cell"
                    :class="waterCellClass(w, 'ammonia_mg_l')"
                    :title="waterCellTitle(w, 'ammonia_mg_l')"
                  >
                    <span>NH₃</span>
                    <b>{{ w.ammonia_mg_l != null ? fmtNum(w.ammonia_mg_l) : '—' }}</b>
                  </div>
                  <div
                    class="oc-card__cell"
                    :class="waterCellClass(w, 'nitrite_mg_l')"
                    :title="waterCellTitle(w, 'nitrite_mg_l')"
                  >
                    <span>NO₂</span>
                    <b>{{ w.nitrite_mg_l != null ? fmtNum(w.nitrite_mg_l) : '—' }}</b>
                  </div>
                  <div
                    class="oc-card__cell oc-card__cell--full"
                    :class="waterCellClass(w, 'water_transparency_cm')"
                    :title="waterCellTitle(w, 'water_transparency_cm')"
                  >
                    <span>{{ $t('companyInspectTransparency') }}</span>
                    <b>{{ w.water_transparency_cm != null ? `${fmtNum(w.water_transparency_cm)} cm` : '—' }}</b>
                  </div>
                </div>
              </div>
            </article>
          </div>
        </section>

        <!-- Yo‘qotish (suvdan keyin) -->
        <section class="oc-block">
          <header class="oc-block__head">
            <div>
              <strong>{{ $t('companyInspectLossTitle') }}</strong>
              <span>{{ $t('companyInspectLossHint') }}</span>
            </div>
            <small class="oc-block__limit">{{ $t('companyInspectLossLimit') }}</small>
          </header>
          <div v-if="!(inspection.losses || []).length" class="oc-extra__empty">
            {{ $t('companyInspectLossEmpty') }}
          </div>
          <div v-else class="oc-card-rail oc-card-rail--loss">
            <article
              v-for="loss in inspection.losses"
              :key="`ls-${loss.id}`"
              class="oc-card oc-card--wide is-red"
            >
              <span class="oc-card__accent" aria-hidden="true" />
              <div class="oc-card__body">
                <header class="oc-card__head">
                  <div>
                    <strong>{{ shortDate(loss.date) }}</strong>
                    <span class="oc-card__sub">{{ weekdayShort(loss.weekday) }}</span>
                  </div>
                </header>
                <div class="oc-card__metric">
                  <div class="oc-card__metric-top">
                    <span class="oc-card__metric-label">{{ $t('companyInspectLossFish') }}</span>
                  </div>
                  <strong class="oc-card__metric-val">{{ fishTypeLabel(loss.fish_type) }}</strong>
                </div>
                <div class="oc-card__metric">
                  <div class="oc-card__metric-top">
                    <span class="oc-card__metric-label">{{ $t('companyInspectLossQty') }}</span>
                  </div>
                  <strong class="oc-card__metric-val">
                    {{ fmtNum(loss.quantity) }}
                    <small>{{ $t('companyInspectLossPcs') }}</small>
                  </strong>
                </div>
              </div>
            </article>
          </div>
        </section>

        <!-- Kontrol -->
        <section class="oc-block">
          <header class="oc-block__head">
            <div>
              <strong>{{ $t('companyInspectControlTitle') }}</strong>
              <span>{{ $t('companyInspectControlHint') }}</span>
            </div>
            <small class="oc-block__limit">{{ $t('companyInspectControlLimit') }}</small>
          </header>
          <div v-if="!(inspection.controls || []).length" class="oc-extra__empty">
            {{ $t('companyInspectControlEmpty') }}
          </div>
          <div v-else class="oc-card-rail oc-card-rail--control">
            <article
              v-for="c in inspection.controls"
              :key="`ct-${c.id}`"
              class="oc-card oc-card--wide is-navy"
            >
              <span class="oc-card__accent" aria-hidden="true" />
              <div class="oc-card__body">
                <header class="oc-card__head">
                  <div>
                    <strong>{{ shortDate(c.date) }}</strong>
                    <span class="oc-card__sub">{{ weekdayShort(c.weekday) }}</span>
                  </div>
                </header>
                <div class="oc-card__weights">
                  <div
                    v-for="(wt, wi) in (c.weights || [])"
                    :key="`ct-${c.id}-w-${wi}`"
                    class="oc-card__weight"
                  >
                    <span>{{ fishTypeLabel(wt.fish_type) }}</span>
                    <b>{{ fmtNum(wt.average_weight_g) }} <small>g</small></b>
                  </div>
                  <p v-if="!(c.weights || []).length" class="oc-card__empty">—</p>
                </div>
              </div>
            </article>
          </div>
        </section>

        <!-- Moliya (yuzaki) — Hisobotlar kartochkalari uslubi -->
        <section v-if="inspection.finance" class="oc-block oc-finance">
          <header class="oc-block__head">
            <div>
              <strong>{{ $t('companyInspectFinanceTitle') }}</strong>
              <span>{{ $t('companyInspectFinanceHint') }}</span>
            </div>
            <em class="oc-finance__level" :class="`is-${inspection.finance.level}`">
              {{ financeLevelLabel(inspection.finance.level) }}
            </em>
          </header>

          <div class="oc-finance__kpis">
            <div class="oc-finance__kpi">
              <span>{{ $t('companyInspectFinanceCurrent') }}</span>
              <b>{{ formatMoney(inspection.finance.current_total) }}</b>
            </div>
            <div class="oc-finance__kpi">
              <span>{{ $t('companyInspectFinancePrevious') }}</span>
              <b>{{ formatMoney(inspection.finance.previous_total) }}</b>
            </div>
            <div class="oc-finance__kpi" :class="financeChangeClass(inspection.finance.change_pct)">
              <span>{{ $t('companyInspectFinanceChange') }}</span>
              <b>{{ formatChangePct(inspection.finance.change_pct) }}</b>
            </div>
          </div>

          <div
            v-if="(inspection.finance.categories || inspection.finance.top_categories || []).length"
            class="oc-fin-grid"
          >
            <article
              v-for="cat in (inspection.finance.categories || inspection.finance.top_categories)"
              :key="`fc-${cat.category}`"
              class="oc-fin-card"
              :class="[
                `oc-fin-card--${financeCardTone(cat.category)}`,
                { 'oc-fin-card--warehouse': cat.warehouse },
              ]"
            >
              <div class="oc-fin-card__top">
                <strong>{{ financeCategoryLabel(cat.category) }}</strong>
              </div>
              <span class="oc-fin-card__sum">{{ formatMoney(cat.amount) }}</span>
              <div class="oc-fin-card__bar" aria-hidden="true">
                <i :style="{ width: `${Math.min(100, Number(cat.pct) || 0)}%`, background: financeCategoryColor(cat.category) }" />
              </div>
              <small>
                <template v-if="cat.warehouse">{{ $t('financeSimpleWarehouseBadge') }}</template>
                <template v-else>{{ $t('companyInspectFinanceShare', { n: fmtNum(cat.pct) }) }}</template>
              </small>
            </article>
          </div>
          <p v-else class="oc-extra__empty">{{ $t('companyInspectFinanceNoSpend') }}</p>

          <ul v-if="(inspection.finance.flags || []).length" class="oc-finance__flags">
            <li
              v-for="(flag, fi) in inspection.finance.flags"
              :key="`ff-${flag.type}-${fi}`"
              :class="`is-${flag.level}`"
            >
              {{ financeFlagText(flag) }}
            </li>
          </ul>

          <div v-if="(inspection.finance.problem_days || []).length" class="oc-fin-grid oc-fin-grid--days">
            <article
              v-for="day in inspection.finance.problem_days"
              :key="`fd-${day.date}`"
              class="oc-fin-card oc-fin-card--spike"
            >
              <div class="oc-fin-card__top">
                <strong>{{ shortDate(day.date) }}</strong>
              </div>
              <span class="oc-fin-card__sum">{{ formatMoney(day.amount) }}</span>
              <div class="oc-fin-card__bar" aria-hidden="true">
                <i style="width: 100%; background: #c62828" />
              </div>
              <small>{{ $t('companyInspectFinanceSpikeDay') }}</small>
            </article>
          </div>

          <button class="btn btn-outline btn-sm oc-finance__open" type="button" @click="manageFromInspection('finance')">
            {{ $t('companyInspectionFixFinance') }}
          </button>
        </section>
        </template>

        <div class="field oc-sheet__note">
          <label for="oc-inspect-note">{{ $t('companyInspectionNote') }}</label>
          <textarea
            id="oc-inspect-note"
            v-model.trim="inspectionNote"
            rows="2"
            :placeholder="$t('companyInspectionNotePlaceholder')"
          />
        </div>
      </div>

      <template #footer>
        <div class="oc-sheet__footer">
          <button
            class="btn btn-outline"
            type="button"
            :disabled="inspectionLoading || !inspectionCompany"
            @click="reloadInspection"
          >
            {{ $t('ownerClientsCheckAgain') }}
          </button>
          <button class="btn btn-outline" type="button" :disabled="completingCheck" @click="closeInspection">
            {{ $t('close') }}
          </button>
          <button
            class="btn btn-primary"
            type="button"
            :disabled="completingCheck || !inspection || !canCompleteInspection"
            @click="completeInspection"
          >
            {{ completingCheck ? $t('loading') : $t('ownerClientsCompleteCheck') }}
          </button>
        </div>
      </template>
    </AppModal>
  </div>
</template>

<script>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppModal from '../../components/AppModal.vue';
import BossCompanyChat from '../../components/BossCompanyChat.vue';
import { i18nState, localeTag, t } from '../../i18n';
import { PRICING_PLANS, formatPlanPrice, periodDays, previewEndDate } from '../../config/pricingPlans';
import { setActingCompany } from '../../utils/ownerContext';
import { fishLabel } from '../../utils/fish';
import { evaluateWaterQuality } from '../../utils/waterQuality';
import { formatDisplayDate, formatDisplayDateTime } from '../../utils/date';
import { formatAlertRichText } from '../../utils/alertRichText';

function emptyForm() {
  return {
    name: '',
    admin_name: '',
    admin_login: '',
    admin_password: '',
    admin_phone: '',
    plan: 'cabinet',
    billing_period: 'year',
    check_interval_days: 7,
    quiet_mode: false,
  };
}

export default {
  components: { AppModal, BossCompanyChat },
  setup() {
    const router = useRouter();
    const route = useRoute();
    const companies = ref([]);
    const searchQuery = ref('');
    const loading = ref(true);
    const saving = ref(false);
    const togglingId = ref(null);
    const showModal = ref(false);
    const showEditPass = ref(false);
    const editingId = ref(null);
    const error = ref('');
    const firstInput = ref(null);
    const form = reactive(emptyForm());
    const pricingPlans = PRICING_PLANS;
    const showInspection = ref(false);
    const inspectionLoading = ref(false);
    const completingCheck = ref(false);
    const inspection = ref(null);
    const inspectionCompany = ref(null);
    const inspectionNote = ref('');
    const inspectionPondId = ref(null);
    const togglingQuietId = ref(null);
    const showMessage = ref(false);
    const messageCompany = ref(null);
    const messageError = ref('');
    const messageSending = ref(false);
    const chatMessages = ref([]);
    const chatLoading = ref(false);
    let chatPollTimer = null;
    const showExtend = ref(false);
    const extendCompany = ref(null);
    const extendPeriod = ref('month');
    const extendSaving = ref(false);
    const extendError = ref('');

    const editingCompany = computed(() => {
      if (!editingId.value) return null;
      return companies.value.find((c) => c.id === editingId.value) || null;
    });

    const previewUntil = computed(() => previewEndDate(form.billing_period));

    const extendPreviewUntil = computed(() => previewEndDate(
      extendPeriod.value,
      extendCompany.value?.access_expires_at || null,
    ));

    function normalizeSearch(value) {
      return String(value || '')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();
    }

    function digitsOnly(value) {
      return String(value || '').replace(/\D+/g, '');
    }

    const filteredCompanies = computed(() => {
      const q = normalizeSearch(searchQuery.value);
      const list = companies.value || [];
      if (!q) return list;

      const qDigits = digitsOnly(q);
      return list.filter((c) => {
        const name = normalizeSearch(c?.name);
        const adminName = normalizeSearch(c?.admin?.name);
        const login = normalizeSearch(c?.admin?.login);
        const phone = normalizeSearch(c?.admin?.phone);
        const phoneDigits = digitsOnly(c?.admin?.phone);
        const id = String(c?.id ?? '');

        if (name.includes(q) || adminName.includes(q) || login.includes(q) || phone.includes(q)) {
          return true;
        }
        if (id === q || id.includes(q)) return true;
        if (qDigits && phoneDigits.includes(qDigits)) return true;
        return false;
      });
    });

    function companyStatusLabel(c) {
      if (!c?.is_active) return t('inactive');
      if (c.quiet_mode) return t('companyQuietBadge');
      return t('companyChecksOnBadge');
    }

    function dayStatusLabel(day) {
      if (!day?.has_report) return t('companyInspectDayMissing');
      if (day.color === 'green') return t('companyInspectDayBoth');
      if (day.color === 'yellow') {
        return day.has_feed ? t('companyInspectDayFeedOnly') : t('companyInspectDayMedicineOnly');
      }
      return t('companyInspectDayEmpty');
    }

    function dayMiniLabel(day) {
      if (!day?.has_report) return t('companyInspectDayMissingShort');
      if (day.color === 'green') return t('companyInspectDayBothShort');
      if (day.has_feed && !day.has_medicine) return t('companyInspectDayFeedOnlyShort');
      if (day.has_medicine && !day.has_feed) return t('companyInspectDayMedicineOnlyShort');
      return t('companyInspectDayEmptyShort');
    }

    function medicineShort(day) {
      const lines = medicineLines(day);
      if (!lines.length) return '—';
      if (lines.length === 1) return `${lines[0].name} ${lines[0].qty}`;
      return `${lines[0].name} ${lines[0].qty}+${lines.length - 1}`;
    }

    function medicineLines(day) {
      const list = day?.medicines || [];
      if (!list.length) return [];
      const merged = new Map();
      list.forEach((m) => {
        const name = String(m?.name || '').trim() || '—';
        const unit = String(m?.unit || '').trim();
        const key = `${name}|${unit}`;
        const prev = merged.get(key) || { name, unit, qty: 0 };
        prev.qty += Number(m?.qty) || 0;
        merged.set(key, prev);
      });
      return [...merged.values()].map((m) => ({
        name: m.name,
        qty: `${fmtNum(m.qty)}${m.unit ? ` ${m.unit}` : ''}`,
      }));
    }

    function smoothLinePath(pts) {
      if (!pts.length) return '';
      if (pts.length === 1) return `M${pts[0].x},${pts[0].y}`;
      if (pts.length === 2) {
        return `M${pts[0].x},${pts[0].y} L${pts[1].x},${pts[1].y}`;
      }
      // Catmull-Rom → yumshoq cubic Bezier
      let d = `M${pts[0].x.toFixed(1)},${pts[0].y.toFixed(1)}`;
      for (let i = 0; i < pts.length - 1; i += 1) {
        const p0 = pts[i - 1] || pts[i];
        const p1 = pts[i];
        const p2 = pts[i + 1];
        const p3 = pts[i + 2] || p2;
        const cp1x = p1.x + (p2.x - p0.x) / 6;
        const cp1y = p1.y + (p2.y - p0.y) / 6;
        const cp2x = p2.x - (p3.x - p1.x) / 6;
        const cp2y = p2.y - (p3.y - p1.y) / 6;
        d += ` C${cp1x.toFixed(1)},${cp1y.toFixed(1)} ${cp2x.toFixed(1)},${cp2y.toFixed(1)} ${p2.x.toFixed(1)},${p2.y.toFixed(1)}`;
      }
      return d;
    }

    const feedAlgo = computed(() => {
      const days = inspection.value?.days || [];
      if (!days.length) return null;

      const rows = days.map((d, i) => {
        const kg = Number(d?.feed_kg) || 0;
        const prev = i > 0 ? Number(days[i - 1]?.feed_kg) || 0 : null;
        const delta = prev == null ? 0 : kg - prev;
        let dir = 'flat';
        if (prev != null) {
          if (delta > 0.05) dir = 'up';
          else if (delta < -0.05) dir = 'down';
        }
        return {
          date: d.date,
          kg,
          delta,
          dir,
          hasReport: !!d.has_report,
        };
      });

      const padL = 18;
      const padR = 18;
      const padT = 18;
      const padB = 16;
      const w = 640;
      const h = 152;
      const innerW = w - padL - padR;
      const innerH = h - padT - padB;
      const maxKg = Math.max(...rows.map((r) => r.kg), 0.01);
      // Pastga biroz joy — chiziq chetga yopishib qolmasin
      const yMax = maxKg * 1.08;
      const n = rows.length;

      const points = rows.map((r, i) => {
        const x = padL + (n === 1 ? innerW / 2 : (i / (n - 1)) * innerW);
        const y = padT + innerH - (r.kg / yMax) * innerH;
        const abs = Math.abs(r.delta);
        let label = `${shortDate(r.date)}: ${fmtNum(r.kg)} kg`;
        if (i > 0) {
          if (r.dir === 'up') label += ` (+${fmtNum(abs)})`;
          else if (r.dir === 'down') label += ` (−${fmtNum(abs)})`;
        }
        return { ...r, x, y, label };
      });

      const line = smoothLinePath(points);
      const first = points[0];
      const last = points[points.length - 1];
      const baseY = padT + innerH;
      const area = `${line} L${last.x.toFixed(1)},${baseY.toFixed(1)} L${first.x.toFixed(1)},${baseY.toFixed(1)} Z`;
      const gridY = [0.33, 0.66].map((f) => padT + innerH * (1 - f));

      return {
        points,
        totalKg: rows.reduce((s, r) => s + r.kg, 0),
        upCount: rows.filter((r) => r.dir === 'up').length,
        downCount: rows.filter((r) => r.dir === 'down').length,
        svg: { w, h, padL, padR, padT, padB, line, area, gridY },
      };
    });

    function shortDate(iso) {
      if (!iso) return '—';
      const s = String(iso).slice(0, 10);
      const parts = s.split('-');
      if (parts.length === 3) return `${parts[2]}.${parts[1]}`;
      return s;
    }

    function weekdayShort(name) {
      if (!name) return '';
      const s = String(name).trim();
      return s.length <= 3 ? s : `${s.slice(0, 3)}.`;
    }

    function fishTypeLabel(type) {
      return fishLabel(type, t);
    }

    function waterAssessment(w) {
      return evaluateWaterQuality({
        test_time: w?.test_time || null,
        do_mg_l: w?.do_mg_l,
        ph_level: w?.ph_level,
        ammonia_mg_l: w?.ammonia_mg_l,
        nitrite_mg_l: w?.nitrite_mg_l,
        water_transparency_cm: w?.water_transparency_cm,
      });
    }

    /** O‘lchanmagan → none; norma → ok; ehtiyot → caution; xavfli → danger */
    function waterMetricTone(w, key) {
      if (w?.[key] == null || w?.[key] === '') return 'none';
      const level = waterAssessment(w)?.metrics?.[key]?.level;
      if (level === 'danger') return 'danger';
      if (level === 'caution') return 'caution';
      if (level === 'ok') return 'ok';
      return 'none';
    }

    function waterCellClass(w, key) {
      return `is-${waterMetricTone(w, key)}`;
    }

    function waterCellTitle(w, key) {
      const tone = waterMetricTone(w, key);
      if (tone === 'none') return t('companyInspectWaterNotMeasured');
      if (tone === 'ok') return t('companyInspectWaterOk');
      if (tone === 'caution') return t('companyInspectWaterCaution');
      return t('companyInspectWaterDanger');
    }

    function fmtNum(n) {
      if (n == null || n === '') return '—';
      const num = Number(n);
      if (Number.isNaN(num)) return '—';
      return new Intl.NumberFormat(localeTag(i18nState.locale), { maximumFractionDigits: 2 }).format(num);
    }

    function formatMoney(n) {
      if (n == null) return '—';
      return new Intl.NumberFormat(localeTag(i18nState.locale)).format(n);
    }

    function formatChangePct(pct) {
      if (pct == null || Number.isNaN(Number(pct))) return '—';
      const n = Number(pct);
      const sign = n > 0 ? '+' : '';
      return `${sign}${fmtNum(n)}%`;
    }

    function financeChangeClass(pct) {
      const n = Number(pct);
      if (!Number.isFinite(n) || n === 0) return 'is-flat';
      return n > 0 ? 'is-up' : 'is-down';
    }

    function problemAlertTitle(alert) {
      const titles = {
        'fcr-high': 'fcrNormAlertTitle',
        'feed-sharp-change': 'feedSharpChangeAlertTitle',
        'survival-low': 'survivalLowAlertTitle',
        'water-quality': 'companyInspectWaterAlertTitle',
      };
      const key = titles[alert?.type];
      return key ? t(key) : (alert?.pondName || alert?.farmName || t('companyInspectAlertsTitle'));
    }

    function problemAlertDetail(alert) {
      if (!alert?.message_key) return alert?.body || '';
      return formatAlertRichText(t(alert.message_key, alert.message_params || {}));
    }

    function formatAlertWhen(value) {
      if (!value) return '';
      const formatted = formatDisplayDateTime(value);
      if (formatted) return formatted;
      try {
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return String(value);
        const dd = String(d.getDate()).padStart(2, '0');
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const yyyy = d.getFullYear();
        const hh = String(d.getHours()).padStart(2, '0');
        const mi = String(d.getMinutes()).padStart(2, '0');
        return `${dd}.${mm}.${yyyy} ${hh}:${mi}`;
      } catch (e) {
        return String(value);
      }
    }

    function financeLevelLabel(level) {
      if (level === 'danger') return t('companyInspectFinanceLevelDanger');
      if (level === 'caution') return t('companyInspectFinanceLevelCaution');
      return t('companyInspectFinanceLevelOk');
    }

    function financeCategoryLabel(cat) {
      const map = {
        feed: 'feed',
        medicine: 'companyInspectMedicineShort',
        salary: 'companyInspectFinanceCatSalary',
        equipment: 'companyInspectFinanceCatEquipment',
        transport: 'companyInspectFinanceCatTransport',
        utilities: 'companyInspectFinanceCatUtilities',
        contingent: 'companyInspectFinanceCatContingent',
        main: 'companyInspectFinanceCatMain',
        other: 'companyInspectFinanceCatOther',
      };
      return t(map[cat] || 'companyInspectFinanceCatOther');
    }

    const FINANCE_COLORS = {
      feed: '#2e7d32',
      medicine: '#c62828',
      equipment: '#455a64',
      main: '#1565c0',
      salary: '#00838f',
      utilities: '#ef6c00',
      contingent: '#6a1b9a',
      transport: '#ef6c00',
      other: '#757575',
      custom: '#6a1b9a',
    };

    function financeCategoryColor(cat) {
      return FINANCE_COLORS[cat] || FINANCE_COLORS.other;
    }

    function financeCardTone(cat) {
      if (FINANCE_COLORS[cat]) return cat;
      return 'other';
    }

    function financeFlagText(flag) {
      if (!flag) return '';
      if (flag.type === 'misplaced') {
        return t('companyInspectFinanceFlagMisplaced', {
          n: flag.count || 0,
          amount: formatMoney(flag.amount || 0),
        });
      }
      if (flag.type === 'zero_streak') {
        return t('companyInspectFinanceFlagZeroStreak', { n: flag.days || 0 });
      }
      if (flag.type === 'spike') {
        return t('companyInspectFinanceFlagSpike', { n: flag.count || 0 });
      }
      return '';
    }

    function formatDate(iso) {
      if (!iso) return '—';
      const formatted = formatDisplayDate(iso);
      return formatted || '—';
    }

    function formatDateTime(iso) {
      if (!iso) return '—';
      const formatted = formatDisplayDateTime(iso);
      return formatted || '—';
    }

    function planLabel(plan) {
      const row = PRICING_PLANS.find((p) => p.id === plan);
      return row ? t(row.nameKey) : (plan || '—');
    }

    function planOptionLabel(plan) {
      const name = t(plan.nameKey);
      if (plan.customPrice) {
        return `${name} — ${t('homePlanIndividualPrice')}`;
      }
      const amount = form.billing_period === 'month' ? plan.monthPrice : plan.yearPrice;
      const period = form.billing_period === 'month'
        ? t('companyPeriodMonthShort')
        : t('companyPeriodYearShort');
      return `${name} — ${formatMoney(amount)} / ${period}`;
    }

    function periodLabel(period) {
      if (period === 'month') return t('companyPeriodMonth');
      if (period === 'year') return t('companyPeriodYear');
      return '—';
    }

    async function load() {
      loading.value = true;
      try {
        const { data } = await window.axios.get('/superadmin/companies');
        companies.value = data;
      } finally {
        loading.value = false;
      }
    }

    async function openCreate() {
      editingId.value = null;
      showEditPass.value = false;
      error.value = '';
      Object.assign(form, emptyForm());
      showModal.value = true;
      await nextTick();
      firstInput.value?.focus?.();
    }

    async function openEdit(company) {
      editingId.value = company.id;
      showEditPass.value = false;
      error.value = '';
      Object.assign(form, {
        name: company.name || '',
        admin_name: company.admin?.name || '',
        admin_login: company.admin?.login || '',
        admin_password: '',
        admin_phone: company.admin?.phone || '',
        plan: company.plan || 'cabinet',
        billing_period: company.billing_period || 'year',
        check_interval_days: Number(company.check_interval_days) > 0
          ? Number(company.check_interval_days)
          : 7,
        quiet_mode: !!company.quiet_mode,
      });
      showModal.value = true;
      await nextTick();
      firstInput.value?.focus?.();
    }

    function stopChatPoll() {
      if (chatPollTimer) {
        clearInterval(chatPollTimer);
        chatPollTimer = null;
      }
    }

    async function loadChat(silent = false) {
      const company = messageCompany.value;
      if (!company?.id) return;
      if (!silent) chatLoading.value = true;
      try {
        const { data } = await window.axios.get(`/superadmin/companies/${company.id}/chat`);
        chatMessages.value = data.messages || [];
        const idx = companies.value.findIndex((c) => c.id === company.id);
        if (idx >= 0) {
          companies.value[idx] = {
            ...companies.value[idx],
            chat_unread: 0,
          };
        }
      } catch (e) {
        if (!silent) {
          messageError.value = e.response?.data?.message || t('errorOccurred');
        }
      } finally {
        if (!silent) chatLoading.value = false;
      }
    }

    function openMessage(company) {
      messageCompany.value = company;
      messageError.value = '';
      chatMessages.value = [];
      showMessage.value = true;
      loadChat();
      stopChatPoll();
      chatPollTimer = window.setInterval(() => loadChat(true), 12000);
    }

    function closeMessage() {
      if (messageSending.value) return;
      stopChatPoll();
      showMessage.value = false;
      messageCompany.value = null;
      chatMessages.value = [];
      messageError.value = '';
    }

    async function sendCompanyMessage(payload) {
      if (!messageCompany.value?.id) return;
      const body = typeof payload === 'string' ? payload : (payload?.body || '');
      const image = typeof payload === 'object' ? payload?.image : null;
      if (!body && !image) return;
      messageSending.value = true;
      messageError.value = '';
      try {
        const form = new FormData();
        if (body) form.append('body', body);
        if (image) form.append('image', image);
        const { data } = await window.axios.post(
          `/superadmin/companies/${messageCompany.value.id}/chat`,
          form,
          { headers: { 'Content-Type': 'multipart/form-data' } },
        );
        if (data?.item) chatMessages.value = [...chatMessages.value, data.item];
        else await loadChat(true);
      } catch (e) {
        messageError.value = e.response?.data?.message
          || Object.values(e.response?.data?.errors || {})?.[0]?.[0]
          || t('errorOccurred');
      } finally {
        messageSending.value = false;
      }
    }

    function enterCompany(company, routeName = 'director-home') {
      if (!company?.id) return;
      setActingCompany({
        id: company.id,
        name: company.name,
        phone: company.admin?.phone || '',
      });
      showInspection.value = false;
      router.push({ name: routeName || 'director-home' });
    }

    async function openInspection(company) {
      if (!company?.id || inspectionLoading.value) return;
      inspectionCompany.value = company;
      inspectionNote.value = company.attention_note || '';
      inspectionPondId.value = null;
      showInspection.value = true;
      await reloadInspection();
    }

    async function selectInspectionPond(pondId) {
      const id = Number(pondId);
      if (!id || inspectionLoading.value) return;
      if (inspectionPondId.value === id) return;
      inspectionPondId.value = id;
      await reloadInspection();
    }

    async function reloadInspection() {
      const company = inspectionCompany.value;
      if (!company?.id) return;
      inspectionLoading.value = true;
      try {
        const params = {};
        if (inspectionPondId.value) params.pond_id = inspectionPondId.value;
        const { data } = await window.axios.get(`/superadmin/companies/${company.id}/inspection`, { params });
        inspection.value = data;
        if (data?.pond?.id) {
          inspectionPondId.value = data.pond.id;
        } else if (data?.needs_pond) {
          inspectionPondId.value = null;
        }
      } catch (e) {
        window.alert(e.response?.data?.message || t('errorOccurred'));
        showInspection.value = false;
      } finally {
        inspectionLoading.value = false;
      }
    }

    function manageFromInspection(area = 'home') {
      const company = inspectionCompany.value || companies.value.find((c) => c.id === inspection.value?.company?.id);
      if (!company) return;
      const routes = inspection.value?.manage || {};
      const routeName = routes[area] || 'director-home';
      enterCompany({
        ...company,
        name: inspection.value?.company?.name || company.name,
        admin: inspection.value?.company?.admin || company.admin,
      }, routeName);
    }

    const canCompleteInspection = computed(() => {
      const data = inspection.value;
      if (!data) return false;
      if (data.needs_pond) return false;
      if (!inspectionPondId.value) return false;
      return data.can_complete !== false;
    });

    async function completeInspection() {
      const company = inspectionCompany.value;
      if (!company?.id || completingCheck.value || !canCompleteInspection.value) return;
      completingCheck.value = true;
      try {
        const { data } = await window.axios.post(`/superadmin/companies/${company.id}/check`, {
          note: inspectionNote.value || null,
        });
        const idx = companies.value.findIndex((c) => c.id === company.id);
        if (idx >= 0) companies.value[idx] = data;
        showInspection.value = false;
        inspection.value = null;
        inspectionCompany.value = null;
        inspectionPondId.value = null;
        inspectionNote.value = '';
      } catch (e) {
        window.alert(e.response?.data?.message || t('errorOccurred'));
      } finally {
        completingCheck.value = false;
      }
    }

    function closeInspection() {
      if (completingCheck.value) return;
      showInspection.value = false;
      inspectionPondId.value = null;
    }

    async function openInspectFromQuery() {
      const raw = route.query.inspect;
      if (!raw) return;
      const id = Number(raw);
      if (!id) return;
      const company = companies.value.find((c) => c.id === id);
      if (company) {
        await openInspection(company);
        router.replace({ query: { ...route.query, inspect: undefined } });
      }
    }

    function closeModal() {
      if (saving.value) return;
      showModal.value = false;
      editingId.value = null;
      showEditPass.value = false;
      error.value = '';
    }

    function openExtendTime() {
      const company = editingCompany.value;
      if (!company) return;
      extendCompany.value = company;
      extendPeriod.value = company.billing_period === 'year' ? 'year' : 'month';
      extendError.value = '';
      showExtend.value = true;
    }

    function closeExtendTime() {
      if (extendSaving.value) return;
      showExtend.value = false;
      extendCompany.value = null;
      extendError.value = '';
    }

    async function confirmExtendTime() {
      const company = extendCompany.value;
      if (!company?.id || extendSaving.value) return;
      extendSaving.value = true;
      extendError.value = '';
      try {
        const { data } = await window.axios.put(`/superadmin/companies/${company.id}`, {
          plan: company.plan || form.plan || 'cabinet',
          billing_period: extendPeriod.value,
          renew_plan: true,
        });
        const idx = companies.value.findIndex((c) => c.id === company.id);
        if (idx >= 0) companies.value[idx] = { ...companies.value[idx], ...data };
        showExtend.value = false;
        extendCompany.value = null;
      } catch (e) {
        extendError.value = e.response?.data?.message
          || Object.values(e.response?.data?.errors || {})?.[0]?.[0]
          || t('errorOccurred');
      } finally {
        extendSaving.value = false;
      }
    }

    async function saveCompany() {
      saving.value = true;
      error.value = '';
      try {
        const interval = Math.min(90, Math.max(1, Number(form.check_interval_days) || 7));
        form.check_interval_days = interval;
        const payload = {
          name: form.name,
          admin_name: form.admin_name,
          admin_login: form.admin_login,
          admin_phone: form.admin_phone,
          plan: form.plan,
          billing_period: form.billing_period,
          check_interval_days: interval,
        };
        if (form.admin_password) {
          payload.admin_password = form.admin_password;
        }

        if (editingId.value) {
          payload.quiet_mode = !!form.quiet_mode;
          await window.axios.put(`/superadmin/companies/${editingId.value}`, payload);
        } else {
          payload.admin_password = form.admin_password;
          await window.axios.post('/superadmin/companies', payload);
        }
        showModal.value = false;
        editingId.value = null;
        Object.assign(form, emptyForm());
        await load();
      } catch (e) {
        error.value = e.response?.data?.message || Object.values(e.response?.data?.errors || {})?.[0]?.[0] || t('errorOccurred');
      } finally {
        saving.value = false;
      }
    }

    async function toggleActive(company, event) {
      const next = !!event.target.checked;
      const prev = company.is_active;
      company.is_active = next;
      togglingId.value = company.id;
      try {
        const { data } = await window.axios.put(`/superadmin/companies/${company.id}`, {
          is_active: next,
        });
        Object.assign(company, data);
        const idx = companies.value.findIndex((c) => c.id === company.id);
        if (idx >= 0) companies.value[idx] = { ...companies.value[idx], ...data };
      } catch (e) {
        company.is_active = prev;
        if (event?.target) event.target.checked = prev;
        window.alert(e.response?.data?.message || Object.values(e.response?.data?.errors || {})?.[0]?.[0] || t('errorOccurred'));
      } finally {
        togglingId.value = null;
      }
    }

    async function toggleQuiet(company) {
      if (!company?.is_active) return;
      const next = !company.quiet_mode;
      const ok = window.confirm(next ? t('companyQuietEnableConfirm') : t('companyQuietDisableConfirm'));
      if (!ok) return;
      const prev = company.quiet_mode;
      company.quiet_mode = next;
      togglingQuietId.value = company.id;
      try {
        const { data } = await window.axios.put(`/superadmin/companies/${company.id}`, {
          quiet_mode: next,
        });
        Object.assign(company, data);
        const idx = companies.value.findIndex((c) => c.id === company.id);
        if (idx >= 0) companies.value[idx] = { ...companies.value[idx], ...data };
        if (form && editingId.value === company.id) {
          form.quiet_mode = !!data.quiet_mode;
        }
      } catch (e) {
        company.quiet_mode = prev;
        window.alert(e.response?.data?.message || Object.values(e.response?.data?.errors || {})?.[0]?.[0] || t('errorOccurred'));
      } finally {
        togglingQuietId.value = null;
      }
    }

    onMounted(async () => {
      await load();
      await openInspectFromQuery();
    });

    onBeforeUnmount(() => {
      stopChatPoll();
    });

    watch(() => route.query.inspect, () => {
      if (companies.value.length) openInspectFromQuery();
    });

    watch(showMessage, (open) => {
      if (!open) stopChatPoll();
    });

    return {
      companies,
      searchQuery,
      filteredCompanies,
      companyStatusLabel,
      loading,
      saving,
      togglingId,
      togglingQuietId,
      showModal,
      showEditPass,
      editingId,
      editingCompany,
      form,
      error,
      firstInput,
      pricingPlans,
      previewUntil,
      showInspection,
      inspectionLoading,
      completingCheck,
      inspection,
      inspectionCompany,
      inspectionNote,
      inspectionPondId,
      selectInspectionPond,
      canCompleteInspection,
      dayStatusLabel,
      problemAlertTitle,
      problemAlertDetail,
      formatAlertWhen,
      dayMiniLabel,
      medicineShort,
      medicineLines,
      feedAlgo,
      shortDate,
      weekdayShort,
      fishTypeLabel,
      waterCellClass,
      waterCellTitle,
      fmtNum,
      formatMoney,
      formatChangePct,
      financeChangeClass,
      financeLevelLabel,
      financeCategoryLabel,
      financeCategoryColor,
      financeCardTone,
      financeFlagText,
      formatDate,
      formatDateTime,
      planLabel,
      planOptionLabel,
      periodLabel,
      formatPlanPrice,
      periodDays,
      showExtend,
      extendCompany,
      extendPeriod,
      extendSaving,
      extendError,
      extendPreviewUntil,
      openExtendTime,
      closeExtendTime,
      confirmExtendTime,
      openCreate,
      openEdit,
      openMessage,
      closeMessage,
      sendCompanyMessage,
      showMessage,
      messageCompany,
      messageError,
      messageSending,
      chatMessages,
      chatLoading,
      enterCompany,
      openInspection,
      reloadInspection,
      manageFromInspection,
      completeInspection,
      closeInspection,
      closeModal,
      saveCompany,
      toggleActive,
      toggleQuiet,
    };
  },
};
</script>

<style scoped>
.owner-companies .page-head {
  align-items: flex-start;
  gap: 1rem;
}

.page-head__text {
  min-width: 0;
  flex: 1;
}

.oc-season-hint {
  margin: 0.35rem 0 0;
  max-width: 42rem;
  color: #667085;
  font-size: 0.88rem;
  line-height: 1.4;
}

.oc-form__power {
  grid-column: 1 / -1;
}

.oc-power {
  display: grid;
  gap: 0.2rem;
  width: 100%;
  text-align: left;
  padding: 0.85rem 1rem;
  border-radius: 12px;
  border: 1px solid #d0d5dd;
  background: #f8fafc;
  cursor: pointer;
}

.oc-power strong {
  font-size: 1rem;
}

.oc-power span {
  color: #667085;
  font-size: 0.84rem;
  line-height: 1.35;
}

.oc-power.is-on {
  border-color: #abefc6;
  background: #ecfdf3;
}

.oc-power.is-on strong { color: #027a48; }

.oc-power.is-off {
  border-color: #fecdca;
  background: #fef3f2;
}

.oc-power.is-off strong { color: #b42318; }

.oc-company-card.is-quiet {
  border-color: #f9dbaf;
  background: linear-gradient(180deg, #fffaf5 0%, #fff 48%);
}

.oc-empty {
  padding: 2rem 1rem;
  text-align: center;
  color: #667085;
  background: #fff;
  border: 1px dashed #d0d5dd;
  border-radius: 14px;
}

.oc-warn {
  margin: 0.75rem 0 0;
  padding: 0.55rem 0.7rem;
  border-radius: 10px;
  background: rgba(180, 83, 9, 0.12);
  color: #9a3412;
  font-size: 0.84rem;
  font-weight: 600;
}

.oc-form__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.85rem 1rem;
}

.oc-form__until input {
  opacity: 0.85;
}

.field-hint {
  margin: 0.35rem 0 0;
  font-size: 0.78rem;
  line-height: 1.35;
  color: #667085;
}

.oc-interval {
  display: flex;
  align-items: center;
  gap: 0.55rem;
}
.oc-interval input {
  max-width: 7rem;
}
.oc-interval span {
  font-size: 0.9rem;
  font-weight: 700;
  color: #475569;
}

.oc-company-card__interval {
  font-weight: 500;
  color: #667085;
}

.oc-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.65rem 1rem;
  margin: 0 0 1rem;
}
.oc-search {
  position: relative;
  flex: 1 1 240px;
  max-width: 420px;
}
.oc-search__input {
  width: 100%;
  min-height: 2.65rem;
  padding: 0.55rem 2.2rem 0.55rem 0.85rem;
  border: 1px solid #d0d5dd;
  border-radius: 12px;
  background: #fff;
  font-size: 0.95rem;
  color: #0f172a;
}
.oc-search__input:focus {
  outline: none;
  border-color: #0090f0;
  box-shadow: 0 0 0 3px rgba(0, 144, 240, 0.15);
}
.oc-search__clear {
  position: absolute;
  right: 0.35rem;
  top: 50%;
  transform: translateY(-50%);
  width: 1.8rem;
  height: 1.8rem;
  border: none;
  border-radius: 999px;
  background: transparent;
  color: #667085;
  font-size: 1.2rem;
  line-height: 1;
  cursor: pointer;
}
.oc-search__clear:hover {
  background: #f2f4f7;
  color: #0f172a;
}
.oc-toolbar__meta {
  margin: 0;
  font-size: 0.84rem;
  font-weight: 650;
  color: #667085;
}
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

.oc-form__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.65rem;
  margin-top: 1.15rem;
  padding-top: 1rem;
  border-top: 1px solid rgba(15, 23, 42, 0.06);
}

@media (max-width: 640px) {
  .oc-form__grid {
    grid-template-columns: 1fr;
  }
}

.oc-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.85rem;
}

@media (max-width: 720px) {
  .oc-grid {
    grid-template-columns: 1fr;
  }
}

.oc-company-card {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  padding: 0.9rem 1rem;
  border-radius: 14px;
  background: #fff;
  border: 1px solid rgba(15, 23, 42, 0.08);
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
  color: #0f172a;
}

.oc-company-card.is-off {
  background: #fafafa;
  opacity: 0.92;
}
.oc-company-card.is-due {
  border-color: rgba(180, 83, 9, 0.35);
  box-shadow: 0 0 0 1px rgba(180, 83, 9, 0.12);
}
.oc-company-card.is-check-soon {
  border-color: rgba(2, 132, 199, 0.28);
}
.oc-company-card__check {
  margin: 0 0 0.25rem;
  font-size: 0.78rem;
  font-weight: 650;
  color: #667085;
}
.oc-company-card__check strong { font-weight: 800; }
.oc-company-card__check.is-warn,
.oc-company-card__check.is-warn strong {
  color: #9a3412;
}

.oc-company-card__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}

.oc-company-card__id {
  font-size: 0.82rem;
  font-weight: 800;
  color: #667085;
}

.oc-badge {
  display: inline-flex;
  padding: 0.18rem 0.5rem;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 700;
}

.oc-badge.is-open {
  background: #ecfdf3;
  color: #027a48;
}

.oc-badge.is-closed {
  background: #fef3f2;
  color: #b42318;
}

.oc-company-card__name {
  font-size: 1.05rem;
  font-weight: 850;
  color: #0f172a;
  line-height: 1.25;
  word-break: break-word;
}

.oc-company-card__sub {
  margin: 0;
  font-size: 0.86rem;
  font-weight: 600;
  color: #475569;
}

.oc-company-card__meta {
  margin: 0 0 0.35rem;
  font-size: 0.8rem;
  font-weight: 650;
  color: #667085;
}

.oc-company-card__meta.is-warn {
  color: #9a3412;
}

.oc-company-card__facts {
  display: grid;
  gap: 0.35rem;
  margin: 0.15rem 0 0.2rem;
  padding: 0.55rem 0.65rem;
  border: 1px solid #eef2f6;
  border-radius: 10px;
  background: #f8fafc;
}

.oc-company-card__facts > div {
  display: grid;
  grid-template-columns: 7.5rem 1fr;
  gap: 0.4rem;
  align-items: baseline;
}

.oc-company-card__facts dt {
  margin: 0;
  font-size: 0.72rem;
  color: #98a2b3;
}

.oc-company-card__facts dd {
  margin: 0;
  font-size: 0.8rem;
  font-weight: 700;
  color: #344054;
}

.oc-company-card__facts dd.is-warn {
  color: #9a3412;
}

.oc-company-card__actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem;
  margin-top: auto;
  padding-top: 0.35rem;
}
.oc-company-card__actions--3 {
  grid-template-columns: 1.2fr 1fr 1fr;
}
.oc-company-card__actions--4 {
  grid-template-columns: 1.15fr 1fr 0.9fr 0.9fr;
}
@media (max-width: 720px) {
  .oc-company-card__actions--3,
  .oc-company-card__actions--4 {
    grid-template-columns: 1fr 1fr;
  }
}
@media (max-width: 560px) {
  .oc-company-card__actions--3,
  .oc-company-card__actions--4 {
    grid-template-columns: 1fr;
  }
}

.oc-btn--due {
  border-color: #b45309;
  background: #fff7ed;
  color: #9a3412;
}

.oc-badge.is-warn {
  background: #fff7ed;
  color: #9a3412;
}

.oc-sheet {
  display: grid;
  gap: 1rem;
}

.oc-inspect {
  gap: 1.15rem;
}

.oc-block {
  display: grid;
  gap: 0.55rem;
  padding: 0.85rem;
  border-radius: 16px;
  background: #f8fafc;
  border: 1px solid #e4e7ec;
}
.oc-block__head {
  display: flex;
  justify-content: space-between;
  gap: 0.75rem;
  align-items: flex-start;
}
.oc-block__head strong {
  display: block;
  font-size: 0.98rem;
  color: #0f172a;
}
.oc-block__head span {
  display: block;
  margin-top: 0.15rem;
  font-size: 0.8rem;
  color: #667085;
}
.oc-block__dots {
  display: flex;
  gap: 0.35rem;
}
.oc-block__dots em {
  min-width: 1.6rem;
  text-align: center;
  font-style: normal;
  font-weight: 850;
  font-size: 0.82rem;
  padding: 0.2rem 0.4rem;
  border-radius: 999px;
}
.oc-block__dots .is-green { background: #0b7a45; color: #fff; }
.oc-block__dots .is-yellow { background: #e5a100; color: #1a1520; }
.oc-block__dots .is-red { background: #d61f3a; color: #fff; }

.oc-problem-alerts__count {
  font-style: normal;
  font-weight: 800;
  font-size: 0.85rem;
  min-width: 1.6rem;
  text-align: center;
  padding: 0.2rem 0.45rem;
  border-radius: 999px;
  background: #fef3c7;
  color: #92400e;
}

.oc-problem-alerts__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.45rem;
}

.oc-problem-alert {
  display: grid;
  gap: 0.25rem;
  padding: 0.7rem 0.8rem;
  border-radius: 12px;
  border: 1px solid #fde68a;
  background: #fffbeb;
}

.oc-problem-alert__head {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.35rem 0.75rem;
}

.oc-problem-alert strong {
  font-size: 0.9rem;
  color: #92400e;
}

.oc-problem-alert__when {
  font-size: 0.78rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  color: #a16207;
  white-space: nowrap;
}

.oc-problem-alert span {
  font-size: 0.84rem;
  color: #78350f;
  line-height: 1.4;
}

.oc-problem-alert.is-danger {
  border-color: #fecaca;
  background: #fef2f2;
}

.oc-problem-alert.is-danger strong {
  color: #991b1b;
}

.oc-problem-alert.is-danger span {
  color: #7f1d1d;
}

.oc-block__legend {
  display: flex;
  flex-wrap: wrap;
  gap: 0.65rem 1rem;
  margin: 0;
  font-size: 0.75rem;
  font-weight: 700;
  color: #475569;
}
.oc-block__legend .is-green { color: #0b7a45; }
.oc-block__legend .is-yellow { color: #b77900; }
.oc-block__legend .is-red { color: #d61f3a; }

.oc-card-rail {
  display: flex;
  flex-wrap: nowrap;
  gap: 0.6rem;
  overflow-x: auto;
  overflow-y: hidden;
  padding: 0.1rem 0.05rem 0.5rem;
  scroll-snap-type: x mandatory;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: thin;
}
.oc-card-rail::-webkit-scrollbar { height: 6px; }
.oc-card-rail::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 999px;
}

.oc-block__limit {
  font-size: 0.75rem;
  font-weight: 750;
  color: #667085;
  white-space: nowrap;
}

.oc-pond-pick__list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
}
.oc-pond-pick__btn {
  appearance: none;
  border: 1px solid #d0d5dd;
  background: #fff;
  color: #334155;
  font-size: 0.84rem;
  font-weight: 750;
  padding: 0.45rem 0.85rem;
  border-radius: 999px;
  cursor: pointer;
  transition: background 0.12s ease, border-color 0.12s ease, color 0.12s ease;
}
.oc-pond-pick__btn:hover:not(:disabled) {
  border-color: #0090f0;
  color: #00579a;
}
.oc-pond-pick__btn.is-active {
  background: #002030;
  border-color: #002030;
  color: #fff;
}
.oc-pond-pick__btn:disabled {
  opacity: 0.55;
  cursor: wait;
}

.oc-card-rail--loss .oc-card,
.oc-card-rail--finance .oc-card {
  flex: 0 0 calc((100% - 2.4rem) / 5);
  width: calc((100% - 2.4rem) / 5);
  max-width: calc((100% - 2.4rem) / 5);
  scroll-snap-align: start;
}
@media (max-width: 720px) {
  .oc-card-rail--loss .oc-card,
  .oc-card-rail--finance .oc-card {
    flex-basis: calc((100% - 1.2rem) / 3);
    width: calc((100% - 1.2rem) / 3);
    max-width: calc((100% - 1.2rem) / 3);
  }
}

.oc-finance__level {
  font-style: normal;
  font-size: 0.75rem;
  font-weight: 800;
  padding: 0.22rem 0.6rem;
  border-radius: 999px;
}
.oc-finance__level.is-ok { background: #eef7f1; color: #3d6b4f; }
.oc-finance__level.is-caution { background: #fff7e6; color: #9a6b00; }
.oc-finance__level.is-danger { background: #fde8eb; color: #a1283a; }

.oc-finance__kpis {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.5rem;
}
@media (max-width: 640px) {
  .oc-finance__kpis { grid-template-columns: 1fr; }
}
.oc-finance__kpi {
  display: grid;
  gap: 0.2rem;
  padding: 0.65rem 0.75rem;
  border-radius: 12px;
  background: #fff;
  border: 1px solid #e8edf3;
}
.oc-finance__kpi span {
  font-size: 0.7rem;
  font-weight: 750;
  color: #667085;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.oc-finance__kpi b {
  font-size: 1.05rem;
  font-weight: 850;
  color: #0f172a;
}
.oc-finance__kpi.is-up b { color: #b91c1c; }
.oc-finance__kpi.is-down b { color: #15803d; }

.oc-fin-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(168px, 1fr));
  gap: 0.65rem;
}
.oc-fin-grid--days {
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
}

.oc-fin-card {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 0.9rem 0.95rem;
  border-radius: 14px;
  border: 1px solid rgba(10, 60, 40, 0.1);
  background: #f7faf8;
}
.oc-fin-card__top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.35rem;
}
.oc-fin-card__top strong {
  font-size: 0.98rem;
  color: #0f172a;
}
.oc-fin-card__sum {
  font-size: 1.05rem;
  font-weight: 850;
  color: #065c36;
}
.oc-fin-card__bar {
  height: 6px;
  border-radius: 999px;
  background: rgba(10, 60, 40, 0.08);
  overflow: hidden;
  margin: 0.15rem 0 0.1rem;
}
.oc-fin-card__bar i {
  display: block;
  height: 100%;
  border-radius: inherit;
  max-width: 100%;
}
.oc-fin-card small {
  color: #5a7164;
  font-size: 0.78rem;
  font-weight: 650;
}
.oc-fin-card--warehouse small { color: #0d8a52; }

.oc-fin-card--feed { border-left: 4px solid #2e7d32; background: linear-gradient(160deg, #e8f5e9, #fff); }
.oc-fin-card--medicine { border-left: 4px solid #c62828; background: linear-gradient(160deg, #fce4ec, #fff); }
.oc-fin-card--equipment { border-left: 4px solid #455a64; background: linear-gradient(160deg, #eceff1, #fff); }
.oc-fin-card--main { border-left: 4px solid #1565c0; background: linear-gradient(160deg, #e3f2fd, #fff); }
.oc-fin-card--salary { border-left: 4px solid #00838f; background: linear-gradient(160deg, #e0f7fa, #fff); }
.oc-fin-card--utilities { border-left: 4px solid #ef6c00; background: linear-gradient(160deg, #fff3e0, #fff); }
.oc-fin-card--contingent { border-left: 4px solid #6a1b9a; background: linear-gradient(160deg, #f3e5f5, #fff); }
.oc-fin-card--transport { border-left: 4px solid #ef6c00; background: linear-gradient(160deg, #fff3e0, #fff); }
.oc-fin-card--other { border-left: 4px solid #757575; background: linear-gradient(160deg, #f5f5f5, #fff); }
.oc-fin-card--spike {
  border-left: 4px solid #c62828;
  background: linear-gradient(160deg, #fdecea, #fff);
}
.oc-fin-card--spike .oc-fin-card__sum { color: #b91c1c; }

.oc-finance__flags {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.35rem;
}
.oc-finance__flags li {
  padding: 0.55rem 0.7rem;
  border-radius: 10px;
  font-size: 0.84rem;
  font-weight: 650;
  line-height: 1.35;
}
.oc-finance__flags li.is-caution {
  background: #fff7e6;
  color: #8a5a00;
}
.oc-finance__flags li.is-danger {
  background: #fde8eb;
  color: #912018;
}
.oc-finance__open {
  justify-self: start;
  width: fit-content;
}

.oc-card-rail--daily .oc-card {
  flex: 0 0 calc((100% - 2.4rem) / 5);
  width: calc((100% - 2.4rem) / 5);
  max-width: calc((100% - 2.4rem) / 5);
  scroll-snap-align: start;
}
.oc-card-rail--water .oc-card {
  flex: 0 0 calc((100% - 1.2rem) / 3);
  width: calc((100% - 1.2rem) / 3);
  max-width: calc((100% - 1.2rem) / 3);
  scroll-snap-align: start;
}
.oc-card-rail--control .oc-card {
  flex: 0 0 calc((100% - 0.6rem) / 2);
  width: calc((100% - 0.6rem) / 2);
  max-width: calc((100% - 0.6rem) / 2);
  scroll-snap-align: start;
}
@media (max-width: 900px) {
  .oc-card-rail--water .oc-card {
    flex-basis: calc((100% - 0.6rem) / 2);
    width: calc((100% - 0.6rem) / 2);
    max-width: calc((100% - 0.6rem) / 2);
  }
}
@media (max-width: 720px) {
  .oc-card-rail--daily .oc-card {
    flex-basis: calc((100% - 1.2rem) / 3);
    width: calc((100% - 1.2rem) / 3);
    max-width: calc((100% - 1.2rem) / 3);
  }
  .oc-card-rail--control .oc-card {
    flex-basis: 85%;
    width: 85%;
    max-width: 85%;
  }
}
@media (max-width: 480px) {
  .oc-card-rail--daily .oc-card,
  .oc-card-rail--water .oc-card {
    flex-basis: calc((100% - 0.6rem) / 2);
    width: calc((100% - 0.6rem) / 2);
    max-width: calc((100% - 0.6rem) / 2);
  }
}

/* Tekshiruv kartalari — faqat rail ichida (ro‘yxat kartalari bilan chalkashmasin) */
.oc-card-rail .oc-card {
  --oc-ink: #f4f8fc;
  --oc-muted: rgba(244, 248, 252, 0.78);
  --oc-chip: rgba(255, 255, 255, 0.16);
  --oc-chip-border: rgba(255, 255, 255, 0.18);
  position: relative;
  box-sizing: border-box;
  display: flex;
  min-height: 124px;
  border-radius: 14px;
  border: 1px solid transparent;
  box-shadow: 0 4px 14px rgba(0, 16, 32, 0.18);
  overflow: hidden;
  color: var(--oc-ink);
}
.oc-card__accent {
  flex: 0 0 6px;
  width: 6px;
  background: #0090f0;
}
.oc-card.is-green {
  background: linear-gradient(160deg, #0b7a45 0%, #065c34 100%);
  border-color: #054d2b;
}
.oc-card.is-green .oc-card__accent { background: #3ddc84; }
.oc-card.is-yellow {
  --oc-ink: #1a1520;
  --oc-muted: rgba(26, 21, 32, 0.72);
  --oc-chip: rgba(255, 255, 255, 0.42);
  --oc-chip-border: rgba(26, 21, 32, 0.08);
  background: linear-gradient(160deg, #ffcc33 0%, #e5a100 100%);
  border-color: #c78a00;
}
.oc-card.is-yellow .oc-card__accent { background: #002030; }
.oc-card.is-red {
  background: linear-gradient(160deg, #e0354c 0%, #b4152e 100%);
  border-color: #8f1024;
}
.oc-card.is-red .oc-card__accent { background: #ff8a9a; }
.oc-card.is-sky {
  background: linear-gradient(160deg, #0090f0 0%, #0068b8 55%, #002030 140%);
  border-color: #00579a;
}
.oc-card.is-sky .oc-card__accent { background: #7ad0ff; }
.oc-card.is-navy {
  background: linear-gradient(160deg, #102030 0%, #002030 55%, #001018 100%);
  border-color: #001020;
}
.oc-card.is-navy .oc-card__accent { background: #0090f0; }

.oc-card--wide {
  min-height: 148px;
}
.oc-card__body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  padding: 0.75rem 0.8rem 0.7rem 0.75rem;
}
.oc-card__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.35rem;
}
.oc-card__head strong {
  display: block;
  font-size: 0.98rem;
  font-weight: 850;
  color: var(--oc-ink);
  letter-spacing: 0.01em;
}
.oc-card__head span,
.oc-card__sub {
  display: block;
  margin-top: 0.1rem;
  font-size: 0.68rem;
  font-weight: 700;
  color: var(--oc-muted);
  text-transform: lowercase;
}
.oc-card__pond {
  margin: 0;
  font-size: 0.8rem;
  font-weight: 750;
  color: var(--oc-ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.oc-card__metric {
  display: grid;
  gap: 0.2rem;
  padding: 0.35rem 0.42rem;
  border-radius: 9px;
  background: var(--oc-chip);
  border: 1px solid var(--oc-chip-border);
}
.oc-card__metric-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.3rem;
}
.oc-card__metric-label {
  font-size: 0.64rem;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--oc-muted);
}
.oc-card__metric-val {
  font-size: 0.9rem;
  font-weight: 850;
  color: var(--oc-ink);
  line-height: 1.2;
}
.oc-card__med {
  display: flex;
  justify-content: space-between;
  gap: 0.35rem;
  align-items: baseline;
}
.oc-card__med span {
  font-size: 0.74rem;
  font-weight: 700;
  color: var(--oc-ink);
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.oc-card__med b {
  flex-shrink: 0;
  font-size: 0.8rem;
  font-weight: 850;
  color: var(--oc-ink);
}
.oc-feed-algo {
  background: #fbfcfd;
  border-color: #e8eef3;
}
.oc-feed-algo__stats {
  display: flex;
  gap: 0.35rem;
  flex-wrap: wrap;
}
.oc-feed-algo__stats em {
  font-style: normal;
  font-size: 0.74rem;
  font-weight: 750;
  padding: 0.2rem 0.55rem;
  border-radius: 999px;
  letter-spacing: 0.01em;
}
.oc-feed-algo__stats .is-up { background: #eef7f1; color: #3d6b4f; }
.oc-feed-algo__stats .is-down { background: #f8eef0; color: #8a4a55; }
.oc-feed-algo__stats .is-total { background: #eef3f7; color: #4a6070; }

.oc-feed-algo__chart {
  display: grid;
  gap: 0.7rem;
}
.oc-feed-algo__svg {
  width: 100%;
  height: 156px;
  display: block;
  border-radius: 14px;
  background: linear-gradient(180deg, #f7f9fb 0%, #eef3f7 100%);
  border: 1px solid #e4ebf0;
}
.oc-feed-algo__grid {
  stroke: rgba(74, 96, 112, 0.1);
  stroke-width: 1;
  stroke-dasharray: 3 6;
}
.oc-feed-algo__line {
  stroke: #6f92a8;
  stroke-width: 2.1;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.oc-feed-algo__dot {
  fill: #fff;
  stroke: #6f92a8;
  stroke-width: 1.6;
}
.oc-feed-algo__dot.is-up { stroke: #6a9a7c; fill: #eef7f1; }
.oc-feed-algo__dot.is-down { stroke: #c47a86; fill: #f8eef0; }
.oc-feed-algo__dot.is-flat { stroke: #6f92a8; fill: #fff; }

.oc-feed-algo__axis {
  display: grid;
  grid-template-columns: repeat(10, minmax(0, 1fr));
  gap: 0.2rem;
}
@media (max-width: 720px) {
  .oc-feed-algo__axis {
    grid-template-columns: repeat(5, minmax(0, 1fr));
  }
}
.oc-feed-algo__col {
  display: grid;
  gap: 0.08rem;
  justify-items: center;
  text-align: center;
  padding: 0.3rem 0.1rem;
  border-radius: 10px;
  background: transparent;
  border: none;
}
.oc-feed-algo__col strong {
  font-size: 0.7rem;
  font-weight: 750;
  color: #334155;
}
.oc-feed-algo__delta {
  font-size: 0.6rem;
  font-weight: 700;
  color: #94a3b8;
}
.oc-feed-algo__col.is-up .oc-feed-algo__delta { color: #5f8a6e; }
.oc-feed-algo__col.is-down .oc-feed-algo__delta { color: #a86a74; }
.oc-feed-algo__col em {
  font-style: normal;
  font-size: 0.6rem;
  font-weight: 650;
  color: #a8b4c0;
}
.oc-card__grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.35rem;
}
.oc-card__cell {
  display: grid;
  gap: 0.12rem;
  padding: 0.4rem 0.45rem;
  border-radius: 9px;
  background: var(--oc-chip);
  border: 1px solid var(--oc-chip-border);
  transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}
.oc-card__cell--full {
  grid-column: 1 / -1;
}
.oc-card__cell span {
  font-size: 0.62rem;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--oc-muted);
}
.oc-card__cell b {
  font-size: 0.95rem;
  font-weight: 850;
  color: var(--oc-ink);
}
/* O‘lchanmagan — kartochka rangida qoladi */
.oc-card__cell.is-none {
  background: var(--oc-chip);
  border-color: var(--oc-chip-border);
}
/* Normal */
.oc-card__cell.is-ok {
  background: rgba(34, 197, 94, 0.92);
  border-color: rgba(255, 255, 255, 0.28);
}
.oc-card__cell.is-ok span,
.oc-card__cell.is-ok b {
  color: #fff;
}
/* Ehtiyot */
.oc-card__cell.is-caution {
  background: rgba(234, 179, 8, 0.95);
  border-color: rgba(255, 255, 255, 0.25);
}
.oc-card__cell.is-caution span {
  color: rgba(26, 21, 32, 0.72);
}
.oc-card__cell.is-caution b {
  color: #1a1520;
}
/* Xavfli */
.oc-card__cell.is-danger {
  background: rgba(220, 38, 38, 0.95);
  border-color: rgba(255, 255, 255, 0.28);
}
.oc-card__cell.is-danger span,
.oc-card__cell.is-danger b {
  color: #fff;
}
.oc-card__weights {
  display: grid;
  gap: 0.35rem;
}
.oc-card__weight {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 0.5rem;
  padding: 0.55rem 0.65rem;
  border-radius: 10px;
  background: var(--oc-chip);
  border: 1px solid var(--oc-chip-border);
}
.oc-card__weight span {
  font-size: 0.8rem;
  font-weight: 750;
  color: var(--oc-muted);
}
.oc-card__weight b {
  font-size: 1.05rem;
  font-weight: 850;
  color: var(--oc-ink);
}
.oc-card__weight small {
  font-size: 0.72rem;
  font-weight: 700;
  opacity: 0.8;
}
.oc-card__foot {
  margin-top: auto;
  font-size: 0.7rem;
  font-weight: 750;
  color: var(--oc-muted);
}
.oc-card__empty {
  margin: auto 0 0;
  font-size: 0.8rem;
  font-weight: 850;
  color: var(--oc-ink);
}

.oc-extra__empty {
  padding: 0.65rem 0.75rem;
  border-radius: 10px;
  background: #fff;
  border: 1px dashed #d0d5dd;
  color: #667085;
  font-size: 0.8rem;
  font-weight: 650;
}

.oc-sheet__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.85rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid #e4e7ec;
}

.oc-sheet__company {
  display: block;
  font-size: 1.2rem;
  letter-spacing: -0.01em;
}

.oc-sheet__meta {
  margin: 0.3rem 0 0;
  color: #667085;
  font-size: 0.86rem;
  line-height: 1.4;
}

.oc-sheet__hint {
  margin: 0;
  color: #475569;
  font-size: 0.9rem;
  line-height: 1.45;
}

.oc-sheet__verdict {
  display: grid;
  gap: 0.45rem;
  padding: 0.75rem 0.85rem;
  border-radius: 12px;
  background: #f8fafc;
  border: 1px solid #e4e7ec;
}

.oc-sheet__verdict-label {
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #98a2b3;
}

.oc-sheet__chips {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.45rem;
}

.oc-sheet__chip {
  display: grid;
  gap: 0.15rem;
  padding: 0.5rem 0.6rem;
  border-radius: 10px;
  background: #fff;
  border: 1px solid #e4e7ec;
}

.oc-sheet__chip em {
  font-style: normal;
  font-size: 0.72rem;
  color: #667085;
}

.oc-sheet__chip strong {
  font-size: 0.88rem;
}

.oc-sheet__chip.is-ok strong { color: #027a48; }
.oc-sheet__chip.is-warn strong { color: #b54708; }
.oc-sheet__chip.is-bad strong { color: #b42318; }

.oc-sheet__block {
  display: grid;
  gap: 0.65rem;
  padding: 0.9rem 1rem 1rem;
  border: 1px solid #e4e7ec;
  border-radius: 14px;
  background: #fff;
  border-top: 3px solid #12b76a;
}

.oc-sheet__block.is-warn { border-top-color: #f79009; }
.oc-sheet__block.is-bad { border-top-color: #f04438; }

.oc-sheet__block-head {
  display: flex;
  justify-content: space-between;
  gap: 0.75rem;
  align-items: flex-start;
}

.oc-sheet__block-head strong {
  display: block;
  font-size: 1.02rem;
}

.oc-sheet__block-head p {
  margin: 0.25rem 0 0;
  color: #475569;
  font-size: 0.86rem;
  line-height: 1.35;
}

.oc-sheet__block h4 {
  margin: 0.35rem 0 0;
  font-size: 0.8rem;
  font-weight: 800;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  color: #667085;
}

.oc-sheet__badge {
  flex-shrink: 0;
  padding: 0.28rem 0.65rem;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 800;
  background: #ecfdf3;
  color: #027a48;
}

.oc-sheet__block.is-warn .oc-sheet__badge {
  background: #ffead5;
  color: #b54708;
}

.oc-sheet__block.is-bad .oc-sheet__badge {
  background: #fee4e2;
  color: #b42318;
}

.oc-sheet__kpis {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.45rem;
}

.oc-sheet__kpis div {
  background: #f8fafc;
  border: 1px solid #eef2f6;
  border-radius: 10px;
  padding: 0.5rem 0.6rem;
}

.oc-sheet__kpis span {
  display: block;
  font-size: 0.7rem;
  color: #667085;
  margin-bottom: 0.15rem;
}

.oc-sheet__kpis b { font-size: 1rem; }
.oc-sheet__kpis .is-ok b { color: #027a48; }
.oc-sheet__kpis .is-late b { color: #b54708; }
.oc-sheet__kpis .is-miss b { color: #b42318; }

.oc-sheet__split {
  display: grid;
  grid-template-columns: 0.9fr 1.1fr;
  gap: 0.75rem;
}

.oc-sheet__table-wrap {
  overflow: auto;
  border: 1px solid #e4e7ec;
  border-radius: 10px;
  max-height: 280px;
  background: #fff;
}

.oc-sheet__table-wrap--sm {
  max-height: 220px;
}

.oc-sheet__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.82rem;
}

.oc-sheet__table--dense {
  font-size: 0.78rem;
}

.oc-sheet__table th,
.oc-sheet__table td {
  padding: 0.48rem 0.6rem;
  text-align: left;
  border-bottom: 1px solid #eef2f6;
  white-space: nowrap;
  vertical-align: middle;
}

.oc-sheet__table th {
  position: sticky;
  top: 0;
  z-index: 1;
  background: #f8fafc;
  color: #667085;
  font-size: 0.68rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.oc-sheet__table tbody tr:last-child td { border-bottom: 0; }
.oc-sheet__table td.is-ok { color: #027a48; font-weight: 700; }
.oc-sheet__table td.is-late { color: #b54708; font-weight: 700; }
.oc-sheet__table td.is-miss { color: #b42318; font-weight: 700; }
.oc-sheet__table td.is-empty {
  color: #98a2b3;
  text-align: center;
  white-space: normal;
  padding: 0.85rem;
}

.oc-sheet__table tr.is-row-late { background: #fff7ed; }
.oc-sheet__table tr.is-row-miss { background: #fef3f2; }

.oc-sheet__pill {
  display: inline-block;
  font-style: normal;
  font-size: 0.7rem;
  font-weight: 800;
  padding: 0.12rem 0.45rem;
  border-radius: 999px;
  background: #ecfdf3;
  color: #027a48;
}

.oc-sheet__pill.is-warn {
  background: #ffead5;
  color: #b54708;
}

.oc-sheet__pill.is-bad {
  background: #fee4e2;
  color: #b42318;
}

.oc-sheet__alert {
  padding: 0.55rem 0.7rem;
  border-radius: 10px;
  background: #fef3f2;
  color: #912018;
  font-size: 0.84rem;
  line-height: 1.4;
}

.oc-sheet__flags {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.3rem;
}

.oc-sheet__flags li {
  padding: 0.45rem 0.6rem;
  border-radius: 8px;
  background: #fff7ed;
  color: #9a3412;
  font-size: 0.84rem;
}

.oc-sheet__note { margin-top: 0.15rem; }

.oc-sheet__footer {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  justify-content: flex-end;
}

@media (max-width: 900px) {
  .oc-sheet__chips,
  .oc-sheet__kpis,
  .oc-sheet__split {
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 640px) {
  .oc-sheet__head {
    flex-direction: column;
  }

  .oc-sheet__chips,
  .oc-sheet__kpis,
  .oc-sheet__split {
    grid-template-columns: 1fr;
  }

  .oc-sheet__footer .btn {
    flex: 1 1 calc(50% - 0.25rem);
  }
}


.oc-btn {
  min-height: 38px;
  padding: 0.4rem 0.65rem;
  border-radius: 10px;
  border: 1px solid #d0d5dd;
  background: #fff;
  color: #344054;
  font-size: 0.84rem;
  font-weight: 700;
  cursor: pointer;
}

.oc-btn:hover {
  background: #f9fafb;
}

.oc-btn--accent {
  border-color: #47c269;
  color: #1f7a3d;
}

.oc-btn--accent:hover {
  background: rgba(71, 194, 105, 0.08);
}

.oc-btn--message {
  position: relative;
  border-color: #84caff;
  color: #026aa2;
}

.oc-btn--message:hover {
  background: rgba(2, 132, 199, 0.08);
}

.oc-chat-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.15rem;
  height: 1.15rem;
  margin-left: 0.35rem;
  padding: 0 0.28rem;
  border-radius: 999px;
  background: #f04438;
  color: #fff;
  font-size: 0.68rem;
  font-weight: 800;
  line-height: 1;
}

.oc-form__until-block {
  display: grid;
  gap: 0.45rem;
  align-content: start;
}

.oc-form__until-block .btn {
  justify-self: start;
}

.oc-extend {
  display: grid;
  gap: 0.85rem;
}

.oc-extend__hint,
.oc-extend__current,
.oc-extend__result {
  margin: 0;
  font-size: 0.9rem;
  line-height: 1.4;
  color: #475569;
}

.oc-extend__current strong,
.oc-extend__result strong {
  color: #101828;
}

.oc-extend__choices {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.55rem;
}

.oc-extend__choice {
  display: flex;
  align-items: flex-start;
  gap: 0.55rem;
  padding: 0.75rem 0.8rem;
  border: 1px solid #d0d5dd;
  border-radius: 12px;
  background: #fff;
  cursor: pointer;
}

.oc-extend__choice.is-on {
  border-color: #0d6b3f;
  background: #ecfdf3;
}

.oc-extend__choice input {
  margin-top: 0.2rem;
  accent-color: #0d6b3f;
}

.oc-extend__choice strong {
  display: block;
  font-size: 0.95rem;
  color: #101828;
}

.oc-extend__choice small {
  color: #667085;
  font-size: 0.78rem;
}

@media (max-width: 520px) {
  .oc-extend__choices {
    grid-template-columns: 1fr;
  }
}

.oc-edit-meta {
  margin: 0;
  font-size: 0.9rem;
  font-weight: 650;
  color: #344054;
}

.oc-edit-meta.is-warn {
  color: #b54708;
}

.oc-edit-meta small {
  font-weight: 500;
  color: #667085;
}

.oc-message-form {
  display: grid;
  gap: 0.85rem;
}

.oc-message-form__hint {
  margin: 0;
  font-size: 0.88rem;
  line-height: 1.4;
  color: #667085;
}

.oc-message-form textarea {
  width: 100%;
  min-height: 7rem;
  resize: vertical;
  font: inherit;
  line-height: 1.45;
}

.oc-btn--primary {
  border-color: #0d6b3f;
  background: #0d6b3f;
  color: #fff;
}

.oc-btn--primary:hover {
  background: #0a5a35;
  border-color: #0a5a35;
}

.oc-warn--soft {
  background: rgba(2, 132, 199, 0.1);
  color: #075985;
}

.oc-detail__grid {
  margin: 0;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.85rem 1rem;
}

.oc-detail__grid dt {
  margin: 0 0 0.2rem;
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  color: #98a2b3;
}

.oc-detail__grid dd {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 650;
  color: #0f172a;
}

.oc-detail__grid dd.is-warn {
  color: #9a3412;
}

.oc-detail__switches {
  grid-column: 1 / -1;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.85rem 1rem;
}

@media (max-width: 560px) {
  .oc-detail__switches {
    grid-template-columns: 1fr;
  }
}

.status-switch.is-disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.oc-mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 0.9rem;
}

.oc-pass {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}

@media (max-width: 560px) {
  .oc-detail__grid {
    grid-template-columns: 1fr;
  }
}

.status-switch {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  user-select: none;
}

.status-switch.is-busy {
  opacity: 0.65;
  pointer-events: none;
}

.status-switch input {
  position: absolute;
  inset: 0;
  opacity: 0;
  width: 100%;
  height: 100%;
  margin: 0;
  cursor: pointer;
}

.status-switch__text {
  font-size: 0.82rem;
  font-weight: 700;
  color: #667085;
}

.status-switch.is-on .status-switch__text {
  color: #027a48;
}

.status-switch__track {
  width: 2.55rem;
  height: 1.4rem;
  border-radius: 999px;
  background: #d0d5dd;
  position: relative;
  display: inline-block;
  pointer-events: none;
}

.status-switch.is-on .status-switch__track {
  background: #12b76a;
}

.status-switch__thumb {
  position: absolute;
  top: 0.15rem;
  left: 0.15rem;
  width: 1.1rem;
  height: 1.1rem;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.25);
  transition: transform 0.2s ease;
}

.status-switch.is-on .status-switch__thumb {
  transform: translateX(1.15rem);
}

/* ——— Mobile: barcha bo‘limlar + tekshiruv ichki bosqichlari ——— */
@media (max-width: 720px) {
  .owner-companies .page-head {
    flex-direction: column;
    align-items: stretch;
    gap: 0.75rem;
  }
  .owner-companies .page-head .btn {
    width: 100%;
  }
  .owner-companies .oc-season-hint {
    font-size: 0.82rem;
    line-height: 1.4;
  }
  .oc-toolbar {
    flex-direction: column;
    align-items: stretch;
  }
  .oc-search {
    max-width: none;
  }
  .oc-grid {
    gap: 0.75rem;
  }
  .oc-company-card {
    padding: 0.85rem 0.9rem;
    gap: 0.4rem;
  }
  .oc-company-card__name {
    font-size: 1rem;
  }
  .oc-company-card__facts > div {
    grid-template-columns: 1fr;
    gap: 0.15rem;
  }
  .oc-company-card__facts dt {
    font-size: 0.68rem;
  }
  .oc-company-card__facts dd {
    font-size: 0.84rem;
  }

  .oc-company-card__actions,
  .oc-company-card__actions--3,
  .oc-company-card__actions--4 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.45rem;
  }
  .oc-company-card__actions .oc-btn {
    width: 100%;
    min-height: 2.55rem;
    font-size: 0.82rem;
  }

  .oc-sheet.oc-inspect {
    gap: 0.85rem;
  }

  .oc-sheet__head {
    flex-direction: column;
    align-items: stretch;
  }
  .oc-sheet__head .btn {
    width: 100%;
  }
  .oc-sheet__company {
    font-size: 1.05rem;
  }

  .oc-block {
    padding: 0.7rem;
    border-radius: 14px;
    gap: 0.5rem;
  }
  .oc-block__head {
    flex-direction: column;
    align-items: stretch;
    gap: 0.45rem;
  }
  .oc-block__head strong {
    font-size: 0.95rem;
  }
  .oc-block__head span {
    font-size: 0.78rem;
  }
  .oc-block__dots {
    align-self: flex-start;
  }
  .oc-block__legend {
    font-size: 0.72rem;
    gap: 0.45rem 0.75rem;
  }

  .oc-pond-pick__list {
    gap: 0.4rem;
  }
  .oc-pond-pick__btn {
    flex: 1 1 calc(50% - 0.4rem);
    text-align: center;
    padding: 0.55rem 0.65rem;
    font-size: 0.86rem;
    min-height: 2.6rem;
  }

  .oc-card-rail {
    gap: 0.5rem;
    padding-bottom: 0.35rem;
    scroll-padding-inline: 0.15rem;
  }

  .oc-card--wide,
  .oc-card {
    min-height: 0;
  }
  .oc-card__body {
    padding: 0.6rem 0.65rem 0.55rem 0.6rem;
    gap: 0.3rem;
  }
  .oc-card__head strong {
    font-size: 0.9rem;
  }
  .oc-card__metric-val {
    font-size: 0.86rem;
  }
  .oc-card__grid {
    gap: 0.3rem;
  }
  .oc-card__cell {
    padding: 0.35rem 0.4rem;
  }
  .oc-card__cell b {
    font-size: 0.88rem;
  }

  .oc-feed-algo__svg {
    height: 120px;
  }
  .oc-feed-algo__stats {
    width: 100%;
  }
  .oc-feed-algo__axis {
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 0.2rem;
  }
  .oc-feed-algo__col {
    padding: 0.28rem 0.08rem;
  }
  .oc-feed-algo__col strong {
    font-size: 0.64rem;
  }
  .oc-feed-algo__delta,
  .oc-feed-algo__col em {
    font-size: 0.56rem;
  }

  .oc-finance__kpis {
    grid-template-columns: 1fr;
  }
  .oc-fin-grid {
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
  }
  .oc-fin-card {
    padding: 0.75rem 0.7rem;
  }
  .oc-fin-card__top strong {
    font-size: 0.88rem;
  }
  .oc-fin-card__sum {
    font-size: 0.95rem;
  }
  .oc-fin-card small {
    font-size: 0.72rem;
  }

  .oc-finance__flags li {
    font-size: 0.8rem;
    padding: 0.5rem 0.6rem;
  }
  .oc-finance__open {
    width: 100%;
  }

  .oc-sheet__note textarea {
    min-height: 4.5rem;
    font-size: 1rem;
  }

  .oc-sheet__footer {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.45rem;
  }
  .oc-sheet__footer .btn {
    width: 100%;
    min-height: 2.7rem;
  }
}

@media (max-width: 480px) {
  .oc-company-card__actions,
  .oc-company-card__actions--3,
  .oc-company-card__actions--4 {
    grid-template-columns: 1fr;
  }

  .oc-pond-pick__btn {
    flex-basis: 100%;
  }

  .oc-fin-grid {
    grid-template-columns: 1fr;
  }

  .oc-feed-algo__axis {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .oc-card-rail--daily .oc-card,
  .oc-card-rail--loss .oc-card,
  .oc-card-rail--finance .oc-card,
  .oc-card-rail--water .oc-card,
  .oc-card-rail--control .oc-card {
    flex-basis: 78%;
    width: 78%;
    max-width: 78%;
  }
}
</style>
