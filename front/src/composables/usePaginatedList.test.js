import { describe, expect, it, vi } from 'vitest';

import { usePaginatedList } from './usePaginatedList';

function makeResponse(data, meta = {}) {
  return { data: { data, current_page: meta.page ?? 1, last_page: meta.lastPage ?? 1, total: meta.total ?? data.length, per_page: meta.perPage ?? 50 } };
}

describe('usePaginatedList', () => {
  it('loads first page and populates items/lastPage/hasMore', async () => {
    const fetchFn = vi.fn(() => Promise.resolve(makeResponse(
      [{ id: 1 }, { id: 2 }],
      { page: 1, lastPage: 3, total: 150 }
    )));
    const list = usePaginatedList(fetchFn, { perPage: 50 });

    expect(list.loading.value).toBe(false);
    expect(list.items.value).toEqual([]);
    expect(list.hasMore.value).toBe(false);

    await list.loadFirstPage();

    expect(list.loading.value).toBe(false);
    expect(list.items.value).toHaveLength(2);
    expect(list.page.value).toBe(1);
    expect(list.lastPage.value).toBe(3);
    expect(list.hasMore.value).toBe(true);
    expect(fetchFn).toHaveBeenCalledWith({ per_page: 50, page: 1 });
  });

  it('appends page 2 without duplicating items', async () => {
    const page1 = makeResponse([{ id: 1 }, { id: 2 }], { page: 1, lastPage: 2, total: 4 });
    const page2 = makeResponse([{ id: 3 }, { id: 4 }], { page: 2, lastPage: 2, total: 4 });
    const fetchFn = vi.fn()
      .mockResolvedValueOnce(page1)
      .mockResolvedValueOnce(page2);
    const list = usePaginatedList(fetchFn, { perPage: 2 });

    await list.loadFirstPage();
    expect(list.items.value).toHaveLength(2);
    expect(list.hasMore.value).toBe(true);

    await list.loadNextPage();
    expect(list.items.value).toHaveLength(4);
    expect(list.items.value.map((i) => i.id)).toEqual([1, 2, 3, 4]);
    expect(list.hasMore.value).toBe(false);
  });

  it('does not fetch when already at last page', async () => {
    const fetchFn = vi.fn(() => Promise.resolve(makeResponse([{ id: 1 }], { page: 1, lastPage: 1, total: 1 })));
    const list = usePaginatedList(fetchFn);

    await list.loadFirstPage();
    expect(list.hasMore.value).toBe(false);

    await list.loadNextPage();
    expect(fetchFn).toHaveBeenCalledTimes(1);
  });

  it('does not duplicate while loadingMore is in flight', async () => {
    let resolvePage2;
    const page2Promise = new Promise((resolve) => { resolvePage2 = resolve; });
    const fetchFn = vi.fn()
      .mockResolvedValueOnce(makeResponse([{ id: 1 }], { page: 1, lastPage: 2, total: 2 }))
      .mockImplementationOnce(() => page2Promise);
    const list = usePaginatedList(fetchFn, { perPage: 1 });

    await list.loadFirstPage();
    expect(list.hasMore.value).toBe(true);

    const p1 = list.loadNextPage();
    const p2 = list.loadNextPage();

    resolvePage2(makeResponse([{ id: 2 }], { page: 2, lastPage: 2, total: 2 }));
    await p1;
    await p2;

    expect(list.items.value).toHaveLength(2);
    expect(fetchFn).toHaveBeenCalledTimes(2);
  });

  it('reset clears all state back to empty page 1', async () => {
    const fetchFn = vi.fn(() => Promise.resolve(makeResponse([{ id: 1 }], { page: 1, lastPage: 3, total: 150 })));
    const list = usePaginatedList(fetchFn);

    await list.loadFirstPage();
    expect(list.items.value).toHaveLength(1);
    expect(list.lastPage.value).toBe(3);

    list.reset();
    expect(list.items.value).toEqual([]);
    expect(list.page.value).toBe(1);
    expect(list.lastPage.value).toBe(1);
    expect(list.total.value).toBe(0);
    expect(list.hasMore.value).toBe(false);
    expect(list.loading.value).toBe(false);
    expect(list.loadingMore.value).toBe(false);
  });

  it('propagates errors via throw', async () => {
    const fetchFn = vi.fn()
      .mockResolvedValueOnce(makeResponse([{ id: 1 }], { page: 1, lastPage: 2, total: 2 }))
      .mockRejectedValue(new Error('network'));
    const list = usePaginatedList(fetchFn);

    await list.loadFirstPage();
    expect(list.loading.value).toBe(false);

    await expect(list.loadNextPage()).rejects.toThrow('network');
    expect(list.loadingMore.value).toBe(false);
  });

  it('handles empty page gracefully', async () => {
    const fetchFn = vi.fn(() => Promise.resolve(makeResponse([], { page: 2, lastPage: 2, total: 0 })));
    const list = usePaginatedList(fetchFn);

    await list.loadFirstPage();
    expect(list.items.value).toEqual([]);
    expect(list.hasMore.value).toBe(false);
  });

  it('passes extra params through to the fetch function', async () => {
    // lastPage: 2 so hasMore is true after page 1 and loadNextPage actually fires page 2.
    const fetchFn = vi.fn(() => Promise.resolve(makeResponse([], { page: 1, lastPage: 2, total: 0 })));
    const list = usePaginatedList(fetchFn, { perPage: 100 });

    await list.loadFirstPage({ search: 'sala', device_id: 5 });
    expect(fetchFn).toHaveBeenCalledWith({ per_page: 100, page: 1, search: 'sala', device_id: 5 });

    await list.loadNextPage({ search: 'sala', device_id: 5 });
    expect(fetchFn).toHaveBeenCalledWith({ per_page: 100, page: 2, search: 'sala', device_id: 5 });
  });
});
