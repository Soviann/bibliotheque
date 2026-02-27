import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { renderHook, waitFor } from "@testing-library/react";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { fetchLookupIsbn, fetchLookupTitle, useLookupIsbn, useLookupTitle } from "../../hooks/useLookup";
import type { LookupResult } from "../../types/api";

const mockApiFetch = vi.fn();
vi.mock("../../services/api", () => ({
  apiFetch: (...args: unknown[]) => mockApiFetch(...args),
}));

const fakeLookup: LookupResult = {
  apiMessages: [],
  authors: "Cailleteau, Vatine",
  description: "Une saga de science-fiction",
  isbn: "9782756001340",
  isOneShot: false,
  latestPublishedIssue: null,
  publishedDate: null,
  publisher: "Delcourt",
  sources: ["google_books"],
  thumbnail: "https://example.com/cover.jpg",
  title: "Aquablue",
};

function createWrapper() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { gcTime: Infinity, retry: false },
    },
  });
  return ({ children }: { children: ReactNode }) =>
    QueryClientProvider({ client: queryClient, children });
}

describe("useLookupIsbn", () => {
  beforeEach(() => {
    mockApiFetch.mockReset();
  });

  it("is disabled when ISBN is too short", () => {
    const { result } = renderHook(() => useLookupIsbn("123", "bd"), {
      wrapper: createWrapper(),
    });

    expect(result.current.isFetching).toBe(false);
    expect(mockApiFetch).not.toHaveBeenCalled();
  });

  it("is disabled when ISBN is empty", () => {
    const { result } = renderHook(() => useLookupIsbn("", "bd"), {
      wrapper: createWrapper(),
    });

    expect(result.current.isFetching).toBe(false);
    expect(mockApiFetch).not.toHaveBeenCalled();
  });

  it("fetches when ISBN has 10+ characters", async () => {
    mockApiFetch.mockResolvedValue(fakeLookup);

    const { result } = renderHook(() => useLookupIsbn("9782756001340", "bd"), {
      wrapper: createWrapper(),
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(mockApiFetch).toHaveBeenCalledWith(
      "/lookup/isbn?isbn=9782756001340&type=bd",
    );
    expect(result.current.data).toEqual(fakeLookup);
    expect(result.current.data?.authors).toBe("Cailleteau, Vatine");
    expect(typeof result.current.data?.authors).toBe("string");
  });

  it("includes type param in query", async () => {
    mockApiFetch.mockResolvedValue(fakeLookup);

    const { result } = renderHook(
      () => useLookupIsbn("9782756001340", "manga"),
      { wrapper: createWrapper() },
    );

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(mockApiFetch).toHaveBeenCalledWith(
      "/lookup/isbn?isbn=9782756001340&type=manga",
    );
  });

  it("omits type param when not provided", async () => {
    mockApiFetch.mockResolvedValue(fakeLookup);

    const { result } = renderHook(() => useLookupIsbn("9782756001340"), {
      wrapper: createWrapper(),
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(mockApiFetch).toHaveBeenCalledWith(
      "/lookup/isbn?isbn=9782756001340",
    );
  });
});

describe("useLookupTitle", () => {
  beforeEach(() => {
    mockApiFetch.mockReset();
  });

  it("is disabled when title is too short", () => {
    const { result } = renderHook(() => useLookupTitle("A", "bd"), {
      wrapper: createWrapper(),
    });

    expect(result.current.isFetching).toBe(false);
    expect(mockApiFetch).not.toHaveBeenCalled();
  });

  it("fetches when title has 2+ characters", async () => {
    mockApiFetch.mockResolvedValue(fakeLookup);

    const { result } = renderHook(() => useLookupTitle("Aquablue", "bd"), {
      wrapper: createWrapper(),
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(mockApiFetch).toHaveBeenCalledWith(
      "/lookup/title?title=Aquablue&type=bd",
    );
    expect(result.current.data?.title).toBe("Aquablue");
    expect(result.current.data?.authors).toBe("Cailleteau, Vatine");
  });

  it("handles null authors gracefully", async () => {
    mockApiFetch.mockResolvedValue({ ...fakeLookup, authors: null });

    const { result } = renderHook(() => useLookupTitle("Aquablue", "bd"), {
      wrapper: createWrapper(),
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(result.current.data?.authors).toBeNull();
  });

  it("handles null title gracefully", async () => {
    mockApiFetch.mockResolvedValue({ ...fakeLookup, title: null });

    const { result } = renderHook(() => useLookupTitle("Aquablue", "bd"), {
      wrapper: createWrapper(),
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(result.current.data?.title).toBeNull();
  });
});

describe("fetchLookupIsbn", () => {
  beforeEach(() => {
    mockApiFetch.mockReset();
  });

  it("calls apiFetch with correct URL and returns result", async () => {
    mockApiFetch.mockResolvedValue(fakeLookup);

    const result = await fetchLookupIsbn("9782756001340", "bd");

    expect(mockApiFetch).toHaveBeenCalledWith("/lookup/isbn?isbn=9782756001340&type=bd");
    expect(result).toEqual(fakeLookup);
  });

  it("omits type param when not provided", async () => {
    mockApiFetch.mockResolvedValue(fakeLookup);

    await fetchLookupIsbn("9782756001340");

    expect(mockApiFetch).toHaveBeenCalledWith("/lookup/isbn?isbn=9782756001340");
  });
});

describe("fetchLookupTitle", () => {
  beforeEach(() => {
    mockApiFetch.mockReset();
  });

  it("calls apiFetch with correct URL and returns result", async () => {
    mockApiFetch.mockResolvedValue(fakeLookup);

    const result = await fetchLookupTitle("Aquablue", "manga");

    expect(mockApiFetch).toHaveBeenCalledWith("/lookup/title?title=Aquablue&type=manga");
    expect(result).toEqual(fakeLookup);
  });

  it("omits type param when not provided", async () => {
    mockApiFetch.mockResolvedValue(fakeLookup);

    await fetchLookupTitle("Aquablue");

    expect(mockApiFetch).toHaveBeenCalledWith("/lookup/title?title=Aquablue");
  });
});
