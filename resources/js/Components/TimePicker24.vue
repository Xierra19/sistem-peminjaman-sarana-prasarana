<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  minuteStep: {
    type: Number,
    default: 1,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  required: {
    type: Boolean,
    default: false,
  },
  id: {
    type: String,
    default: undefined,
  },
  name: {
    type: String,
    default: undefined,
  },
  hourPlaceholder: {
    type: String,
    default: 'Jam',
  },
  minutePlaceholder: {
    type: String,
    default: 'Menit',
  },
})

const emit = defineEmits(['update:modelValue'])

const hourOptions = Array.from({ length: 24 }, (_, index) => index.toString().padStart(2, '0'))

const normalizedStep = computed(() => {
  const step = Number.isFinite(props.minuteStep) ? Number(props.minuteStep) : 1
  if (step < 1) {
    return 1
  }
  if (step > 60) {
    return 60
  }
  return Math.round(step)
})

const minuteOptions = computed(() => {
  const options = []
  const step = normalizedStep.value
  for (let minute = 0; minute < 60; minute += step) {
    options.push(minute.toString().padStart(2, '0'))
  }
  if (!options.includes('00')) {
    options.unshift('00')
  }
  return Array.from(new Set(options))
})

const hour = ref('')
const minute = ref('')

watch(
  () => props.modelValue,
  (value) => {
    if (!value) {
      hour.value = ''
      minute.value = ''
      return
    }
    const [rawHour = '', rawMinute = ''] = value.split(':')
    hour.value = hourOptions.includes(rawHour) ? rawHour : ''
    const candidateMinute = rawMinute.padStart(2, '0')
    if (minuteOptions.value.includes(candidateMinute)) {
      minute.value = candidateMinute
    } else if (minuteOptions.value.includes('00')) {
      minute.value = '00'
    } else {
      minute.value = ''
    }
  },
  { immediate: true },
)

watch(
  minuteOptions,
  (options) => {
    if (minute.value && !options.includes(minute.value)) {
      minute.value = options[0] ?? ''
    }
  },
  { immediate: true },
)

const hiddenValue = computed(() => (hour.value && minute.value ? `${hour.value}:${minute.value}` : ''))

watch(
  [hour, minute],
  ([selectedHour, selectedMinute]) => {
    if (!selectedHour) {
      if (minute.value) {
        minute.value = ''
      }
      if (props.modelValue !== '') {
        emit('update:modelValue', '')
      }
      return
    }

    if (!selectedMinute) {
      if (props.modelValue !== '') {
        emit('update:modelValue', '')
      }
      return
    }

    const nextValue = `${selectedHour}:${selectedMinute}`
    if (nextValue !== props.modelValue) {
      emit('update:modelValue', nextValue)
    }
  },
  { immediate: false },
)
</script>

<template>
  <div class="flex w-full items-center gap-2">
    <select
      :id="id ? `${id}-hour` : undefined"
      v-model="hour"
      :disabled="disabled"
      :required="required"
      class="w-full rounded border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-gray-100"
    >
      <option value="">{{ hourPlaceholder }}</option>
      <option v-for="option in hourOptions" :key="option" :value="option">
        {{ option }}
      </option>
    </select>
    <span class="text-sm text-gray-500">:</span>
    <select
      :id="id ? `${id}-minute` : undefined"
      v-model="minute"
      :disabled="disabled"
      :required="required && !!hour"
      class="w-full rounded border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-gray-100"
    >
      <option value="">{{ minutePlaceholder }}</option>
      <option v-for="option in minuteOptions" :key="option" :value="option">
        {{ option }}
      </option>
    </select>
    <input v-if="name" type="hidden" :name="name" :value="hiddenValue" />
  </div>
</template>
