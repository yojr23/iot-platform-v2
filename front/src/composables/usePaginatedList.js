import { computed, ref } from 'vue';

import { paginatedItems } from '@/utils/formatters';

/**
 * Reusable pagination composable for Laravel-paginated API lists.
 *
 * Usage:
 *   const { items, page, lastPage, hasMore, loading, loadingMore, loadFirstPage, loadNextPage, reset } =
 *     usePaginatedList(fetchFn, { perPage: 50 });
 *
 *   await loadFirstPage();
 *   await loadNextPage();   // appends page 2, 3, ...
 *   reset();                // back to empty + page 1
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
    loading.value = true;
    try {
      const response = await fetchFn({ per_page: perPage, page: 1, ...extraParams });
      applyPage(response);
    } finally {
      loading.value = false;
    }
  }

  async function loadNextPage(extraParams = {}) {
    if (loadingMore.value || !hasMore.value) {
      return;
    }
    loadingMore.value = true;
    try {
      const response = await fetchFn({ per_page: perPage, page: page.value + 1, ...extraParams });
      applyPage(response, { append: true });
    } finally {
      loadingMore.value = false;
    }
  }

  function reset() {
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
