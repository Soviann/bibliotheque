import "fake-indexeddb/auto";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { act, renderHook, waitFor } from "@testing-library/react";
import { createElement, type ReactNode } from "react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { useOfflineMutation } from "../../hooks/useOfflineMutation";
import { getAll, _resetDb } from "../../services/offlineQueue";

function createWrapper() {
  const queryClient = new QueryClient({
    defaultOptions: { mutations: { retry: false }, queries: { retry: false } },
  });
  return ({ children }: { children: ReactNode }) =>
    createElement(QueryClientProvider, { client: queryClient }, children);
}

describe("useOfflineMutation", () => {
  beforeEach(async () => {
    await _resetDb();
    const dbs = await indexedDB.databases();
    for (const db of dbs) {
      if (db.name) indexedDB.deleteDatabase(db.name);
    }
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it("calls mutationFn directly when online", async () => {
    const mutationFn = vi.fn().mockResolvedValue({ id: 1 });

    Object.defineProperty(navigator, "onLine", { configurable: true, value: true, writable: true });

    const { result } = renderHook(
      () =>
        useOfflineMutation({
          mutationFn,
          offlineOperation: "create",
          offlineResourceType: "comic_series",
          queryKeysToInvalidate: [["comics"]],
        }),
      { wrapper: createWrapper() },
    );

    act(() => {
      result.current.mutate({ title: "Test" });
    });

    await waitFor(() => {
      expect(mutationFn).toHaveBeenCalledWith({ title: "Test" });
    });

    const items = await getAll();
    expect(items).toHaveLength(0);
  });

  it("enqueues operation when offline instead of calling mutationFn", async () => {
    const mutationFn = vi.fn();
    const syncRegister = vi.fn();

    vi.stubGlobal("navigator", {
      ...navigator,
      onLine: false,
      serviceWorker: {
        ready: Promise.resolve({ sync: { register: syncRegister } }),
      },
    });

    const { result } = renderHook(
      () =>
        useOfflineMutation({
          mutationFn,
          offlineOperation: "create",
          offlineResourceType: "comic_series",
          queryKeysToInvalidate: [["comics"]],
        }),
      { wrapper: createWrapper() },
    );

    await act(async () => {
      result.current.mutate({ title: "Offline Comic" });
    });

    await waitFor(async () => {
      const items = await getAll();
      expect(items).toHaveLength(1);
    });

    expect(mutationFn).not.toHaveBeenCalled();

    const items = await getAll();
    expect(items[0]).toMatchObject({
      operation: "create",
      payload: { title: "Offline Comic" },
      resourceType: "comic_series",
      status: "pending",
    });
  });

  it("calls onOfflineSuccess callback when offline", async () => {
    const onOfflineSuccess = vi.fn();
    const syncRegister = vi.fn();

    vi.stubGlobal("navigator", {
      ...navigator,
      onLine: false,
      serviceWorker: {
        ready: Promise.resolve({ sync: { register: syncRegister } }),
      },
    });

    const { result } = renderHook(
      () =>
        useOfflineMutation({
          mutationFn: vi.fn(),
          offlineOperation: "create",
          offlineResourceType: "comic_series",
          onOfflineSuccess,
          queryKeysToInvalidate: [["comics"]],
        }),
      { wrapper: createWrapper() },
    );

    await act(async () => {
      result.current.mutate({ title: "Test" });
    });

    await waitFor(() => {
      expect(onOfflineSuccess).toHaveBeenCalledTimes(1);
    });
  });

  it("stores offlineResourceId when provided", async () => {
    const syncRegister = vi.fn();

    vi.stubGlobal("navigator", {
      ...navigator,
      onLine: false,
      serviceWorker: {
        ready: Promise.resolve({ sync: { register: syncRegister } }),
      },
    });

    const { result } = renderHook(
      () =>
        useOfflineMutation<unknown, { id: number; title: string }>({
          mutationFn: vi.fn(),
          offlineOperation: "update",
          offlineResourceId: (v) => String(v.id),
          offlineResourceType: "comic_series",
          queryKeysToInvalidate: [["comics"]],
        }),
      { wrapper: createWrapper() },
    );

    await act(async () => {
      result.current.mutate({ id: 42, title: "Updated" });
    });

    await waitFor(async () => {
      const items = await getAll();
      expect(items).toHaveLength(1);
      expect(items[0].resourceId).toBe("42");
      expect(items[0].operation).toBe("update");
    });
  });

  it("does not call hook-level onSuccess when offline", async () => {
    const onSuccess = vi.fn();
    const syncRegister = vi.fn();

    vi.stubGlobal("navigator", {
      ...navigator,
      onLine: false,
      serviceWorker: {
        ready: Promise.resolve({ sync: { register: syncRegister } }),
      },
    });

    const { result } = renderHook(
      () =>
        useOfflineMutation({
          mutationFn: vi.fn(),
          offlineOperation: "create",
          offlineResourceType: "comic_series",
          onSuccess,
          queryKeysToInvalidate: [["comics"]],
        }),
      { wrapper: createWrapper() },
    );

    await act(async () => {
      result.current.mutate({ title: "Test" });
    });

    // Wait for mutation to complete
    await waitFor(async () => {
      const items = await getAll();
      expect(items).toHaveLength(1);
    });

    // onSuccess should NOT have been called (offline path)
    expect(onSuccess).not.toHaveBeenCalled();
  });

  it("registers Background Sync when offline", async () => {
    const syncRegister = vi.fn();

    vi.stubGlobal("navigator", {
      ...navigator,
      onLine: false,
      serviceWorker: {
        ready: Promise.resolve({ sync: { register: syncRegister } }),
      },
    });

    const { result } = renderHook(
      () =>
        useOfflineMutation({
          mutationFn: vi.fn(),
          offlineOperation: "create",
          offlineResourceType: "comic_series",
          queryKeysToInvalidate: [["comics"]],
        }),
      { wrapper: createWrapper() },
    );

    await act(async () => {
      result.current.mutate({ title: "Test" });
    });

    await waitFor(() => {
      expect(syncRegister).toHaveBeenCalledWith("offline-sync");
    });
  });
});
