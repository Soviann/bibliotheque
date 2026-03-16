import { QueryClientProvider } from "@tanstack/react-query";
import { renderHook, waitFor, act } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import type { ReactNode } from "react";
import { MemoryRouter } from "react-router-dom";
import { toast } from "sonner";
import { useCreateComic } from "../../../hooks/useCreateComic";
import { useUpdateComic } from "../../../hooks/useUpdateComic";
import { queryKeys } from "../../../queryKeys";
import { createTestQueryClient } from "../../helpers/test-utils";
import { createMockComicSeries } from "../../helpers/factories";
import { server } from "../../helpers/server";

// We test useOfflineMutation indirectly through useCreateComic,
// which is the simplest consumer.

vi.mock("sonner", async () => {
  const actual = await vi.importActual("sonner");
  return {
    ...actual,
    toast: Object.assign(vi.fn(), {
      error: vi.fn(),
      info: vi.fn(),
      success: vi.fn(),
    }),
  };
});

// Mock offlineQueue to avoid IndexedDB dependency in jsdom
vi.mock("../../../services/offlineQueue", () => ({
  enqueue: vi.fn().mockResolvedValue(1),
  getPendingCount: vi.fn().mockResolvedValue(0),
}));

function createWrapper(queryClient = createTestQueryClient()) {
  return function Wrapper({ children }: { children: ReactNode }) {
    return (
      <QueryClientProvider client={queryClient}>
        <MemoryRouter>{children}</MemoryRouter>
      </QueryClientProvider>
    );
  };
}

