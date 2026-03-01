import { QueryClientProvider } from "@tanstack/react-query";
import { renderHook, waitFor, act } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import type { ReactNode } from "react";
import { MemoryRouter } from "react-router-dom";
import { useUpdateComic } from "../../../hooks/useUpdateComic";
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
      http.put("/api/comic_series/3", () => HttpResponse.json(updated)),
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

  it("invalidates comics and comic queries on success", async () => {
    const queryClient = createTestQueryClient();

    queryClient.setQueryData(["comics"], createMockHydraCollection([]));
    queryClient.setQueryData(
      ["comic", 3],
      createMockComicSeries({ id: 3, title: "Old" }),
    );

    server.use(
      http.put("/api/comic_series/3", () =>
        HttpResponse.json(
          createMockComicSeries({ id: 3, title: "New" }),
        ),
      ),
    );

    const { result } = renderHook(() => useUpdateComic(), {
      wrapper: createWrapper(queryClient),
    });

    await act(async () => {
      result.current.mutate({ id: 3, title: "New" });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(queryClient.getQueryState(["comics"])?.isInvalidated).toBe(true);
    expect(queryClient.getQueryState(["comic", 3])?.isInvalidated).toBe(true);
  });

  it("handles API error", async () => {
    server.use(
      http.put("/api/comic_series/3", () =>
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
});
