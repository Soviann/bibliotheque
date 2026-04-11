import "fake-indexeddb/auto";
import { QueryClientProvider } from "@tanstack/react-query";
import { act, renderHook, waitFor } from "@testing-library/react";
import type { ReactNode } from "react";
import { MemoryRouter } from "react-router-dom";
import { useSyncFailures } from "../../../hooks/useSyncFailures";
import { _resetDb, addSyncFailure } from "../../../services/offlineQueue";
import { createTestQueryClient } from "../../helpers/test-utils";

function createWrapper(queryClient = createTestQueryClient()) {
  return function Wrapper({ children }: { children: ReactNode }) {
    return (
      <QueryClientProvider client={queryClient}>
        <MemoryRouter>{children}</MemoryRouter>
      </QueryClientProvider>
    );
  };
}

describe("useSyncFailures", () => {
  beforeEach(async () => {
    await _resetDb();
    await new Promise<void>((resolve, reject) => {
      const req = indexedDB.deleteDatabase("bibliotheque-offline");
      req.onsuccess = () => resolve();
      req.onerror = () => reject(req.error);
    });
  });

  it("returns empty array when no failures exist", async () => {
    const queryClient = createTestQueryClient();

    const { result } = renderHook(() => useSyncFailures(), {
      wrapper: createWrapper(queryClient),
    });

    await waitFor(() => {
      expect(result.current.failures).toEqual([]);
    });
  });

  it("returns unresolved failures", async () => {
    await addSyncFailure({
      error: "Validation failed",
      httpStatus: 422,
      operation: "create",
      payload: { title: "Bad" },
      resourceType: "comic_series",
    });

    const queryClient = createTestQueryClient();

    const { result } = renderHook(() => useSyncFailures(), {
      wrapper: createWrapper(queryClient),
    });

    await waitFor(() => {
      expect(result.current.failures).toHaveLength(1);
      expect(result.current.failures[0].error).toBe("Validation failed");
    });
  });

  it("stops polling when no failures exist", async () => {
    const queryClient = createTestQueryClient();

    const { result } = renderHook(() => useSyncFailures(), {
      wrapper: createWrapper(queryClient),
    });

    // Wait for the query to have fetched at least once
    await waitFor(() => {
      const queries = queryClient
        .getQueryCache()
        .findAll({ queryKey: ["syncFailures"] });
      expect(queries[0]?.state.dataUpdateCount).toBeGreaterThanOrEqual(1);
    });

    const query = queryClient
      .getQueryCache()
      .findAll({ queryKey: ["syncFailures"] })[0];
    const fetchCount = query.state.dataUpdateCount;

    // Wait longer than the 3s polling interval — no additional fetches should happen
    await new Promise((r) => setTimeout(r, 4000));

    expect(query.state.dataUpdateCount).toBe(fetchCount);
    expect(result.current.failures).toEqual([]);
  }, 10000);

  it("removes a failure", async () => {
    const id = await addSyncFailure({
      error: "Error",
      httpStatus: 400,
      operation: "delete",
      payload: {},
      resourceType: "tome",
    });

    const queryClient = createTestQueryClient();

    const { result } = renderHook(() => useSyncFailures(), {
      wrapper: createWrapper(queryClient),
    });

    await waitFor(() => {
      expect(result.current.failures).toHaveLength(1);
    });

    await act(async () => {
      await result.current.removeSyncFailure(id);
    });

    await waitFor(() => {
      expect(result.current.failures).toHaveLength(0);
    });
  });
});
