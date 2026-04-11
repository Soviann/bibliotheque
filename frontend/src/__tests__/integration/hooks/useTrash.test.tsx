import { QueryClientProvider } from "@tanstack/react-query";
import { renderHook, waitFor, act } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import type { ReactNode } from "react";
import { MemoryRouter } from "react-router-dom";
import {
  useTrash,
  useRestoreComic,
  usePermanentDelete,
} from "../../../hooks/useTrash";
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

describe("useTrash", () => {
  beforeEach(() => {
    localStorage.clear();
    Object.defineProperty(navigator, "onLine", {
      configurable: true,
      value: true,
      writable: true,
    });
  });

  it("returns trash collection", async () => {
    const deleted = createMockComicSeries({ id: 1, title: "Deleted Series" });

    server.use(
      http.get("/api/trash", () =>
        HttpResponse.json(createMockHydraCollection([deleted], "/api/trash")),
      ),
    );

    const { result } = renderHook(() => useTrash(), {
      wrapper: createWrapper(),
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(result.current.data?.member).toHaveLength(1);
    expect(result.current.data?.member[0].title).toBe("Deleted Series");
  });

  it("returns error on failed API request", async () => {
    server.use(
      http.get("/api/trash", () =>
        HttpResponse.json({ detail: "Server error" }, { status: 500 }),
      ),
    );

    const { result } = renderHook(() => useTrash(), {
      wrapper: createWrapper(),
    });

    await waitFor(() => expect(result.current.isError).toBe(true));
  });

  it("returns empty trash", async () => {
    server.use(
      http.get("/api/trash", () =>
        HttpResponse.json(createMockHydraCollection([], "/api/trash")),
      ),
    );

    const { result } = renderHook(() => useTrash(), {
      wrapper: createWrapper(),
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(result.current.data?.member).toHaveLength(0);
  });
});

describe("useRestoreComic", () => {
  beforeEach(() => {
    localStorage.clear();
    Object.defineProperty(navigator, "onLine", {
      configurable: true,
      value: true,
      writable: true,
    });
  });

  it("restores a comic via API", async () => {
    const restored = createMockComicSeries({ id: 7, title: "Restored" });

    server.use(
      http.put("/api/comic_series/7/restore", () =>
        HttpResponse.json(restored),
      ),
    );

    const { result } = renderHook(() => useRestoreComic(), {
      wrapper: createWrapper(),
    });

    await act(async () => {
      result.current.mutate({ id: 7 });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(result.current.data?.title).toBe("Restored");
  });

  it("returns error on failed PUT", async () => {
    server.use(
      http.put("/api/comic_series/7/restore", () =>
        HttpResponse.json({ detail: "Cannot restore" }, { status: 400 }),
      ),
    );

    const { result } = renderHook(() => useRestoreComic(), {
      wrapper: createWrapper(),
    });

    await act(async () => {
      result.current.mutate({ id: 7 });
    });

    await waitFor(() => expect(result.current.isError).toBe(true));
  });

  it("invalidates trash and comics queries on success", async () => {
    const queryClient = createTestQueryClient();

    queryClient.setQueryData(
      queryKeys.trash.all,
      createMockHydraCollection([], "/api/trash"),
    );
    queryClient.setQueryData(
      queryKeys.comics.all,
      createMockHydraCollection([]),
    );

    server.use(
      http.put("/api/comic_series/7/restore", () =>
        HttpResponse.json(createMockComicSeries({ id: 7 })),
      ),
    );

    const { result } = renderHook(() => useRestoreComic(), {
      wrapper: createWrapper(queryClient),
    });

    await act(async () => {
      result.current.mutate({ id: 7 });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(queryClient.getQueryState(queryKeys.trash.all)?.isInvalidated).toBe(
      true,
    );
    expect(queryClient.getQueryState(queryKeys.comics.all)?.isInvalidated).toBe(
      true,
    );
  });
});

describe("usePermanentDelete", () => {
  beforeEach(() => {
    localStorage.clear();
    Object.defineProperty(navigator, "onLine", {
      configurable: true,
      value: true,
      writable: true,
    });
  });

  it("permanently deletes a comic via API", async () => {
    server.use(
      http.delete(
        "/api/trash/9/permanent",
        () => new HttpResponse(null, { status: 204 }),
      ),
    );

    const { result } = renderHook(() => usePermanentDelete(), {
      wrapper: createWrapper(),
    });

    await act(async () => {
      result.current.mutate({ id: 9 });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
  });

  it("invalidates trash queries on success", async () => {
    const queryClient = createTestQueryClient();

    queryClient.setQueryData(
      queryKeys.trash.all,
      createMockHydraCollection([], "/api/trash"),
    );

    server.use(
      http.delete(
        "/api/trash/9/permanent",
        () => new HttpResponse(null, { status: 204 }),
      ),
    );

    const { result } = renderHook(() => usePermanentDelete(), {
      wrapper: createWrapper(queryClient),
    });

    await act(async () => {
      result.current.mutate({ id: 9 });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(queryClient.getQueryState(queryKeys.trash.all)?.isInvalidated).toBe(
      true,
    );
  });

  it("returns error on failed DELETE", async () => {
    server.use(
      http.delete("/api/trash/9/permanent", () =>
        HttpResponse.json({ detail: "Cannot delete" }, { status: 500 }),
      ),
    );

    const { result } = renderHook(() => usePermanentDelete(), {
      wrapper: createWrapper(),
    });

    await act(async () => {
      result.current.mutate({ id: 9 });
    });

    await waitFor(() => expect(result.current.isError).toBe(true));
  });
});

describe("useRestoreComic — offline", () => {
  beforeEach(() => {
    localStorage.clear();
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
  });

  afterEach(() => {
    Object.defineProperty(navigator, "onLine", {
      configurable: true,
      value: true,
      writable: true,
    });
  });

  it("enqueues restore mutation with correct resourceId when offline", async () => {
    const { result } = renderHook(() => useRestoreComic(), {
      wrapper: createWrapper(),
    });

    await act(async () => {
      result.current.mutate({ id: 7 });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(enqueue).toHaveBeenCalledWith(
      expect.objectContaining({
        operation: "update",
        resourceId: "7",
        resourceType: "comic_series",
      }),
    );
  });
});

describe("usePermanentDelete — offline", () => {
  beforeEach(() => {
    localStorage.clear();
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
  });

  afterEach(() => {
    Object.defineProperty(navigator, "onLine", {
      configurable: true,
      value: true,
      writable: true,
    });
  });

  it("enqueues permanent delete mutation with correct resourceId when offline", async () => {
    const { result } = renderHook(() => usePermanentDelete(), {
      wrapper: createWrapper(),
    });

    await act(async () => {
      result.current.mutate({ id: 9 });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(enqueue).toHaveBeenCalledWith(
      expect.objectContaining({
        operation: "delete",
        resourceId: "9",
        resourceType: "comic_series",
      }),
    );
  });
});
