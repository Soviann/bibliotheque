import { QueryClientProvider } from "@tanstack/react-query";
import { renderHook, waitFor, act } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import type { ReactNode } from "react";
import { MemoryRouter } from "react-router-dom";
import { useDeleteComic } from "../../../hooks/useDeleteComic";
import { createTestQueryClient } from "../../helpers/test-utils";
import { createMockHydraCollection } from "../../helpers/factories";
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

describe("useDeleteComic", () => {
  beforeEach(() => {
    localStorage.clear();
    Object.defineProperty(navigator, "onLine", {
      configurable: true,
      value: true,
      writable: true,
    });
  });

  it("deletes comic series via API", async () => {
    server.use(
      http.delete("/api/comic_series/5", () =>
        new HttpResponse(null, { status: 204 }),
      ),
    );

    const { result } = renderHook(() => useDeleteComic(), {
      wrapper: createWrapper(),
    });

    await act(async () => {
      result.current.mutate({ id: 5 });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
  });

  it("invalidates comics and trash queries on success", async () => {
    const queryClient = createTestQueryClient();

    queryClient.setQueryData(["comics"], createMockHydraCollection([]));
    queryClient.setQueryData(
      ["trash"],
      createMockHydraCollection([], "/api/trash"),
    );

    server.use(
      http.delete("/api/comic_series/5", () =>
        new HttpResponse(null, { status: 204 }),
      ),
    );

    const { result } = renderHook(() => useDeleteComic(), {
      wrapper: createWrapper(queryClient),
    });

    await act(async () => {
      result.current.mutate({ id: 5 });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(queryClient.getQueryState(["comics"])?.isInvalidated).toBe(true);
    expect(queryClient.getQueryState(["trash"])?.isInvalidated).toBe(true);
  });

  it("handles API error", async () => {
    server.use(
      http.delete("/api/comic_series/5", () =>
        HttpResponse.json({ detail: "Forbidden" }, { status: 403 }),
      ),
    );

    const { result } = renderHook(() => useDeleteComic(), {
      wrapper: createWrapper(),
    });

    await act(async () => {
      result.current.mutate({ id: 5 });
    });

    await waitFor(() => expect(result.current.isError).toBe(true));

    expect(result.current.error?.message).toBe("Forbidden");
  });
});
