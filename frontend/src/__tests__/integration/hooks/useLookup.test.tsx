import { QueryClientProvider } from "@tanstack/react-query";
import { renderHook, waitFor } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import type { ReactNode } from "react";
import { MemoryRouter } from "react-router-dom";
import {
  fetchLookupIsbn,
  fetchLookupTitle,
  useLookupIsbn,
  useLookupTitle,
  useLookupTitleCandidates,
} from "../../../hooks/useLookup";
import { createTestQueryClient } from "../../helpers/test-utils";
import { createMockLookupResult } from "../../helpers/factories";
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

describe("useLookupIsbn", () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it("is disabled when isbn is shorter than 10 characters", () => {
    const { result } = renderHook(() => useLookupIsbn("123"), {
      wrapper: createWrapper(),
    });

    expect(result.current.fetchStatus).toBe("idle");
  });

  it("returns loading state when isbn is valid", () => {
    const { result } = renderHook(() => useLookupIsbn("9781234567890"), {
      wrapper: createWrapper(),
    });

    expect(result.current.isLoading).toBe(true);
  });

  it("returns lookup result by ISBN", async () => {
    const lookupResult = createMockLookupResult({
      authors: "Eiichiro Oda",
      isbn: "9781234567890",
      publisher: "Glenat",
      title: "One Piece",
    });

    server.use(
      http.get("/api/lookup/isbn", () => HttpResponse.json(lookupResult)),
    );

    const { result } = renderHook(() => useLookupIsbn("9781234567890"), {
      wrapper: createWrapper(),
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(result.current.data?.title).toBe("One Piece");
    expect(result.current.data?.authors).toBe("Eiichiro Oda");
    expect(result.current.data?.publisher).toBe("Glenat");
  });

  it("handles error state", async () => {
    server.use(
      http.get("/api/lookup/isbn", () =>
        HttpResponse.json({ detail: "ISBN not found" }, { status: 404 }),
      ),
    );

    const { result } = renderHook(() => useLookupIsbn("9781234567890"), {
      wrapper: createWrapper(),
    });

    await waitFor(() => expect(result.current.isError).toBe(true));

    expect(result.current.error?.message).toBe("ISBN not found");
  });

  it("includes type query parameter in request URL", async () => {
    let capturedUrl = "";

    server.use(
      http.get("/api/lookup/isbn", ({ request }) => {
        capturedUrl = new URL(request.url).search;
        return HttpResponse.json(createMockLookupResult({ title: "Test" }));
      }),
    );

    const { result } = renderHook(() => useLookupIsbn("9781234567890", "manga"), {
      wrapper: createWrapper(),
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(capturedUrl).toContain("type=manga");
  });

  it("accepts ISBN with exactly 10 characters", async () => {
    server.use(
      http.get("/api/lookup/isbn", () =>
        HttpResponse.json(createMockLookupResult({ title: "Short ISBN" })),
      ),
    );

    const { result } = renderHook(() => useLookupIsbn("1234567890"), {
      wrapper: createWrapper(),
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(result.current.data?.title).toBe("Short ISBN");
  });
});

describe("useLookupIsbn — without type parameter", () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it("does not include type parameter in URL when type is not provided", async () => {
    let capturedUrl = "";

    server.use(
      http.get("/api/lookup/isbn", ({ request }) => {
        capturedUrl = new URL(request.url).search;
        return HttpResponse.json(createMockLookupResult({ title: "No Type" }));
      }),
    );

    const { result } = renderHook(() => useLookupIsbn("9781234567890"), {
      wrapper: createWrapper(),
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(capturedUrl).not.toContain("type=");
  });
});

describe("useLookupTitle", () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it("is disabled when title is shorter than 2 characters", () => {
    const { result } = renderHook(() => useLookupTitle("A"), {
      wrapper: createWrapper(),
    });

    expect(result.current.fetchStatus).toBe("idle");
  });

  it("returns lookup result by title", async () => {
    const lookupResult = createMockLookupResult({
      authors: "Hajime Isayama",
      title: "Attack on Titan",
    });

    server.use(
      http.get("/api/lookup/title", () => HttpResponse.json(lookupResult)),
    );

    const { result } = renderHook(() => useLookupTitle("Attack"), {
      wrapper: createWrapper(),
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(result.current.data?.title).toBe("Attack on Titan");
    expect(result.current.data?.authors).toBe("Hajime Isayama");
  });

  it("handles error state", async () => {
    server.use(
      http.get("/api/lookup/title", () =>
        HttpResponse.json({ detail: "Server error" }, { status: 500 }),
      ),
    );

    const { result } = renderHook(() => useLookupTitle("Test"), {
      wrapper: createWrapper(),
    });

    await waitFor(() => expect(result.current.isError).toBe(true));

    expect(result.current.error?.message).toBe("Server error");
  });

  it("includes type parameter in URL when type is provided", async () => {
    let capturedUrl = "";

    server.use(
      http.get("/api/lookup/title", ({ request }) => {
        capturedUrl = new URL(request.url).search;
        return HttpResponse.json(createMockLookupResult({ title: "Naruto" }));
      }),
    );

    const { result } = renderHook(() => useLookupTitle("Naruto", "manga"), {
      wrapper: createWrapper(),
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(capturedUrl).toContain("type=manga");
  });
});

describe("useLookupTitleCandidates", () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it("is disabled when title is shorter than 2 characters", () => {
    const { result } = renderHook(() => useLookupTitleCandidates("A"), {
      wrapper: createWrapper(),
    });

    expect(result.current.fetchStatus).toBe("idle");
  });

  it("returns multiple candidates", async () => {
    const candidatesResponse = {
      apiMessages: {},
      results: [
        { authors: "Oda", description: null, isbn: null, isOneShot: false, latestPublishedIssue: null, publishedDate: null, publisher: "Glenat", thumbnail: null, title: "One Piece", tomeEnd: null, tomeNumber: null },
        { authors: "Oda", description: null, isbn: null, isOneShot: null, latestPublishedIssue: null, publishedDate: null, publisher: null, thumbnail: null, title: "One Piece Party", tomeEnd: null, tomeNumber: null },
      ],
      sources: ["google_books"],
    };

    server.use(
      http.get("/api/lookup/title", ({ request }) => {
        const url = new URL(request.url);
        if (url.searchParams.get("limit")) {
          return HttpResponse.json(candidatesResponse);
        }
        return HttpResponse.json(createMockLookupResult({ title: "One Piece" }));
      }),
    );

    const { result } = renderHook(() => useLookupTitleCandidates("One Piece"), {
      wrapper: createWrapper(),
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(result.current.data?.results).toHaveLength(2);
    expect(result.current.data?.results[0].title).toBe("One Piece");
    expect(result.current.data?.results[1].title).toBe("One Piece Party");
  });

  it("includes limit parameter in URL", async () => {
    let capturedUrl = "";

    server.use(
      http.get("/api/lookup/title", ({ request }) => {
        capturedUrl = new URL(request.url).search;
        return HttpResponse.json({ apiMessages: {}, results: [], sources: [] });
      }),
    );

    const { result } = renderHook(() => useLookupTitleCandidates("Test", "manga", 3), {
      wrapper: createWrapper(),
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(capturedUrl).toContain("limit=3");
    expect(capturedUrl).toContain("type=manga");
  });
});

describe("fetchLookupIsbn", () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it("returns lookup result imperatively by ISBN", async () => {
    const lookupResult = createMockLookupResult({
      isbn: "9781234567890",
      title: "Imperative ISBN",
    });

    server.use(
      http.get("/api/lookup/isbn", () => HttpResponse.json(lookupResult)),
    );

    const result = await fetchLookupIsbn("9781234567890");

    expect(result.title).toBe("Imperative ISBN");
  });
});

describe("fetchLookupTitle", () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it("returns lookup result imperatively by title", async () => {
    const lookupResult = createMockLookupResult({
      title: "Imperative Title",
    });

    server.use(
      http.get("/api/lookup/title", () => HttpResponse.json(lookupResult)),
    );

    const result = await fetchLookupTitle("Imperative");

    expect(result.title).toBe("Imperative Title");
  });
});
