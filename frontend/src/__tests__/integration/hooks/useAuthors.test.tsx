import { QueryClientProvider } from "@tanstack/react-query";
import { renderHook, waitFor } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import type { ReactNode } from "react";
import { MemoryRouter } from "react-router-dom";
import { useAuthors } from "../../../hooks/useAuthors";
import { createTestQueryClient } from "../../helpers/test-utils";
import {
  createMockAuthor,
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

describe("useAuthors", () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it("is disabled when search is empty", () => {
    const { result } = renderHook(() => useAuthors(""), {
      wrapper: createWrapper(),
    });

    expect(result.current.fetchStatus).toBe("idle");
  });

  it("returns loading state while fetching", () => {
    const { result } = renderHook(() => useAuthors("A"), {
      wrapper: createWrapper(),
    });

    expect(result.current.isLoading).toBe(true);
  });

  it("returns authors list from API", async () => {
    const author1 = createMockAuthor({ id: 1, name: "Eiichiro Oda" });
    const author2 = createMockAuthor({ id: 2, name: "Eiichi Fukui" });

    server.use(
      http.get("/api/authors", () =>
        HttpResponse.json(
          createMockHydraCollection([author1, author2], "/api/authors"),
        ),
      ),
    );

    const { result } = renderHook(() => useAuthors("Eiichi"), {
      wrapper: createWrapper(),
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(result.current.data?.member).toHaveLength(2);
    expect(result.current.data?.member[0].name).toBe("Eiichiro Oda");
  });

  it("returns error state on failed request", async () => {
    server.use(
      http.get("/api/authors", () =>
        HttpResponse.json({ detail: "Server error" }, { status: 500 }),
      ),
    );

    const { result } = renderHook(() => useAuthors("test"), {
      wrapper: createWrapper(),
    });

    await waitFor(() => expect(result.current.isError).toBe(true));
  });

  it("is enabled when search has at least 1 character", async () => {
    server.use(
      http.get("/api/authors", () =>
        HttpResponse.json(
          createMockHydraCollection([], "/api/authors"),
        ),
      ),
    );

    const { result } = renderHook(() => useAuthors("A"), {
      wrapper: createWrapper(),
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(result.current.data?.member).toHaveLength(0);
  });
});
