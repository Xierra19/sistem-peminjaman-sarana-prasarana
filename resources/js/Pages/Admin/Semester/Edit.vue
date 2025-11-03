// file: resources/js/Pages/Admin/Semester/Edit.vue
<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
  semester: {
    type: Object,
    required: true,
  },
});

const form = useForm({
  id: props.semester.id ?? null,
  start_date: props.semester.start_date ?? '',
  end_date: props.semester.end_date ?? '',
  uts_start_date: props.semester.uts_start_date ?? '',
  uts_end_date: props.semester.uts_end_date ?? '',
  uas_start_date: props.semester.uas_start_date ?? '',
  uas_end_date: props.semester.uas_end_date ?? '',
  teaching_weeks_before_uts: props.semester.teaching_weeks_before_uts ?? 7,
  teaching_weeks_after_uts: props.semester.teaching_weeks_after_uts ?? 7,
  is_active: Boolean(props.semester.is_active),
});

const ranges = computed(() => {
  const formatRange = (start, end) => {
    if (!start || !end) {
      return null;
    }

    return `${start} s.d. ${end}`;
  };

  return {
    semester: formatRange(form.start_date, form.end_date),
    uts: formatRange(form.uts_start_date, form.uts_end_date),
    uas: formatRange(form.uas_start_date, form.uas_end_date),
  };
});

const submit = () => {
  form.put(route('admin.semester.update'), {
    preserveScroll: true,
  });
};
</script>

<template>
  <div class="max-w-5xl mx-auto py-10">
    <div class="mb-8">
      <h1 class="text-2xl font-semibold text-gray-900">Semester Settings</h1>
      <p class="mt-2 text-sm text-gray-600">
        Configure the active semester schedule and exam windows.
      </p>
    </div>

    <div class="grid gap-6 lg:grid-cols-[2fr,1fr]">
      <form
        class="bg-white shadow rounded-lg p-6"
        @submit.prevent="submit"
      >
        <input type="hidden" v-model="form.id" />

        <div class="grid gap-4 md:grid-cols-2">
          <div>
            <label class="block text-sm font-medium text-gray-700" for="start_date">
              Start Date
            </label>
            <input
              id="start_date"
              v-model="form.start_date"
              type="date"
              class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            />
            <p v-if="form.errors.start_date" class="mt-1 text-sm text-red-600">
              {{ form.errors.start_date }}
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700" for="end_date">
              End Date
            </label>
            <input
              id="end_date"
              v-model="form.end_date"
              type="date"
              class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            />
            <p v-if="form.errors.end_date" class="mt-1 text-sm text-red-600">
              {{ form.errors.end_date }}
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700" for="uts_start_date">
              UTS Start Date
            </label>
            <input
              id="uts_start_date"
              v-model="form.uts_start_date"
              type="date"
              class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            />
            <p v-if="form.errors.uts_start_date" class="mt-1 text-sm text-red-600">
              {{ form.errors.uts_start_date }}
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700" for="uts_end_date">
              UTS End Date
            </label>
            <input
              id="uts_end_date"
              v-model="form.uts_end_date"
              type="date"
              class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            />
            <p v-if="form.errors.uts_end_date" class="mt-1 text-sm text-red-600">
              {{ form.errors.uts_end_date }}
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700" for="uas_start_date">
              UAS Start Date
            </label>
            <input
              id="uas_start_date"
              v-model="form.uas_start_date"
              type="date"
              class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            />
            <p v-if="form.errors.uas_start_date" class="mt-1 text-sm text-red-600">
              {{ form.errors.uas_start_date }}
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700" for="uas_end_date">
              UAS End Date
            </label>
            <input
              id="uas_end_date"
              v-model="form.uas_end_date"
              type="date"
              class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            />
            <p v-if="form.errors.uas_end_date" class="mt-1 text-sm text-red-600">
              {{ form.errors.uas_end_date }}
            </p>
          </div>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-2">
          <div>
            <label
              class="block text-sm font-medium text-gray-700"
              for="teaching_weeks_before_uts"
            >
              Teaching Weeks Before UTS
            </label>
            <input
              id="teaching_weeks_before_uts"
              v-model.number="form.teaching_weeks_before_uts"
              type="number"
              min="1"
              max="14"
              class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            />
            <p v-if="form.errors.teaching_weeks_before_uts" class="mt-1 text-sm text-red-600">
              {{ form.errors.teaching_weeks_before_uts }}
            </p>
          </div>

          <div>
            <label
              class="block text-sm font-medium text-gray-700"
              for="teaching_weeks_after_uts"
            >
              Teaching Weeks After UTS
            </label>
            <input
              id="teaching_weeks_after_uts"
              v-model.number="form.teaching_weeks_after_uts"
              type="number"
              min="1"
              max="14"
              class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            />
            <p v-if="form.errors.teaching_weeks_after_uts" class="mt-1 text-sm text-red-600">
              {{ form.errors.teaching_weeks_after_uts }}
            </p>
          </div>
        </div>

        <div class="mt-6 flex items-center">
          <input
            id="is_active"
            v-model="form.is_active"
            type="checkbox"
            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
          />
          <label class="ml-2 text-sm text-gray-700" for="is_active">
            Is Active
          </label>
          <p v-if="form.errors.is_active" class="ml-4 text-sm text-red-600">
            {{ form.errors.is_active }}
          </p>
        </div>

        <div class="mt-8 flex justify-end">
          <button
            type="submit"
            :disabled="form.processing"
            class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-60 disabled:hover:bg-indigo-600"
          >
            <span v-if="form.processing">Saving...</span>
            <span v-else>Save Changes</span>
          </button>
        </div>
      </form>

      <div class="bg-white shadow rounded-lg p-6 space-y-4">
        <h2 class="text-lg font-semibold text-gray-900">Current Windows</h2>
        <div class="text-sm text-gray-700 space-y-2">
          <div>
            <span class="font-medium text-gray-900">Semester:</span>
            <span class="ml-1">
              {{ ranges.semester ?? 'Not set' }}
            </span>
          </div>
          <div>
            <span class="font-medium text-gray-900">UTS:</span>
            <span class="ml-1">
              {{ ranges.uts ?? 'Not set' }}
            </span>
          </div>
          <div>
            <span class="font-medium text-gray-900">UAS:</span>
            <span class="ml-1">
              {{ ranges.uas ?? 'Not set' }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

