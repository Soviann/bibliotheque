import { QueryClientProvider } from "@tanstack/react-query";
import { act, renderHook, waitFor } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import type { ReactNode } from "react";
import { MemoryRouter } from "react-router-dom";
import { useCreateTome } from "../../../hooks/useCreateTome";
import { queryKeys } from "../../../queryKeys";
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

describe("useCreateTome", () => {
  beforeEach(() => {
    localStorage.clear();
    Object.defineProperty(navigator, "onLine", {
      configurable: true,
      value: true,
      writable: true,
    });
  });

  it("creates a tome via API", async () => {
    const created = createMockTome({ id: 1, number: 1 });

    server.use(
      http.post("/api/comic_series/5/tomes", () =>
        HttpResponse.json(created, { status: 201 }),
      ),
    );

    const { result } = renderHook(() => useCreateTome(5), {
      wrapper: createWrapper(),
    });

    await act(async () => {
      result.current.mutate({ number: 1 });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
  });

  it("optimistically inserts tome into cache when offline", async () => {
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
    const series = createMockComicSeries({ id: 5, tomes: [] });
    queryClient.setQueryData(queryKeys.comics.detail(5), series);

    const { result } = renderHook(() => useCreateTome(5), {
      wrapper: createWrapper(queryClient),
    });

    await act(async () => {
      result.current.mutate({ number: 3 });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    const cached = queryClient.getQueryData<typeof series>(queryKeys.comics.detail(5));
    expect(cached?.tomes).toHaveLength(1);
    expect(cached?.tomes[0].number).toBe(3);
    expect(cached?.tomes[0]._syncPending).toBe(true);
  });
});
