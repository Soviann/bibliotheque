import { QueryClientProvider } from "@tanstack/react-query";
import { renderHook, waitFor } from "@testing-library/react";
import type { ReactNode } from "react";
import { MemoryRouter } from "react-router-dom";
import { usePendingQueueCount } from "../../../hooks/usePendingQueueCount";
import { createTestQueryClient } from "../../helpers/test-utils";

// Mock offlineQueue module
vi.mock("../../../services/offlineQueue", () => ({
  getPendingCount: vi.fn().mockResolvedValue(0),
}));

import { getPendingCount } from "../../../services/offlineQueue";

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

describe("usePendingQueueCount", () => {
  beforeEach(() => {
    localStorage.clear();
    vi.mocked(getPendingCount).mockReset();
  });

  it("returns 0 when queue is empty", async () => {
    vi.mocked(getPendingCount).mockResolvedValue(0);

    const { result } = renderHook(() => usePendingQueueCount(), {
      wrapper: createWrapper(),
    });

    await waitFor(() => expect(result.current).toBe(0));
  });

  it("returns count of pending mutations", async () => {
    vi.mocked(getPendingCount).mockResolvedValue(3);

    const { result } = renderHook(() => usePendingQueueCount(), {
      wrapper: createWrapper(),
    });

    await waitFor(() => expect(result.current).toBe(3));
  });

  it("returns 0 when getPendingCount rejects (IndexedDB failure)", async () => {
    vi.mocked(getPendingCount).mockRejectedValue(
      new Error("IndexedDB unavailable"),
    );

    const { result } = renderHook(() => usePendingQueueCount(), {
      wrapper: createWrapper(),
    });

    // Should fall back to 0 (the default)
    await waitFor(() => expect(result.current).toBe(0));
  });

  it("returns 0 as default before data loads", () => {
    vi.mocked(getPendingCount).mockReturnValue(new Promise(() => {})); // never resolves

    const { result } = renderHook(() => usePendingQueueCount(), {
      wrapper: createWrapper(),
    });

    expect(result.current).toBe(0);
  });

  it("stops polling when online and queue is empty", async () => {
    vi.mocked(getPendingCount).mockResolvedValue(0);

    renderHook(() => usePendingQueueCount(), {
      wrapper: createWrapper(),
    });

    // Wait for initial fetch
    await waitFor(() => expect(getPendingCount).toHaveBeenCalledTimes(1));

    // Record call count after initial, then wait 3s (more than the 2s interval)
    vi.mocked(getPendingCount).mockClear();
    await new Promise((r) => setTimeout(r, 3000));

    // Should NOT have been called again since online + count=0
    expect(getPendingCount).not.toHaveBeenCalled();
  }, 10_000);
});
