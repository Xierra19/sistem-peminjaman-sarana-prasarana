<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Dropdown from "@/Components/Dropdown.vue";
import { usePagination } from "@/Composables/usePagination";
import { Head } from "@inertiajs/vue3";
import { ref, computed } from "vue";

// Props dari controller
const props = defineProps({
  histories: Array,
});

// State
const searchQuery = ref("");
const startDate = ref("");
const endDate = ref("");

const filteredHistories = computed(() => {
  return props.histories.filter((h) => {
    // Search
    const q = searchQuery.value.toLowerCase();
    const matchesSearch =
      h.user?.name?.toLowerCase().includes(q) ||
      h.booking?.room?.name?.toLowerCase().includes(q) ||
      h.action?.toLowerCase().includes(q) ||
      h.description?.toLowerCase().includes(q);

    // Date filter
    const createdAt = new Date(h.created_at);
    const startOk = startDate.value ? createdAt >= new Date(startDate.value) : true;
    const endOk = endDate.value ? createdAt <= new Date(endDate.value) : true;

    return matchesSearch && startOk && endOk;
  });
});

const {
  paginatedItems: paginatedHistories,
  rowsPerPage,
  currentPage,
  pageMeta,
  pages,
  changePage,
} = usePagination(filteredHistories);

// Export actions (langsung ke route Laravel)
const perPageOptions = [5, 10, 25, 50];

function exportExcel() {
  window.open(route("history.export.excel"), "_blank");
}
</script>

<template>
  <Head title="History" />

  <AuthenticatedLayout>
    <template #default>
      <div class="p-6">
        <h1 class="text-2xl font-semibold mb-4">Log History</h1>

        <!-- Filter tools -->
        <div class="mb-4 flex flex-wrap items-center gap-4">
          <!-- Search -->
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search..."
            class="border rounded px-3 py-2 text-sm w-64"
          />

          <!-- Date filter -->
          <div class="flex items-center gap-2">
            <label class="text-sm">From</label>
            <input type="date" v-model="startDate" class="border rounded px-2 py-1 text-sm" />
            <label class="text-sm">To</label>
            <input type="date" v-model="endDate" class="border rounded px-2 py-1 text-sm" />
          </div>

          <!-- Export -->
          <div class="ml-auto">
            <Dropdown align="right" width="48">
              <template #trigger>
                <button
                  type="button"
                  class="inline-flex items-center justify-center gap-2 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-100"
                >
                  📄 Export
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
                <button
                  type="button"
                  class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-gray-700 hover:bg-blue-50"
                  @click="exportExcel"
                >
                  📊 Export Excel
                </button>
              </template>
            </Dropdown>
          </div>
        </div>

        <!-- Rows per page -->
        <div class="flex justify-end mb-2">
          <label class="text-sm font-medium mr-2">Rows per page</label>
          <select v-model.number="rowsPerPage" class="border rounded px-2 py-1 text-sm">
            <option v-for="option in perPageOptions" :key="`rows-${option}`" :value="option">
              {{ option }}
            </option>
          </select>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto bg-white rounded shadow">
          <table class="min-w-full text-sm text-left border-collapse">
            <thead class="bg-gray-100">
              <tr>
                <th class="px-4 py-2 border">Id</th>
                <th class="px-4 py-2 border">User</th>
                <th class="px-4 py-2 border">Room</th>
                <th class="px-4 py-2 border">Action</th>
                <th class="px-4 py-2 border">Time (Created At)</th>
                <th class="px-4 py-2 border">Description</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="history in paginatedHistories"
                :key="history.id"
                class="hover:bg-gray-50"
              >
                <td class="px-4 py-2 border">{{ history.id }}</td>
                <td class="px-4 py-2 border">
                  {{ history.user?.name ?? "-" }}
                </td>
                <td class="px-4 py-2 border">
                  {{ history.booking?.room?.name ?? "-" }}
                </td>
                <td class="px-4 py-2 border">{{ history.action }}</td>
                <td class="px-4 py-2 border">
                  {{ new Date(history.created_at).toLocaleString() }}
                </td>
                <td class="px-4 py-2 border">
                  {{ history.description ?? "-" }}
                </td>
              </tr>
              <tr v-if="!paginatedHistories.length">
                <td colspan="6" class="text-center py-4 text-gray-500">
                  No history found
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="mt-4 flex flex-col gap-3 text-sm text-gray-600 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <span v-if="pageMeta.of">Menampilkan {{ pageMeta.from }}-{{ pageMeta.to }} dari {{ pageMeta.of }} data</span>
            <span v-else>Menampilkan 0 data</span>
          </div>
          <div class="flex items-center space-x-1">
            <button
              class="px-2 py-1 border rounded disabled:opacity-50"
              @click="changePage(1)"
            :disabled="currentPage === 1 || !pageMeta.of"
          >
            «
          </button>
          <button
            class="px-2 py-1 border rounded disabled:opacity-50"
              @click="changePage(currentPage - 1)"
            :disabled="currentPage === 1 || !pageMeta.of"
          >
            ‹
          </button>
          <template v-if="pageMeta.of">
            <button
              v-for="page in pages"
              :key="`history-page-${page}`"
              class="px-3 py-1 border rounded"
              :class="currentPage === page ? 'border-blue-500 bg-blue-500 text-white' : 'border-gray-300 text-gray-600 hover:border-blue-400 hover:text-blue-600'"
              @click="changePage(page)"
            >
              {{ page }}
            </button>
          </template>
          <button
            class="px-2 py-1 border rounded disabled:opacity-50"
              @click="changePage(currentPage + 1)"
            :disabled="currentPage === pages.length || !pageMeta.of"
          >
            ›
          </button>
            <button
              class="px-2 py-1 border rounded disabled:opacity-50"
                @click="changePage(pages.length)"
            :disabled="currentPage === pages.length || !pageMeta.of"
            >
              »
            </button>
          </div>
        </div>
      </div>
    </template>
  </AuthenticatedLayout>
</template>