<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Dropdown from '@/Components/Dropdown.vue'
import SortableTh from '@/Components/SortableTh.vue'
import { usePagination } from '@/Composables/usePagination'
import { useTableSort } from '@/Composables/useTableSort'
import { Head } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps({
  histories: {
    type: Array,
    default: () => [],
  },
})

const searchQuery = ref('')
const startDate = ref('')
const endDate = ref('')

const filteredHistories = computed(() => {
  const list = props.histories ?? []
  const query = searchQuery.value.trim().toLowerCase()
  const start = startDate.value ? new Date(startDate.value) : null
  const end = endDate.value ? new Date(endDate.value) : null

  return list.filter((history) => {
    const matchesSearch =
      !query ||
      history.user?.name?.toLowerCase().includes(query) ||
      history.user?.email?.toLowerCase().includes(query) ||
      history.booking?.room?.name?.toLowerCase().includes(query) ||
      history.booking?.room?.building?.name?.toLowerCase().includes(query) ||
      history.action?.toLowerCase().includes(query) ||
      history.description?.toLowerCase().includes(query)

    if (!matchesSearch) {
      return false
    }

    const createdAt = history.created_at ? new Date(history.created_at) : null
    const startOk = !start || (createdAt && createdAt >= start)
    const endOk = !end || (createdAt && createdAt <= end)

    return startOk && endOk
  })
})

const {
  sortedItems: sortedHistories,
  toggleSort: toggleHistorySort,
  sortDirection: historySortDirection,
  ariaSortValue: historyAriaSortValue,
} = useTableSort(filteredHistories, {
  accessors: {
    id: (history) => history.id ?? 0,
    user: (history) => history.user?.name ?? '',
    room: (history) => history.booking?.room?.name ?? '',
    action: (history) => history.action ?? '',
    created_at: (history) => (history.created_at ? new Date(history.created_at) : null),
    description: (history) => history.description ?? '',
  },
})

const {
  paginatedItems: paginatedHistories,
  rowsPerPage,
  currentPage,
  pageMeta,
  pages,
  changePage,
} = usePagination(sortedHistories)

const perPageOptions = [5, 10, 25, 50]

