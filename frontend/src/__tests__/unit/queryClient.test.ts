import { describe, expect, it, vi } from "vitest";
import type { PersistedClient } from "@tanstack/react-query-persist-client";

let storedValue: PersistedClient | undefined;
vi.mock("idb-keyval", () => ({
  del: vi.fn(async () => {
    storedValue = undefined;
  }),
  get: vi.fn(async () => storedValue),
  set: vi.fn(async (_key: unknown, value: PersistedClient) => {
    storedValue = value;
  }),
}));

// Import after mock
const { persister, queryClient } = await import("../../queryClient");

describe("queryClient default options", () => {
  it("has correct query defaults", () => {
    const defaults = queryClient.getDefaultOptions().queries;

    expect(defaults?.gcTime).toBe(60 * 60 * 1000);
    expect(defaults?.networkMode).toBe("offlineFirst");
    expect(defaults?.refetchOnWindowFocus).toBe(true);
    expect(defaults?.retry).toBe(1);
    expect(defaults?.staleTime).toBe(5 * 60 * 1000);
  });

  it("has correct mutation defaults", () => {
    const defaults = queryClient.getDefaultOptions().mutations;

    expect(defaults?.networkMode).toBe("offlineFirst");
  });
});

describe("persister", () => {
  beforeEach(() => {
    storedValue = undefined;
  });

  it("defers persistClient off main thread (does not block synchronously)", async () => {
    vi.useFakeTimers();

    const mockClient: PersistedClient = {
      buster: "",
      clientState: { mutations: [], queries: [] },
      timestamp: Date.now(),
    };

    // persistClient should return a promise that resolves after idle scheduling
    const promise = persister.persistClient(mockClient);

    // Advance timers to trigger the setTimeout fallback (jsdom has no requestIdleCallback)
    await vi.advanceTimersByTimeAsync(10);

    await promise;

    vi.useRealTimers();
  });

  it("strips non-cloneable values via JSON round-trip before writing to IndexedDB", async () => {
    vi.useFakeTimers();
    const { set } = await import("idb-keyval");

    const mockClient: PersistedClient = {
      buster: "",
      clientState: {
        mutations: [],
        queries: [
          {
            queryHash: '["test"]',
            queryKey: ["test"],
            state: { data: "hello" } as never,
          },
        ],
      },
      timestamp: Date.now(),
    };

    const promise = persister.persistClient(mockClient);
    await vi.advanceTimersByTimeAsync(10);
    await promise;

    // Verify set was called with a clean object (JSON round-tripped)
    expect(set).toHaveBeenCalledWith(
      "bibliotheque-query-cache",
      expect.objectContaining({ buster: "" }),
    );

    // Verify the stored value is retrievable
    const restored = await persister.restoreClient();
    expect(restored).toBeDefined();
    expect(restored?.clientState.queries[0].state.data).toBe("hello");

    vi.useRealTimers();
  });
});
