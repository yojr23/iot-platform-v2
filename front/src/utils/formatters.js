export function formatDate(value) {
  if (!value) {
    return '-';
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return String(value);
  }

  return new Intl.DateTimeFormat('es-CO', {
    dateStyle: 'medium',
    timeStyle: 'short'
  }).format(date);
}

export function formatNumber(value, options = {}) {
  if (value === null || value === undefined || value === '') {
    return '-';
  }

  const numeric = Number(value);

  if (Number.isNaN(numeric)) {
    return String(value);
  }

  return new Intl.NumberFormat('es-CO', {
    maximumFractionDigits: 2,
    ...options
  }).format(numeric);
}

export function asArray(value) {
  if (Array.isArray(value)) {
    return value;
  }

  if (Array.isArray(value?.data)) {
    return value.data;
  }

  return [];
}

export function paginatedItems(response) {
  const payload = response?.data ?? response;

  if (Array.isArray(payload)) {
    return payload;
  }

  if (Array.isArray(payload?.data)) {
    return payload.data;
  }

  if (Array.isArray(payload?.readings?.data)) {
    return payload.readings.data;
  }

  return [];
}

export function statusLabel(value) {
  return value ? 'Activo' : 'Inactivo';
}

export function severityLabel(value) {
  const labels = {
    info: 'Info',
    warning: 'Advertencia',
    danger: 'Critica'
  };

  return labels[value] || value || '-';
}

export function validationMessage(errors, field) {
  const fieldErrors = errors?.[field];

  if (Array.isArray(fieldErrors)) {
    return fieldErrors[0] || '';
  }

  return fieldErrors || '';
}
