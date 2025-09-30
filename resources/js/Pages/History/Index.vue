<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, router } from "@inertiajs/vue3";
import { ref, computed } from "vue";

// Props dari controller
const props = defineProps({
  histories: Array,
});

// State
const currentPage = ref(1);
const rowsPerPage = ref(10);
const searchQuery = ref("");
const startDate = ref("");
const endDate = ref("");

// Pagination
const totalPages = computed(() =>
  Math.ceil(filteredHistories.value.length / rowsPerPage.value)
);

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

const paginatedHistories = computed(() => {
  const start = (currentPage.value - 1) * rowsPerPage.value;
  const end = start + rowsPerPage.value;
  return filteredHistories.value.slice(start, end);
});

function changePage(page) {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page;
  }
}

// Export actions (langsung ke route Laravel)
function exportExcel() {
  window.location.href = "/history/export/excel";
}

function exportPdf() {
  window.location.href = "/history/export/pdf";
}
</script>

<template>
  <Head title="History" />

  <AuthenticatedLayout>
    <template #default>
      <div class="p-6">
        <h1 class="text-2xl font-semibold mb-4">Log History</h1>

        <!-- Filter tools -->
        <div class="flex flex-wrap items-center gap-4 mb-4">
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
          <div class="ml-auto flex gap-2">
            <button
              @click="exportExcel"
              class="bg-green-500 hover:bg-green-600 text-white px-3 py-2 rounded text-sm"
            >
              Export Excel
            </button>
            <button
              @click="exportPdf"
              class="bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded text-sm"
            >
              Export PDF
            </button>
          </div>
        </div>

        <!-- Rows per page -->
        <div class="flex justify-end mb-2">
          <label class="text-sm font-medium mr-2">Rows per page</label>
          <select
            v-model="rowsPerPage"
            class="border rounded px-2 py-1 text-sm"
          >
            <option :value="5">5</option>
            <option :value="10">10</option>
            <option :value="25">25</option>
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
        <div class="flex justify-end items-center mt-4 space-x-1">
          <button
            class="px-2 py-1 border rounded disabled:opacity-50"
            @click="changePage(1)"
            :disabled="currentPage === 1"
          >
            «
          </button>
          <button
            class="px-2 py-1 border rounded disabled:opacity-50"
            @click="changePage(currentPage - 1)"
            :disabled="currentPage === 1"
          >
            ‹
          </button>

          <span class="px-3 py-1 border rounded bg-blue-500 text-white">
            {{ currentPage }}
          </span>

          <button
            class="px-2 py-1 border rounded disabled:opacity-50"
            @click="changePage(currentPage + 1)"
            :disabled="currentPage === totalPages"
          >
            ›
          </button>
          <button
            class="px-2 py-1 border rounded disabled:opacity-50"
            @click="changePage(totalPages)"
            :disabled="currentPage === totalPages"
          >
            »
          </button>
        </div>
      </div>
    </template>
  </AuthenticatedLayout>
</template>
