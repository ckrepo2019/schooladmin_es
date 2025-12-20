const DEFAULT_API_URL = 'http://localhost:5000'

export const API_BASE_URL = (import.meta.env.VITE_API_URL || DEFAULT_API_URL)
  .trim()
  .replace(/\/+$/, '')

const isAbsoluteUrl = (value) =>
  typeof value === 'string' &&
  (value.startsWith('//') || /^[a-zA-Z][a-zA-Z\d+.-]*:/.test(value))

export function apiUrl(path = '') {
  if (!path) return API_BASE_URL
  if (typeof path !== 'string') return API_BASE_URL
  if (isAbsoluteUrl(path)) return path
  return `${API_BASE_URL}${path.startsWith('/') ? '' : '/'}${path}`
}

export function apiFetch(path, options) {
  return fetch(apiUrl(path), options)
}
