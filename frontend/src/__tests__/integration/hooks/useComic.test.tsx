import { QueryClientProvider } from "@tanstack/react-query";
import { renderHook, waitFor } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import type { ReactNode } from "react";
import { MemoryRouter } from "react-router-dom";
import { useComic } from "../../../hooks/useComic";
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

describe("useComic", () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it("returns loading state initially", () => {
    const { result } = renderHook(() => useComic(1), {
      wrapper: createWrapper(),
    });

    expect(result.current.isLoading).toBe(true);
  });

  it("returns single comic series by ID", async () => {
    const comic = createMockComicSeries({ id: 5, title: "Bleach" });

    server.use(
      http.get("/api/comic_series/5", () => HttpResponse.json(comic)),
    );

    const { result } = renderHook(() => useComic(5), {
      wrapper: createWrapper(),
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(result.current.data?.id).toBe(5);
    expect(result.current.data?.title).toBe("Bleach");
  });

  it("is disabled when id is undefined", () => {
    const { result } = renderHook(() => useComic(undefined), {
      wrapper: createWrapper(),
    });

    expect(result.current.fetchStatus).toBe("idle");
  });

  it("returns error state for non-existent comic", async () => {
    server.use(
      http.get("/api/comic_series/999", () =>
        HttpResponse.json({ detail: "Not Found" }, { status: 404 }),
      ),
    );

    const { result } = renderHook(() => useComic(999), {
      wrapper: createWrapper(),
    });

    await waitFor(() => expect(result.current.isError).toBe(true));

    expect(result.current.error?.message).toBe("Not Found");
  });

  it("uses initial data from comics collection cache", async () => {
    const comic = createMockComicSeries({ id: 10, title: "AoT" });
    const queryClient = createTestQueryClient();

    // Pre-populate comics collection
    queryClient.setQueryData(
      queryKeys.comics.all,
      createMockHydraCollection([comic]),
    );

    const { result } = renderHook(() => useComic(10), {
      wrapper: createWrapper(queryClient),
    });

    // Should immediately have data from cache
    expect(result.current.data?.title).toBe("AoT");
  });

  it("fetches from API when comics cache does not contain target ID", async () => {
    const otherComic = createMockComicSeries({ id: 20, title: "Other" });
    const targetComic = createMockComicSeries({ id: 30, title: "Target" });
    const queryClient = createTestQueryClient();

    // Pre-populate comics collection with a different comic
    queryClient.setQueryData(
      queryKeys.comics.all,
      createMockHydraCollection([otherComic]),
    );

    server.use(
      http.get("/api/comic_series/30", () => HttpResponse.json(targetComic)),
    );

    const { result } = renderHook(() => useComic(30), {
      wrapper: createWrapper(queryClient),
    });

    // initialData should be undefined since ID 30 is not in cache
    expect(result.current.data).toBeUndefined();

    // Should fetch from API
    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(result.current.data?.title).toBe("Target");
  });
});