describe("useOfflineMutation", () => {
  beforeEach(() => {
    localStorage.clear();
  });

  afterEach(() => {
    Object.defineProperty(navigator, "onLine", {
      configurable: true,
      value: true,
      writable: true,
    });
  });

  it("executes mutation normally when online", async () => {
    Object.defineProperty(navigator, "onLine", {
      configurable: true,
      value: true,
      writable: true,
    });

    const created = createMockComicSeries({ id: 1, title: "Online Comic" });

    server.use(
      http.post("/api/comic_series", () =>
        HttpResponse.json(created, { status: 201 }),
      ),
    );

    const { result } = renderHook(() => useCreateComic(), {
      wrapper: createWrapper(),
    });

    await act(async () => {
      result.current.mutate({ title: "Online Comic" });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(result.current.data?.title).toBe("Online Comic");
  });

  it("queues mutation when offline", async () => {
    Object.defineProperty(navigator, "onLine", {
      configurable: true,
      value: false,
      writable: true,
    });

    // Mock serviceWorker.ready for registerSync
    Object.defineProperty(navigator, "serviceWorker", {
      configurable: true,
      value: {
        ready: Promise.resolve({ sync: { register: vi.fn() } }),
      },
      writable: true,
    });

    const { result } = renderHook(() => useCreateComic(), {
      wrapper: createWrapper(),
    });

    await act(async () => {
      result.current.mutate({ title: "Offline Comic" });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    // When offline, data is undefined (no actual API call)
    expect(result.current.data).toBeUndefined();
  });

  it("silently skips registration when no serviceWorker in navigator", async () => {
    Object.defineProperty(navigator, "onLine", {
      configurable: true,
      value: false,
      writable: true,
    });

    // Delete the serviceWorker property so "serviceWorker" in navigator is false
    const originalSW = Object.getOwnPropertyDescriptor(navigator, "serviceWorker");
    // @ts-expect-error -- deliberate deletion for testing
    delete (navigator as Record<string, unknown>).serviceWorker;

    const { result } = renderHook(() => useCreateComic(), {
      wrapper: createWrapper(),
    });

    await act(async () => {
      result.current.mutate({ title: "No SW Comic" });
    });

    // Should succeed without throwing
    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    // Restore serviceWorker property
    if (originalSW) {
      Object.defineProperty(navigator, "serviceWorker", originalSW);
    }
  });

  it("silently skips when Background Sync not supported", async () => {
    Object.defineProperty(navigator, "onLine", {
      configurable: true,
      value: false,
      writable: true,
    });

    // serviceWorker.ready resolves to registration WITHOUT sync
    Object.defineProperty(navigator, "serviceWorker", {
      configurable: true,
      value: {
        ready: Promise.resolve({}),
      },
      writable: true,
    });

    const { result } = renderHook(() => useCreateComic(), {
      wrapper: createWrapper(),
    });

    await act(async () => {
      result.current.mutate({ title: "No Sync Comic" });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
  });

  it("returns error state when online mutation fails", async () => {
    Object.defineProperty(navigator, "onLine", {
      configurable: true,
      value: true,
      writable: true,
    });

    server.use(
      http.post("/api/comic_series", () =>
        HttpResponse.json({ detail: "Validation failed" }, { status: 422 }),
      ),
    );

    const { result } = renderHook(() => useCreateComic(), {
      wrapper: createWrapper(),
    });

    await act(async () => {
      result.current.mutate({ title: "Bad Comic" });
    });

    await waitFor(() => expect(result.current.isError).toBe(true));
  });

  it("calls toast.info when mutation enqueued offline", async () => {
    Object.defineProperty(navigator, "onLine", {
      configurable: true,
      value: false,
      writable: true,
    });

    Object.defineProperty(navigator, "serviceWorker", {
      configurable: true,
      value: {
        ready: Promise.resolve({ sync: { register: vi.fn() } }),
      },
      writable: true,
    });

    const { result } = renderHook(() => useCreateComic(), {
      wrapper: createWrapper(),
    });

    await act(async () => {
      result.current.mutate({ title: "Toast Comic" });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(toast.info).toHaveBeenCalledWith(
      "Opération enregistrée, sera synchronisée au retour en ligne",
    );
  });

  it("calls onOfflineSuccess callback when offline mutation succeeds", async () => {
    Object.defineProperty(navigator, "onLine", {
      configurable: true,
      value: false,
      writable: true,
    });

    Object.defineProperty(navigator, "serviceWorker", {
      configurable: true,
      value: {
        ready: Promise.resolve({ sync: { register: vi.fn() } }),
      },
      writable: true,
    });

    // useUpdateComic doesn't pass onOfflineSuccess, so we test via useOfflineMutation directly
    const { useOfflineMutation } = await import("../../../hooks/useOfflineMutation");
    const onOfflineSuccess = vi.fn();

    const { result } = renderHook(
      () =>
        useOfflineMutation({
          mutationFn: vi.fn(),
          offlineOperation: "create" as const,
          offlineResourceType: "comic_series" as const,
          onOfflineSuccess,
          queryKeysToInvalidate: [queryKeys.comics.all],
        }),
      { wrapper: createWrapper() },
    );

    await act(async () => {
      result.current.mutate({ title: "Callback Comic" });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(onOfflineSuccess).toHaveBeenCalledTimes(1);
  });

  it("calls onSuccess callback on successful online mutation", async () => {
    Object.defineProperty(navigator, "onLine", {
      configurable: true,
      value: true,
      writable: true,
    });

    const created = createMockComicSeries({ id: 10, title: "Success CB" });

    server.use(
      http.post("/api/comic_series", () =>
        HttpResponse.json(created, { status: 201 }),
      ),
    );

    const { useOfflineMutation } = await import("../../../hooks/useOfflineMutation");
    const onSuccess = vi.fn();

    const { result } = renderHook(
      () =>
        useOfflineMutation({
          mutationFn: (data: Record<string, unknown>) =>
            Promise.resolve(data),
          offlineOperation: "create" as const,
          offlineResourceType: "comic_series" as const,
          onSuccess,
          queryKeysToInvalidate: [],
        }),
      { wrapper: createWrapper() },
    );

    await act(async () => {
      result.current.mutate({ title: "Success CB" });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(onSuccess).toHaveBeenCalledTimes(1);
  });

  it("returns undefined for offlineResourceId when not provided (useCreateComic)", async () => {
    Object.defineProperty(navigator, "onLine", {
      configurable: true,
      value: false,
      writable: true,
    });

    Object.defineProperty(navigator, "serviceWorker", {
      configurable: true,
      value: {
        ready: Promise.resolve({ sync: { register: vi.fn() } }),
      },
      writable: true,
    });

    const { enqueue } = await import("../../../services/offlineQueue");

    const { result } = renderHook(() => useCreateComic(), {
      wrapper: createWrapper(),
    });

    await act(async () => {
      result.current.mutate({ title: "No Resource ID" });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    // useCreateComic does not provide offlineResourceId, so resourceId should be undefined
    expect(enqueue).toHaveBeenCalledWith(
      expect.objectContaining({ resourceId: undefined }),
    );
  });

  it("does not invalidate queries when offline", async () => {
    Object.defineProperty(navigator, "onLine", {
      configurable: true,
      value: false,
      writable: true,
    });

    Object.defineProperty(navigator, "serviceWorker", {
      configurable: true,
      value: {
        ready: Promise.resolve({ sync: { register: vi.fn() } }),
      },
      writable: true,
    });

    const queryClient = createTestQueryClient();
    queryClient.setQueryData(queryKeys.comics.all, { member: [], totalItems: 0 });

    const { result } = renderHook(() => useCreateComic(), {
      wrapper: createWrapper(queryClient),
    });

    await act(async () => {
      result.current.mutate({ title: "Offline Comic" });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    // Should NOT invalidate when offline
    expect(queryClient.getQueryState(queryKeys.comics.all)?.isInvalidated).toBe(false);
  });
});
