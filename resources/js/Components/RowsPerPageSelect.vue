<script setup>
const props = defineProps({
  modelValue: {
    type: Number,
    required: true,
  },
  options: {
    type: Array,
    default: () => [5, 10, 25, 50],
  },
  inputId: {
    type: String,
    required: true,
  },
  label: {
    type: String,
    default: 'Rows per page',
  },
  wide: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue'])

const updateValue = (event) => {
  emit('update:modelValue', Number(event.target.value))
}
</script>

<template>
  <div class="flex flex-col gap-2 text-sm text-slate-600 dark:text-slate-300 sm:flex-row sm:items-center sm:justify-end">
    <label class="font-medium text-slate-700 dark:text-slate-200" :for="inputId">
      {{ label }}
    </label>
    <select
      :id="inputId"
      :value="modelValue"
      class="w-full rounded border border-slate-300 bg-white px-3 py-1.5 pr-8 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
      :class="wide ? 'sm:w-28' : 'sm:w-20'"
      @change="updateValue"
    >
      <option v-for="option in options" :key="option" :value="option">
        {{ option }}
      </option>
    </select>
  </div>
</template>
