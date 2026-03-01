import { QueryClientProvider } from "@tanstack/react-query";
import { renderHook, waitFor, act } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import type { ReactNode } from "react";
import { MemoryRouter } from "react-router-dom";
import { useCreateComic } from "../../../hooks/useCreateComic";
import { createTestQueryClient } from "../../helpers/test-utils";
import { createMockComicSeries } from "../../helpers/factories";
import { server } from "../../helpers/server";

// We test useOfflineMutation indirectly through useCreateComic,
// which is the simplest consumer.

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
    queryClient.setQueryData(["comics"], { member: [], totalItems: 0 });

    const { result } = renderHook(() => useCreateComic(), {
      wrapper: createWrapper(queryClient),
    });

    await act(async () => {
      result.current.mutate({ title: "Offline Comic" });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    // Should NOT invalidate when offline
    expect(queryClient.getQueryState(["comics"])?.isInvalidated).toBe(false);
  });
});
