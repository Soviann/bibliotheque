import { QueryClientProvider } from "@tanstack/react-query";
import { renderHook, waitFor } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import type { ReactNode } from "react";
import { MemoryRouter } from "react-router-dom";
import { useCoverSearch } from "../../../hooks/useCoverSearch";
import type { CoverSearchResult } from "../../../types/api";
import { createTestQueryClient } from "../../helpers/test-utils";
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

const mockResults: CoverSearchResult[] = [
  {
    height: 800,
    thumbnail: "https://example.com/thumb1.jpg",
    title: "Naruto Vol. 1",
    url: "https://example.com/cover1.jpg",
    width: 600,
  },
  {
    height: 900,
    thumbnail: "https://example.com/thumb2.jpg",
    title: "Naruto Vol. 2",
    url: "https://example.com/cover2.jpg",
    width: 650,
  },
];

describe("useCoverSearch", () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it("is disabled when query is shorter than 2 characters", () => {
    const { result } = renderHook(() => useCoverSearch("A"), {
      wrapper: createWrapper(),
    });

    expect(result.current.fetchStatus).toBe("idle");
  });

  it("returns cover search results", async () => {
    server.use(
      http.get("/api/lookup/covers", () => HttpResponse.json(mockResults)),
    );

    const { result } = renderHook(() => useCoverSearch("Naruto"), {
      wrapper: createWrapper(),
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(result.current.data).toHaveLength(2);
    expect(result.current.data?.[0].url).toBe("https://example.com/cover1.jpg");
    expect(result.current.data?.[0].title).toBe("Naruto Vol. 1");
  });

  it("includes type parameter in request URL", async () => {
    let capturedUrl = "";

    server.use(
      http.get("/api/lookup/covers", ({ request }) => {
        capturedUrl = new URL(request.url).search;
        return HttpResponse.json(mockResults);
      }),
    );

    const { result } = renderHook(() => useCoverSearch("Naruto", "manga"), {
      wrapper: createWrapper(),
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(capturedUrl).toContain("type=manga");
  });

  it("handles error state", async () => {
    server.use(
      http.get("/api/lookup/covers", () =>
        HttpResponse.json({ detail: "Server error" }, { status: 500 }),
      ),
    );

    const { result } = renderHook(() => useCoverSearch("Test"), {
      wrapper: createWrapper(),
    });

    await waitFor(() => expect(result.current.isError).toBe(true));
  });
});
