import { QueryClientProvider } from "@tanstack/react-query";
import { act, renderHook, waitFor } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import type { ReactNode } from "react";
import { MemoryRouter } from "react-router-dom";
import { useDeleteTome } from "../../../hooks/useDeleteTome";
import { createTestQueryClient } from "../../helpers/test-utils";
import { createMockComicSeries, createMockTome } from "../../helpers/factories";
import { server } from "../../helpers/server";

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

describe("useDeleteTome", () => {
  beforeEach(() => {
    localStorage.clear();
    Object.defineProperty(navigator, "onLine", {
      configurable: true,
      value: true,
      writable: true,
    });
  });

  it("deletes a tome via API", async () => {
    server.use(
      http.delete("/api/tomes/10", () =>
        new HttpResponse(null, { status: 204 }),
      ),
    );

    const { result } = renderHook(() => useDeleteTome(5), {
      wrapper: createWrapper(),
    });

    await act(async () => {
      result.current.mutate({ id: 10 });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
  });

  it("optimistically removes tome from cache when offline", async () => {
    Object.defineProperty(navigator, "onLine", {
      configurable: true,
      value: false,
      writable: true,
    });

    Object.defineProperty(navigator, "serviceWorker", {
      configurable: true,
      value: { ready: Promise.resolve({ sync: { register: vi.fn() } }) },
      writable: true,
    });

    const queryClient = createTestQueryClient();
    const tome = createMockTome({ id: 10, number: 1 });
    const series = createMockComicSeries({ id: 5, tomes: [tome] });
    queryClient.setQueryData(["comic", 5], series);

    const { result } = renderHook(() => useDeleteTome(5), {
      wrapper: createWrapper(queryClient),
    });

    await act(async () => {
      result.current.mutate({ id: 10 });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    const cached = queryClient.getQueryData<typeof series>(["comic", 5]);
    expect(cached?.tomes).toHaveLength(0);
  });
});
