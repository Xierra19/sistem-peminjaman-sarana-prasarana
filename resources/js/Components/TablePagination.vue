<script setup>
import { computed } from 'vue'

const props = defineProps({
  pageMeta: {
    type: Object,
    default: () => ({ from: 0, to: 0, of: 0 }),
  },
  pages: {
    type: Array,
    default: () => [],
  },
  currentPage: {
    type: Number,
    default: 1,
  },
  itemLabel: {
    type: String,
    default: 'data',
  },
})

const emit = defineEmits(['change'])

const hasItems = computed(() => Number(props.pageMeta?.of ?? 0) > 0)
const lastPage = computed(() => Math.max(1, props.pages.length))
const canGoPrevious = computed(() => hasItems.value && props.currentPage > 1)
const canGoNext = computed(() => hasItems.value && props.currentPage < lastPage.value)
const visiblePages = computed(() => {
  if (lastPage.value <= 7) {
    return props.pages
  }

  if (props.currentPage <= 4) {
    return [1, 2, 3, 4, 5, 'end-ellipsis', lastPage.value]
  }

  if (props.currentPage >= lastPage.value - 3) {
    return [
      1,
      'start-ellipsis',
      lastPage.value - 4,
      lastPage.value - 3,
      lastPage.value - 2,
      lastPage.value - 1,
      lastPage.value,
    ]
  }

  return [
    1,
    'start-ellipsis',
    props.currentPage - 1,
    props.currentPage,
    props.currentPage + 1,
    'end-ellipsis',
    lastPage.value,
  ]
})

const changePage = (page) => {
  if (page >= 1 && page <= lastPage.value) {
    emit('change', page)
  }
}
</script>

<template>
  <div class="flex flex-col gap-3 border-t border-slate-100 px-5 py-4 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700 dark:text-slate-300">
    <div>
      <span v-if="hasItems">
        Menampilkan {{ pageMeta.from }}-{{ pageMeta.to }} dari {{ pageMeta.of }} {{ itemLabel }}
      </span>
      <span v-else>Menampilkan 0 {{ itemLabel }}</span>
    </div>

    <div class="w-full sm:w-auto">
      <div class="mobile-pagination-compact md:hidden">
        <button
          type="button"
          class="rounded border border-slate-300 px-3 py-2 text-sm text-slate-600 transition hover:border-slate-400 hover:text-slate-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-200"
          :disabled="!canGoPrevious"
          @click="changePage(currentPage - 1)"
        >
          Sebelumnya
        </button>
        <button
          type="button"
          class="rounded border border-slate-300 px-3 py-2 text-sm text-slate-600 transition hover:border-slate-400 hover:text-slate-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-200"
          :disabled="!canGoNext"
          @click="changePage(currentPage + 1)"
        >
          Berikutnya
        </button>
      </div>

      <div class="hidden items-center gap-2 md:flex">
        <button
          type="button"
          class="rounded border border-slate-300 px-3 py-1 text-sm text-slate-600 transition hover:border-slate-400 hover:text-slate-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-200"
          :disabled="!canGoPrevious"
          @click="changePage(1)"
        >
          «
        </button>
        <button
          type="button"
          class="rounded border border-slate-300 px-3 py-1 text-sm text-slate-600 transition hover:border-slate-400 hover:text-slate-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-200"
          :disabled="!canGoPrevious"
          @click="changePage(currentPage - 1)"
        >
          ‹
        </button>

        <template v-if="hasItems">
          <template v-for="entry in visiblePages" :key="entry">
            <span
              v-if="typeof entry !== 'number'"
              class="px-1 text-slate-400 dark:text-slate-500"
              aria-hidden="true"
            >
              …
            </span>
            <button
              v-else
              type="button"
              class="rounded border px-3 py-1 text-sm transition"
              :class="
                currentPage === entry
                  ? 'border-blue-500 bg-blue-500 text-white'
                  : 'border-slate-300 text-slate-600 hover:border-slate-400 hover:text-slate-600 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-200'
              "
              @click="changePage(entry)"
            >
              {{ entry }}
            </button>
          </template>
        </template>

        <button
          type="button"
          class="rounded border border-slate-300 px-3 py-1 text-sm text-slate-600 transition hover:border-slate-400 hover:text-slate-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-200"
          :disabled="!canGoNext"
          @click="changePage(currentPage + 1)"
        >
          ›
        </button>
        <button
          type="button"
          class="rounded border border-slate-300 px-3 py-1 text-sm text-slate-600 transition hover:border-slate-400 hover:text-slate-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-200"
          :disabled="!canGoNext"
          @click="changePage(lastPage)"
        >
          »
        </button>
      </div>
    </div>
  </div>
</template>
