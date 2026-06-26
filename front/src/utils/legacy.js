const fallbackLegacyBaseUrl = window.location.port === '5173'
  ? 'http://localhost:8000'
  : window.location.origin;

const legacyBaseUrl = (
  import.meta.env.VITE_LEGACY_APP_URL
  || import.meta.env.VITE_API_PROXY_TARGET
  || fallbackLegacyBaseUrl
).replace(/\/$/, '');

export function legacyUrl(path) {
  const normalizedPath = String(path || '').startsWith('/') ? path : `/${path}`;

  return `${legacyBaseUrl}${normalizedPath}`;
}
