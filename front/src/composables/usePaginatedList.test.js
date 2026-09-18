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

  // --- race-safety (generation token) ---

  function deferred() {
    let resolve;
    let reject;
    const promise = new Promise((res, rej) => { resolve = res; reject = rej; });
    return { promise, resolve, reject };
  }

  it('old search A resolving after search B keeps B visible', async () => {
    const a = deferred();
    const b = deferred();
    const fetchFn = vi.fn()
      .mockImplementationOnce(() => a.promise)   // search A
      .mockImplementationOnce(() => b.promise);  // search B
    const list = usePaginatedList(fetchFn, { perPage: 50 });

    const pa = list.loadFirstPage({ search: 'A' });
    const pb = list.loadFirstPage({ search: 'B' });

    // B resolves first, then the stale A resolves late.
    b.resolve(makeResponse([{ id: 2 }], { page: 1, lastPage: 1, total: 1 }));
    await pb;
    a.resolve(makeResponse([{ id: 1 }], { page: 1, lastPage: 1, total: 1 }));
    await pa;

    expect(list.items.value.map((i) => i.id)).toEqual([2]);
  });

  it('reset while page 1 in flight ignores the stale response', async () => {
    const d = deferred();
    const fetchFn = vi.fn(() => d.promise);
    const list = usePaginatedList(fetchFn);

    const p = list.loadFirstPage({ search: 'A' });
    list.reset();
    d.resolve(makeResponse([{ id: 1 }], { page: 1, lastPage: 3, total: 150 }));
    await p;

    expect(list.items.value).toEqual([]);
    expect(list.lastPage.value).toBe(1);
    expect(list.loading.value).toBe(false);
  });

  it('page 2 for filter A resolving after filter B begins does not append A rows', async () => {
    const p1 = makeResponse([{ id: 1 }], { page: 1, lastPage: 2, total: 2 });
    const a2 = deferred();
    const fetchFn = vi.fn()
      .mockResolvedValueOnce(p1)          // filter A page 1
      .mockImplementationOnce(() => a2.promise)  // filter A page 2 (slow)
      .mockResolvedValueOnce(makeResponse([{ id: 9 }], { page: 1, lastPage: 1, total: 1 })); // filter B page 1
    const list = usePaginatedList(fetchFn, { perPage: 1 });

    await list.loadFirstPage({ search: 'A' });
    const pageA2 = list.loadNextPage();      // starts A page 2
    await list.loadFirstPage({ search: 'B' }); // new filter B resets
    a2.resolve(makeResponse([{ id: 2 }], { page: 2, lastPage: 2, total: 2 })); // stale A page 2 lands
    await pageA2;

    expect(list.items.value.map((i) => i.id)).toEqual([9]);
  });

  it('loadNextPage retains page-1 search/filter params automatically', async () => {
    const fetchFn = vi.fn(() => Promise.resolve(makeResponse([], { page: 1, lastPage: 2, total: 0 })));
    const list = usePaginatedList(fetchFn, { perPage: 20 });

    await list.loadFirstPage({ search: 'temp', status: 'active' });
    await list.loadNextPage(); // no params passed — must reuse page-1 params

    expect(fetchFn).toHaveBeenLastCalledWith({ per_page: 20, page: 2, search: 'temp', status: 'active' });
  });

  it('two loadNextPage calls while in flight fire only one request', async () => {
    const d = deferred();
    const fetchFn = vi.fn()
      .mockResolvedValueOnce(makeResponse([{ id: 1 }], { page: 1, lastPage: 3, total: 3 }))
      .mockImplementationOnce(() => d.promise);
    const list = usePaginatedList(fetchFn, { perPage: 1 });

    await list.loadFirstPage();
    list.loadNextPage();
    list.loadNextPage();

    expect(fetchFn).toHaveBeenCalledTimes(2); // page1 + one page2
    d.resolve(makeResponse([{ id: 2 }], { page: 2, lastPage: 3, total: 3 }));
  });

  it('error from a stale request does not overwrite current list state', async () => {
    const a = deferred();
    const fetchFn = vi.fn()
      .mockImplementationOnce(() => a.promise) // search A (will reject, stale)
      .mockResolvedValueOnce(makeResponse([{ id: 5 }], { page: 1, lastPage: 1, total: 1 })); // search B
    const list = usePaginatedList(fetchFn);

    const pa = list.loadFirstPage({ search: 'A' });
    await list.loadFirstPage({ search: 'B' });
    a.reject(new Error('network'));
    await pa.catch(() => {}); // stale rejection swallowed

    expect(list.items.value.map((i) => i.id)).toEqual([5]);
    expect(list.loading.value).toBe(false);
  });
});
