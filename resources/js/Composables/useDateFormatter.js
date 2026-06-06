/**
 * Utility untuk format tanggal sesuai standar DD-MM-YY
 */

/**
 * Format tanggal ke YYYY-MM-DD menggunakan zona waktu lokal.
 * @param {string|Date} value - Nilai tanggal yang akan diformat
 * @returns {string} Tanggal dalam format YYYY-MM-DD atau string kosong jika invalid
 */
export const formatDateToYMD = (value) => {
  if (!value) return ''
  const date = value instanceof Date ? value : new Date(value)
  if (Number.isNaN(date.getTime())) return ''

  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

/**
 * Format tanggal ke DD-MM-YY
 * @param {string|Date} value - Nilai tanggal yang akan diformat
 * @returns {string} Tanggal dalam format DD-MM-YY atau '-' jika invalid
 */
export const formatToDDMMYY = (value) => {
  if (!value) return '-'
  const date = new Date(value)
  if (isNaN(date.getTime())) return '-'
  const day = String(date.getDate()).padStart(2, '0')
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const year = String(date.getFullYear()).slice(-2)
  return `${day}-${month}-${year}`
}

/**
 * Format datetime ke DD-MM-YY HH:MM
 * @param {string|Date} value - Nilai datetime yang akan diformat
 * @returns {string} Datetime dalam format DD-MM-YY HH:MM atau '-' jika invalid
 */
export const formatDateTimeToDDMMYY = (value) => {
  if (!value) return '-'
  const date = new Date(value)
  if (isNaN(date.getTime())) return '-'
  const day = String(date.getDate()).padStart(2, '0')
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const year = String(date.getFullYear()).slice(-2)
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  return `${day}-${month}-${year} ${hours}:${minutes}`
}
