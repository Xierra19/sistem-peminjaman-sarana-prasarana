import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import flatpickr from 'flatpickr'
import 'flatpickr/dist/flatpickr.css'

const pickerOptions = {
  dateFormat: 'Y-m-d',
  altInput: true,
  altFormat: 'd-m-y',
}

export function useDateRangePickers(filterForm) {
  const startInput = ref(null)
  const endInput = ref(null)
  const startPicker = ref(null)
  const endPicker = ref(null)

  onMounted(() => {
    if (startInput.value) {
      startPicker.value = flatpickr(startInput.value, {
        ...pickerOptions,
        defaultDate: filterForm.start_date || null,
        onChange: (_selectedDates, dateString) => {
          filterForm.start_date = dateString
        },
      })
    }

    if (endInput.value) {
      endPicker.value = flatpickr(endInput.value, {
        ...pickerOptions,
        defaultDate: filterForm.end_date || null,
        onChange: (_selectedDates, dateString) => {
          filterForm.end_date = dateString
        },
      })
    }
  })

  watch(
    () => filterForm.start_date,
    (value) => {
      startPicker.value?.setDate(value || null, false)
    },
  )

  watch(
    () => filterForm.end_date,
    (value) => {
      endPicker.value?.setDate(value || null, false)
    },
  )

  onBeforeUnmount(() => {
    startPicker.value?.destroy()
    endPicker.value?.destroy()
  })

  return {
    startInput,
    endInput,
  }
}
