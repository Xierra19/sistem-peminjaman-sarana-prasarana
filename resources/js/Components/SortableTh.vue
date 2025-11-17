<script setup>
import { computed } from 'vue'

const props = defineProps({
  column: {
    type: [String, Number],
    required: true,
  },
  label: {
    type: String,
    default: '',
  },
  direction: {
    type: String,
    default: 'none',
    validator: (value) => ['asc', 'desc', 'none'].includes(value),
  },
  ariaSort: {
    type: String,
    default: 'none',
  },
  align: {
    type: String,
    default: 'left',
    validator: (value) => ['left', 'center', 'right'].includes(value),
  },
})

const emit = defineEmits(['toggle'])

const thAlignClass = computed(() => {
  if (props.align === 'center') return 'text-center'
  if (props.align === 'right') return 'text-right'
  return 'text-left'
})

const buttonClass = computed(() => {
  if (props.align === 'center') {
    return 'flex w-full items-center justify-center gap-2 text-center focus:outline-none'
  }

  if (props.align === 'right') {
    return 'flex w-full items-center justify-end gap-2 text-right focus:outline-none'
  }

  return 'flex w-full items-center gap-2 text-left focus:outline-none'
})

const iconWrapperClass = computed(() =>
  props.direction === 'none'
    ? 'text-gray-400'
    : 'text-blue-600 dark:text-blue-400',
)

const handleClick = () => {
  emit('toggle', props.column)
}
</script>

<template>
  <th
    v-bind="$attrs"
    :class="[thAlignClass]"
    :aria-sort="ariaSort"
  >
    <button
      type="button"
      :class="buttonClass"
      @click="handleClick"
    >
      <slot>{{ label }}</slot>

      <span class="ml-1 inline-flex h-4 w-4 items-center justify-center" :class="iconWrapperClass" aria-hidden="true">
        <template v-if="direction === 'asc'">
          <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
            <path d="M10 4l4 6H6l4-6z" />
            <path d="M10 16l-4-6h8l-4 6z" opacity="0.25" />
          </svg>
        </template>
        <template v-else-if="direction === 'desc'">
          <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
            <path d="M10 16l-4-6h8l-4 6z" />
            <path d="M10 4l4 6H6l4-6z" opacity="0.25" />
          </svg>
        </template>
        <template v-else>
          <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 opacity-80">
            <path d="M10 4l4 6H6l4-6zM10 16l-4-6h8l-4 6z" />
          </svg>
        </template>
      </span>
    </button>
  </th>
</template>
