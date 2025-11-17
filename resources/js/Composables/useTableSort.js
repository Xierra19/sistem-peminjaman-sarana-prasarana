import { computed, ref, unref } from 'vue'

const getValueByPath = (obj, path) => {
  if (!obj || typeof path !== 'string') {
    return undefined
  }

  if (!path.includes('.')) {
    return obj[path]
  }

  return path.split('.').reduce((acc, segment) => {
    if (acc == null) {
      return undefined
    }

    return acc[segment]
  }, obj)
}

const normalizeValue = (value) => {
  if (value == null || value === '') {
    return { type: 'null', value: '' }
  }

  if (value instanceof Date) {
    return { type: 'number', value: value.getTime() }
  }

  const valueType = typeof value

  if (valueType === 'number' || valueType === 'bigint') {
    return { type: 'number', value: Number(value) }
  }

  if (valueType === 'boolean') {
    return { type: 'number', value: value ? 1 : 0 }
  }

  if (valueType === 'string') {
    const trimmed = value.trim()
    if (trimmed !== '') {
      const numeric = Number(trimmed)
      if (!Number.isNaN(numeric)) {
        return { type: 'number', value: numeric }
      }
    }

    return { type: 'string', value: value.toLowerCase() }
  }

  if (Array.isArray(value)) {
    return normalizeValue(value.length)
  }

  return { type: 'string', value: String(value).toLowerCase() }
}

const compareValues = (a, b) => {
  const normalizedA = normalizeValue(a)
  const normalizedB = normalizeValue(b)

  if (normalizedA.type === 'null' && normalizedB.type === 'null') {
    return 0
  }

  if (normalizedA.type === 'null') {
    return 1
  }

  if (normalizedB.type === 'null') {
    return -1
  }

  if (normalizedA.type === normalizedB.type) {
    if (normalizedA.value < normalizedB.value) return -1
    if (normalizedA.value > normalizedB.value) return 1
    return 0
  }

  if (normalizedA.type === 'number') {
    return -1
  }

  if (normalizedB.type === 'number') {
    return 1
  }

  return String(normalizedA.value).localeCompare(String(normalizedB.value))
}

export function useTableSort(itemsRef, options = {}) {
  const sortState = ref({
    column: options.defaultColumn ?? null,
    direction: options.defaultDirection === 'desc' ? 'desc' : 'asc',
  })

  const accessors = options.accessors ?? {}

  const resolveAccessor = (column) => {
    if (!column) {
      return null
    }

    const accessor = accessors[column]

    if (typeof accessor === 'function') {
      return accessor
    }

    if (typeof accessor === 'string' && accessor.length > 0) {
      return (row) => getValueByPath(row, accessor)
    }

    if (typeof column === 'string' && column.length > 0) {
      return (row) => getValueByPath(row, column)
    }

    return null
  }

  const sortedItems = computed(() => {
    const source = unref(itemsRef)
    const list = Array.isArray(source) ? [...source] : []
    const column = sortState.value.column
    const accessor = resolveAccessor(column)

    if (!accessor) {
      return list
    }

    const direction = sortState.value.direction === 'desc' ? -1 : 1

    return list.sort((a, b) => {
      const order = compareValues(accessor(a), accessor(b))
      return order * direction
    })
  })

  const setSort = (column, direction = 'asc') => {
    if (!column) {
      return
    }

    sortState.value.column = column
    sortState.value.direction = direction === 'desc' ? 'desc' : 'asc'
  }

  const toggleSort = (column) => {
    if (!column) {
      return
    }

    if (sortState.value.column === column) {
      sortState.value.direction = sortState.value.direction === 'asc' ? 'desc' : 'asc'
      return
    }

    setSort(column, 'asc')
  }

  const sortDirection = (column) => {
    if (!column || sortState.value.column !== column) {
      return 'none'
    }

    return sortState.value.direction === 'asc' ? 'asc' : 'desc'
  }

  const ariaSortValue = (column) => {
    if (!column || sortState.value.column !== column) {
      return 'none'
    }

    return sortState.value.direction === 'asc' ? 'ascending' : 'descending'
  }

  return {
    sortState,
    sortedItems,
    toggleSort,
    setSort,
    sortDirection,
    ariaSortValue,
  }
}