const formatDateTime = (value) => {
  if (!value) return '-'
  return new Date(value).toLocaleString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const exportExcel = () => {
  window.open(route('history.export.excel'), '_blank')
}
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Riwayat Aktivitas" />

    <div class="space-y-6">
      <div>
        <h1 class="text-2xl font-semibold text-slate-900">Riwayat Aktivitas</h1>
        <p class="text-sm text-slate-500">Pantau semua perubahan booking ruangan yang terekam di sistem.</p>
      </div>

      <div class="card-surface overflow-hidden">
        <div class="flex flex-col gap-4 border-b border-slate-100 px-5 py-4">
          <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div class="flex flex-1 flex-col gap-3 md:flex-row md:items-center md:gap-4">
              <div class="relative w-full md:max-w-xs">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M21 21l-4.35-4.35M9.5 17a7.5 7.5 0 1 1 7.5-7.5A7.5 7.5 0 0 1 9.5 17Z"
                    />
                  </svg>
                </span>
                <input
                  id="history-search"
                  v-model="searchQuery"
                  type="text"
                  placeholder="Cari pengguna, ruangan, atau aksi…"
                  class="w-full rounded-xl border border-slate-200 bg-white py-2 pl-10 pr-3 text-sm text-slate-700 placeholder-slate-400 transition focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
              </div>
              <div class="flex flex-wrap items-center gap-3 text-sm text-slate-600">
                <div class="flex items-center gap-2">
                  <label class="font-medium text-slate-600" for="history-start">Dari</label>
                  <input
                    id="history-start"
                    v-model="startDate"
                    type="date"
                    class="rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 transition focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                  />
                </div>
                <div class="flex items-center gap-2">
                  <label class="font-medium text-slate-600" for="history-end">Sampai</label>
                  <input
                    id="history-end"
                    v-model="endDate"
                    type="date"
                    class="rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 transition focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                  />
                </div>
              </div>
            </div>
            <Dropdown align="right" width="48">
              <template #trigger>
                <button
                  type="button"
                  class="inline-flex items-center justify-center gap-2 rounded-2xl border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-medium text-blue-700 transition hover:bg-blue-100"
                >
                  <span>Export</span>
                  <svg class="h-4 w-4" fill="none" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                    <path
                      d="M5.25 7.5 10 12.5l4.75-5"
                      stroke="currentColor"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="1.5"
                    />
                  </svg>
                </button>
              </template>
              <template #content>
                <div class="flex flex-col gap-1 p-2 text-sm text-slate-700">
                  <button
                    type="button"
                    class="flex items-center gap-2 rounded-md px-3 py-2 hover:bg-blue-50"
                    @click="exportExcel"
                  >
                    <span>Export Excel</span>
                  </button>
                </div>
              </template>
            </Dropdown>
          </div>
          <div class="flex items-center justify-end gap-3 text-sm text-slate-600">
            <label class="font-medium text-slate-700" for="history-rows">Baris per halaman</label>
            <div class="relative">
              <select
                id="history-rows"
                v-model.number="rowsPerPage"
                class="w-24 rounded-xl border border-slate-200 bg-white px-3 py-1.5 pr-8 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              >
                <option v-for="option in perPageOptions" :key="`rows-${option}`" :value="option">
                  {{ option }}
                </option>
              </select>
            </div>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="mobile-friendly-table min-w-full divide-y divide-slate-200 text-sm text-slate-700">
            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
              <tr>
                <SortableTh
                  class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                  column="id"
                  label="ID"
                  :direction="historySortDirection('id')"
                  :aria-sort="historyAriaSortValue('id')"
                  @toggle="toggleHistorySort"
                />
                <SortableTh
                  class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                  column="user"
                  label="Pengguna"
                  :direction="historySortDirection('user')"
                  :aria-sort="historyAriaSortValue('user')"
                  @toggle="toggleHistorySort"
                />
                <SortableTh
                  class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                  column="room"
                  label="Ruangan"
                  :direction="historySortDirection('room')"
                  :aria-sort="historyAriaSortValue('room')"
                  @toggle="toggleHistorySort"
                />
                <SortableTh
                  class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                  column="action"
                  label="Aksi"
                  :direction="historySortDirection('action')"
                  :aria-sort="historyAriaSortValue('action')"
                  @toggle="toggleHistorySort"
                />
                <SortableTh
                  class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                  column="created_at"
                  label="Waktu"
                  :direction="historySortDirection('created_at')"
                  :aria-sort="historyAriaSortValue('created_at')"
                  @toggle="toggleHistorySort"
                />
                <SortableTh
                  class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                  column="description"
                  label="Deskripsi"
                  :direction="historySortDirection('description')"
                  :aria-sort="historyAriaSortValue('description')"
                  @toggle="toggleHistorySort"
                />
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700">
              <tr v-for="history in paginatedHistories" :key="history.id" class="hover:bg-slate-50">
                <td class="px-5 py-4 text-sm text-slate-500" data-title="ID">#{{ history.id }}</td>
                <td class="px-5 py-4" data-title="Pengguna">
                  <div class="font-medium text-slate-900">{{ history.user?.name ?? '-' }}</div>
                  <div class="text-xs text-slate-500">{{ history.user?.email ?? '-' }}</div>
                </td>
                <td class="px-5 py-4" data-title="Ruangan">
                  <div class="font-medium text-slate-900">{{ history.booking?.room?.name ?? '-' }}</div>
                  <div class="text-xs text-slate-500">
                    {{ history.booking?.room?.building?.name ?? '-' }} - {{ history.booking?.room?.building?.campus?.name ?? '-' }}
                  </div>
                </td>
                <td class="px-5 py-4" data-title="Aksi">
                  <span
                    class="inline-flex rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-600"
                  >
                    {{ history.action ?? '-' }}
                  </span>
                </td>
                <td class="px-5 py-4 text-sm text-slate-600" data-title="Waktu">
                  <div class="font-medium text-slate-800">{{ formatDateTime(history.created_at) }}</div>
                </td>
                <td class="px-5 py-4 text-sm text-slate-600" data-title="Deskripsi">
                  <span>{{ history.description ?? '-' }}</span>
                </td>
              </tr>
              <tr v-if="!paginatedHistories.length">
                <td colspan="6" class="px-5 py-10 text-center text-sm text-slate-400" data-title="Info">
                  Belum ada riwayat yang sesuai dengan filter.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div
          class="flex flex-col gap-3 border-t border-slate-100 px-5 py-4 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between"
        >
          <div>
            <span v-if="pageMeta.of">Menampilkan {{ pageMeta.from }}-{{ pageMeta.to }} dari {{ pageMeta.of }} data</span>
            <span v-else>Menampilkan 0 data</span>
          </div>
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="rounded-xl border border-slate-300 px-3 py-1 text-sm text-slate-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(1)"
              :disabled="currentPage === 1 || !pageMeta.of"
            >
              «
            </button>
            <button
              type="button"
              class="rounded-xl border border-slate-300 px-3 py-1 text-sm text-slate-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(currentPage - 1)"
              :disabled="currentPage === 1 || !pageMeta.of"
            >
              ‹
            </button>
            <template v-if="pageMeta.of">
              <button
                v-for="page in pages"
                :key="`history-page-${page}`"
                type="button"
                class="rounded-xl border px-3 py-1 text-sm transition"
                :class="
                  currentPage === page
                    ? 'border-blue-500 bg-blue-500 text-white'
                    : 'border-slate-300 text-slate-600 hover:border-blue-400 hover:text-blue-600'
                "
                @click="changePage(page)"
              >
                {{ page }}
              </button>
            </template>
            <button
              type="button"
              class="rounded-xl border border-slate-300 px-3 py-1 text-sm text-slate-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(currentPage + 1)"
              :disabled="currentPage === pages.length || !pageMeta.of"
            >
              ›
            </button>
            <button
              type="button"
              class="rounded-xl border border-slate-300 px-3 py-1 text-sm text-slate-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(pages.length)"
              :disabled="currentPage === pages.length || !pageMeta.of"
            >
              »
            </button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
