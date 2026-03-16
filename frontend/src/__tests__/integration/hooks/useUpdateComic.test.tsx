import { QueryClientProvider } from "@tanstack/react-query";
import { renderHook, waitFor, act } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import type { ReactNode } from "react";
import { MemoryRouter } from "react-router-dom";
import { useUpdateComic } from "../../../hooks/useUpdateComic";
import { queryKeys } from "../../../queryKeys";
import { enqueue } from "../../../services/offlineQueue";
import { createTestQueryClient } from "../../helpers/test-utils";
import {
  createMockComicSeries,
  createMockHydraCollection,
} from "../../helpers/factories";
import { server } from "../../helpers/server";

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

describe("useUpdateComic", () => {
  beforeEach(() => {
    localStorage.clear();
    Object.defineProperty(navigator, "onLine", {
      configurable: true,
      value: true,
      writable: true,
    });
  });

  it("updates comic series via API", async () => {
    const updated = createMockComicSeries({ id: 3, title: "Updated Title" });

    server.use(
      http.patch("/api/comic_series/3", () => HttpResponse.json(updated)),
    );

    const { result } = renderHook(() => useUpdateComic(), {
      wrapper: createWrapper(),
    });

    await act(async () => {
      result.current.mutate({ id: 3, title: "Updated Title" });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(result.current.data?.title).toBe("Updated Title");
  });

  it("updates collection via setQueryData and invalidates comic detail on success", async () => {
    const queryClient = createTestQueryClient();
    const oldComic = createMockComicSeries({ id: 3, title: "Old" });
    const newComic = createMockComicSeries({ id: 3, title: "New" });

    queryClient.setQueryData(queryKeys.comics.all, createMockHydraCollection([oldComic]));
    queryClient.setQueryData(queryKeys.comics.detail(3), oldComic);

    server.use(
      http.patch("/api/comic_series/3", () => HttpResponse.json(newComic)),
    );

    const { result } = renderHook(() => useUpdateComic(), {
      wrapper: createWrapper(queryClient),
    });

    await act(async () => {
      result.current.mutate({ id: 3, title: "New" });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    // Collection should NOT be invalidated (updated via setQueryData instead)
    expect(queryClient.getQueryState(queryKeys.comics.all)?.isInvalidated).toBe(false);
    // Collection should contain the server response
    const collection = queryClient.getQueryData<{ member: { id: number; title: string }[] }>(queryKeys.comics.all);
    expect(collection?.member.find((c) => c.id === 3)?.title).toBe("New");
    // Detail should be invalidated for fresh refetch
    expect(queryClient.getQueryState(queryKeys.comics.detail(3))?.isInvalidated).toBe(true);
  });

  it("does not invalidate unrelated comic detail queries", async () => {
    const queryClient = createTestQueryClient();

    queryClient.setQueryData(queryKeys.comics.all, createMockHydraCollection([
      createMockComicSeries({ id: 3, title: "Target" }),
      createMockComicSeries({ id: 5, title: "Other" }),
    ]));
    queryClient.setQueryData(queryKeys.comics.detail(3), createMockComicSeries({ id: 3, title: "Target" }));
    queryClient.setQueryData(queryKeys.comics.detail(5), createMockComicSeries({ id: 5, title: "Other" }));

    server.use(
      http.patch("/api/comic_series/3", () =>
        HttpResponse.json(createMockComicSeries({ id: 3, title: "Updated" })),
      ),
    );

    const { result } = renderHook(() => useUpdateComic(), {
      wrapper: createWrapper(queryClient),
    });

    await act(async () => {
      result.current.mutate({ id: 3, title: "Updated" });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    // comic 3 should be invalidated (targeted)
    expect(queryClient.getQueryState(queryKeys.comics.detail(3))?.isInvalidated).toBe(true);
    // comic 5 should NOT be invalidated (not related)
    expect(queryClient.getQueryState(queryKeys.comics.detail(5))?.isInvalidated).toBe(false);
  });

  it("handles API error", async () => {
    server.use(
      http.patch("/api/comic_series/3", () =>
        HttpResponse.json({ detail: "Not Found" }, { status: 404 }),
      ),
    );

    const { result } = renderHook(() => useUpdateComic(), {
      wrapper: createWrapper(),
    });

    await act(async () => {
      result.current.mutate({ id: 3, title: "Fail" });
    });

    await waitFor(() => expect(result.current.isError).toBe(true));

    expect(result.current.error?.message).toBe("Not Found");
  });

  it("enqueues mutation with correct resourceId when offline", async () => {
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

    const { result } = renderHook(() => useUpdateComic(), {
      wrapper: createWrapper(),
    });

    await act(async () => {
      result.current.mutate({ id: 3, title: "Offline Update" });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(enqueue).toHaveBeenCalledWith(
      expect.objectContaining({
        operation: "update",
        resourceId: "3",
        resourceType: "comic_series",
      }),
    );
  });
});
