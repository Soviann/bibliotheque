import { QueryClientProvider } from "@tanstack/react-query";
import { act, renderHook, waitFor } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import type { ReactNode } from "react";
import { MemoryRouter } from "react-router-dom";
import { useUpdateTome } from "../../../hooks/useUpdateTome";
import { queryKeys } from "../../../queryKeys";
import { enqueue } from "../../../services/offlineQueue";
import { createTestQueryClient } from "../../helpers/test-utils";
import { createMockComicSeries, createMockTome } from "../../helpers/factories";
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

describe("useUpdateTome", () => {
  beforeEach(() => {
    localStorage.clear();
    Object.defineProperty(navigator, "onLine", {
      configurable: true,
      value: true,
      writable: true,
    });
  });

  it("sends PATCH with merge-patch+json Content-Type", async () => {
    let capturedContentType: string | null = null;
    const updated = createMockTome({ bought: true, id: 5, number: 1 });

    server.use(
      http.patch("/api/tomes/5", ({ request }) => {
        capturedContentType = request.headers.get("Content-Type");
        return HttpResponse.json(updated);
      }),
    );

    const { result } = renderHook(() => useUpdateTome(), {
      wrapper: createWrapper(),
    });

    await act(async () => {
      result.current.mutate({ bought: true, id: 5 });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(result.current.data?.bought).toBe(true);
    expect(capturedContentType).toBe("application/merge-patch+json");
  });

  it("invalidates comic queries on success", async () => {
    const queryClient = createTestQueryClient();

    queryClient.setQueryData(
      queryKeys.comics.detail(1),
      createMockComicSeries({ id: 1, title: "Test" }),
    );

    server.use(
      http.patch("/api/tomes/5", () =>
        HttpResponse.json(createMockTome({ bought: true, id: 5 })),
      ),
    );

    const { result } = renderHook(() => useUpdateTome(), {
      wrapper: createWrapper(queryClient),
    });

    await act(async () => {
      result.current.mutate({ bought: true, id: 5 });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(
      queryClient.getQueryState(queryKeys.comics.detail(1))?.isInvalidated,
    ).toBe(true);
  });

  it("handles API error", async () => {
    server.use(
      http.patch("/api/tomes/5", () =>
        HttpResponse.json({ detail: "Not Found" }, { status: 404 }),
      ),
    );

    const { result } = renderHook(() => useUpdateTome(), {
      wrapper: createWrapper(),
    });

    await act(async () => {
      result.current.mutate({ id: 5, read: true });
    });

    await waitFor(() => expect(result.current.isError).toBe(true));

    expect(result.current.error?.message).toBe("Not Found");
  });

  it("enqueues mutation when offline", async () => {
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

    const { result } = renderHook(() => useUpdateTome(), {
      wrapper: createWrapper(),
    });

    await act(async () => {
      result.current.mutate({ id: 5, read: true });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(enqueue).toHaveBeenCalledWith(
      expect.objectContaining({
        operation: "update",
        resourceId: "5",
        resourceType: "tome",
      }),
    );
  });
});
