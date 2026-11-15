<template>
  <div class="contract-page">
    <header class="contract-page__bar">
      <router-link class="contract-page__back" :to="{ name: 'home' }">{{ $t('back') }}</router-link>
      <div class="contract-page__bar-actions">
        <button class="contract-page__ghost" type="button" @click="printDoc">{{ $t('contractPrint') }}</button>
        <button class="contract-page__solid" type="button" @click="downloadDoc">{{ $t('contractDownload') }}</button>
        <LanguageSwitcher embedded />
      </div>
    </header>

    <article class="contract-sheet" aria-labelledby="contract-title">
      <div class="contract-sheet__mark" aria-hidden="true"></div>
      <p class="contract-sheet__kicker">{{ doc.kicker }}</p>
      <h1 id="contract-title">{{ doc.title }}</h1>
      <p class="contract-sheet__sub">{{ doc.subtitle }}</p>

      <dl class="contract-meta">
        <div>
          <dt>{{ doc.meta.number }}</dt>
          <dd class="is-line">№ ________</dd>
        </div>
        <div>
          <dt>{{ doc.meta.date }}</dt>
          <dd class="is-line">______________</dd>
        </div>
        <div>
          <dt>{{ doc.meta.executor }}</dt>
          <dd>{{ doc.meta.phone }}: +998 77 444 2525</dd>
        </div>
        <div>
          <dt>{{ doc.meta.client }}</dt>
          <dd class="is-line">{{ doc.meta.phone }}: ______________</dd>
        </div>
        <div>
          <dt>{{ doc.meta.plan }}</dt>
          <dd>☐ {{ doc.meta.standard }} &nbsp; ☐ {{ doc.meta.premium }}</dd>
        </div>
        <div>
          <dt>{{ doc.meta.farm }}</dt>
          <dd class="is-line">______________</dd>
        </div>
      </dl>

      <section>
        <h2>{{ doc.s1Title }}</h2>
        <p>{{ doc.s1Standard }}</p>
        <p>{{ doc.s1Premium }}</p>
        <p class="contract-emphasis">{{ doc.s1Use }}</p>
      </section>

      <section>
        <h2>{{ doc.s2Title }}</h2>
        <div class="contract-split">
          <div class="contract-split__col">
            <h3>{{ doc.s2ExecTitle }}</h3>
            <ul>
              <li v-for="item in doc.s2Exec" :key="item">{{ item }}</li>
            </ul>
          </div>
          <div class="contract-split__col contract-split__col--client">
            <h3>{{ doc.s2ClientTitle }}</h3>
            <ul>
              <li v-for="item in doc.s2Client" :key="item">{{ item }}</li>
            </ul>
          </div>
        </div>
        <p>{{ doc.s2Note }}</p>
      </section>

      <section class="contract-warn">
        <h2>{{ doc.s3Title }}</h2>
        <p>{{ doc.s3Lead }}</p>
        <ul>
          <li v-for="item in doc.s3Items" :key="item">{{ item }}</li>
        </ul>
        <p class="contract-emphasis">{{ doc.s3Foot }}</p>
      </section>

      <section>
        <h2>{{ doc.s4Title }}</h2>
        <div class="table-scroll">
          <table class="contract-table">
            <thead>
              <tr>
                <th v-for="head in doc.s4Head" :key="head">{{ head }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in doc.s4Rows" :key="row[0]">
                <td v-for="cell in row" :key="cell">{{ cell }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p>{{ doc.s4Pay }}</p>
      </section>

      <section>
        <h2>{{ doc.s5Title }}</h2>
        <ol class="contract-rules">
          <li v-for="item in doc.s5Items" :key="item">{{ item }}</li>
        </ol>
      </section>

      <section class="contract-sign">
        <p class="contract-emphasis">{{ doc.signAck }}</p>
        <div class="contract-sign__grid">
          <div>
            <span class="contract-sign__line" />
            <small>{{ doc.signExec }} · {{ doc.stamp }}</small>
          </div>
          <div>
            <span class="contract-sign__line" />
            <small>{{ doc.signClient }} · {{ doc.signDate }}</small>
          </div>
        </div>
      </section>
    </article>
  </div>
</template>

<script>
import { computed } from 'vue';
import LanguageSwitcher from '../components/LanguageSwitcher.vue';
import { getTarifContract } from '../config/tarifContract';
import { i18nState } from '../i18n';
import { downloadTarifContract } from '../utils/downloadTarifContract';

export default {
  name: 'TarifContract',
  components: { LanguageSwitcher },
  setup() {
    const doc = computed(() => getTarifContract(i18nState.locale));

    function printDoc() {
      window.print();
    }

    function downloadDoc() {
      downloadTarifContract(i18nState.locale);
    }

    return { doc, printDoc, downloadDoc };
  },
};
</script>
