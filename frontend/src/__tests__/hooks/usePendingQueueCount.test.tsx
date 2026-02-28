import "fake-indexeddb/auto";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { renderHook, waitFor } from "@testing-library/react";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it } from "vitest";
import { usePendingQueueCount } from "../../hooks/usePendingQueueCount";
import { enqueue, _resetDb } from "../../services/offlineQueue";

function createWrapper() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
  );
}

describe("usePendingQueueCount", () => {
  beforeEach(async () => {
    await _resetDb();
    const dbs = await indexedDB.databases();
    for (const db of dbs) {
      if (db.name) indexedDB.deleteDatabase(db.name);
    }
  });

  it("returns 0 when queue is empty", async () => {
    const { result } = renderHook(() => usePendingQueueCount(), {
      wrapper: createWrapper(),
    });

    await waitFor(() => {
      expect(result.current).toBe(0);
    });
  });

  it("returns count of pending items", async () => {
    await enqueue({ operation: "create", payload: {}, resourceType: "comic_series" });
    await enqueue({ operation: "update", payload: {}, resourceId: "1", resourceType: "comic_series" });

    const { result } = renderHook(() => usePendingQueueCount(), {
      wrapper: createWrapper(),
    });

    await waitFor(() => {
      expect(result.current).toBe(2);
    });
  });
});
