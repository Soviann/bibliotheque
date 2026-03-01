import { QueryClientProvider } from "@tanstack/react-query";
import { renderHook, waitFor } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import type { ReactNode } from "react";
import { MemoryRouter } from "react-router-dom";
import { useComics } from "../../../hooks/useComics";
import { createTestQueryClient } from "../../helpers/test-utils";
import {
  createMockComicSeries,
  createMockHydraCollection,
} from "../../helpers/factories";
import { server } from "../../helpers/server";

function createWrapper() {
  const queryClient = createTestQueryClient();
  return function Wrapper({ children }: { children: ReactNode }) {
    return (
      <QueryClientProvider client={queryClient}>
        <MemoryRouter>{children}</MemoryRouter>
      </QueryClientProvider>
    );
  };
}

describe("useComics", () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it("returns loading state initially", () => {
    const { result } = renderHook(() => useComics(), {
      wrapper: createWrapper(),
    });

    expect(result.current.isLoading).toBe(true);
    expect(result.current.data).toBeUndefined();
  });

  it("returns comic series list from API", async () => {
    const series1 = createMockComicSeries({ id: 1, title: "Naruto" });
    const series2 = createMockComicSeries({ id: 2, title: "One Piece" });

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection([series1, series2])),
      ),
    );

    const { result } = renderHook(() => useComics(), {
      wrapper: createWrapper(),
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(result.current.data?.member).toHaveLength(2);
    expect(result.current.data?.member[0].title).toBe("Naruto");
    expect(result.current.data?.member[1].title).toBe("One Piece");
    expect(result.current.data?.totalItems).toBe(2);
  });

  it("returns empty collection when no comics exist", async () => {
    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection([])),
      ),
    );

    const { result } = renderHook(() => useComics(), {
      wrapper: createWrapper(),
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(result.current.data?.member).toHaveLength(0);
    expect(result.current.data?.totalItems).toBe(0);
  });

  it("seeds individual comic cache entries", async () => {
    const series = createMockComicSeries({ id: 42, title: "Dragon Ball" });

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(createMockHydraCollection([series])),
      ),
    );

    const queryClient = createTestQueryClient();
    const wrapper = ({ children }: { children: ReactNode }) => (
      <QueryClientProvider client={queryClient}>
        <MemoryRouter>{children}</MemoryRouter>
      </QueryClientProvider>
    );

    const { result } = renderHook(() => useComics(), { wrapper });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    const cachedComic = queryClient.getQueryData(["comic", 42]);
    expect(cachedComic).toEqual(series);
  });
});
