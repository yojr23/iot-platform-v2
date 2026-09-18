import { computed, ref } from 'vue';

import { paginatedItems } from '@/utils/formatters';

/**
 * Reusable pagination composable for Laravel-paginated API lists.
 *
 * Race safety: every loadFirstPage()/reset() bumps a generation token. A
 * response (or error) belonging to an older generation is ignored, so a slow
 * search-A response can never overwrite the newer search-B result, and a
 * page-2 request started under filter A cannot append into filter B.
 * loadNextPage() runs in the same generation as the last first page and reuses
 * that page's params, so callers don't have to re-pass search/filter.
 *
 * Usage:
 *   const { items, page, lastPage, hasMore, loading, loadingMore, loadFirstPage, loadNextPage, reset } =
 *     usePaginatedList(fetchFn, { perPage: 50 });
 *
 *   await loadFirstPage({ search: 'sala' });
 *   await loadNextPage();   // appends page 2 with the same params
 *   reset();                // back to empty + page 1, invalidates in-flight
 *
 * @param {Function} fetchFn  async (params: { per_page, page, ...extra }) => LaravelPaginatedResponse
 * @param {{ perPage?: number }} options
 */
export function usePaginatedList(fetchFn, { perPage = 50 } = {}) {
  const items = ref([]);
  const page = ref(1);
  const lastPage = ref(1);
  const total = ref(0);
  const loading = ref(false);
  const loadingMore = ref(false);

  // Generation token: increments on every first-page load and reset.
  let generation = 0;
  // Params used by the active first page, reused by loadNextPage.
  let activeParams = {};

  const hasMore = computed(() => page.value < lastPage.value);

  function applyPage(response, { append = false } = {}) {
    const newItems = paginatedItems(response);
    const meta = response?.data ?? {};
    page.value = meta.current_page ?? page.value;
    lastPage.value = meta.last_page ?? page.value;
    total.value = meta.total ?? (append ? total.value + newItems.length : newItems.length);
    items.value = append ? [...items.value, ...newItems] : newItems;
  }

  async function loadFirstPage(extraParams = {}) {
    const gen = ++generation;
    activeParams = { ...extraParams };
    loading.value = true;
    try {
      const response = await fetchFn({ per_page: perPage, page: 1, ...extraParams });
      if (gen !== generation) return; // stale — a newer load/reset superseded us
      applyPage(response);
    } finally {
      if (gen === generation) loading.value = false;
    }
  }

  async function loadNextPage() {
    if (loadingMore.value || !hasMore.value) {
      return;
    }
    const gen = generation; // stay in the current first-page generation
    loadingMore.value = true;
    try {
      const response = await fetchFn({ per_page: perPage, page: page.value + 1, ...activeParams });
      if (gen !== generation) return; // filter/search changed while page-2 was in flight
      applyPage(response, { append: true });
    } finally {
      if (gen === generation) loadingMore.value = false;
    }
  }

  function reset() {
    generation++; // invalidate any in-flight request
    activeParams = {};
    items.value = [];
    page.value = 1;
    lastPage.value = 1;
    total.value = 0;
    loading.value = false;
    loadingMore.value = false;
  }

  return {
    items,
    page,
    lastPage,
    total,
    hasMore,
    loading,
    loadingMore,
    loadFirstPage,
    loadNextPage,
    reset,
  };
}
