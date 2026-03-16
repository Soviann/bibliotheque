import { QueryClientProvider } from "@tanstack/react-query";
import { renderHook, waitFor, act } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import type { ReactNode } from "react";
import { MemoryRouter } from "react-router-dom";
import { useCreateComic } from "../../../hooks/useCreateComic";
import { queryKeys } from "../../../queryKeys";
import { createTestQueryClient } from "../../helpers/test-utils";
import {
  createMockComicSeries,
  createMockHydraCollection,
} from "../../helpers/factories";
import { server } from "../../helpers/server";

function createWrapper(queryClient = createTestQueryClient()) {
  return function Wrapper({ children }: { children: ReactNode }) {
    return (
      <QueryClientProvider client={queryClient}>
        <MemoryRouter>{children}</MemoryRouter>
      </QueryClientProvider>
    );
  };
}

describe("useCreateComic", () => {
  beforeEach(() => {
    localStorage.clear();
    Object.defineProperty(navigator, "onLine", {
      configurable: true,
      value: true,
      writable: true,
    });
  });

  it("creates comic series via API", async () => {
    const created = createMockComicSeries({ id: 1, title: "New Series" });

    server.use(
      http.post("/api/comic_series", () =>
        HttpResponse.json(created, { status: 201 }),
      ),
    );

    const { result } = renderHook(() => useCreateComic(), {
      wrapper: createWrapper(),
    });

    await act(async () => {
      result.current.mutate({ title: "New Series" });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(result.current.data?.title).toBe("New Series");
  });

  it("invalidates comics queries on success", async () => {
    const queryClient = createTestQueryClient();

    // Pre-populate cache
    queryClient.setQueryData(queryKeys.comics.all, createMockHydraCollection([]));

    server.use(
      http.post("/api/comic_series", () =>
        HttpResponse.json(
          createMockComicSeries({ title: "New" }),
          { status: 201 },
        ),
      ),
    );

    const { result } = renderHook(() => useCreateComic(), {
      wrapper: createWrapper(queryClient),
    });

    await act(async () => {
      result.current.mutate({ title: "New" });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    // Cache should be invalidated
    const state = queryClient.getQueryState(queryKeys.comics.all);
    expect(state?.isInvalidated).toBe(true);
  });

  it("handles API error", async () => {
    server.use(
      http.post("/api/comic_series", () =>
        HttpResponse.json(
          { detail: "Validation Failed" },
          { status: 422 },
        ),
      ),
    );

    const { result } = renderHook(() => useCreateComic(), {
      wrapper: createWrapper(),
    });

    await act(async () => {
      result.current.mutate({ title: "" });
    });

    await waitFor(() => expect(result.current.isError).toBe(true));

    expect(result.current.error?.message).toBe("Validation Failed");
  });
});
