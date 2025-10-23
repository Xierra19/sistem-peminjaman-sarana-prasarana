import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Ensure CSRF header is sent for Laravel (uses XSRF-TOKEN cookie)
try {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
  if (match && match[1]) {
    const token = decodeURIComponent(match[1])
    window.axios.defaults.headers.common['X-XSRF-TOKEN'] = token
  }
} catch (e) {
  // no-op
}
