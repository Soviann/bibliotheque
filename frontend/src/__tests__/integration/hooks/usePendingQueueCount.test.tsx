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

  it("returns 0 as default before data loads", () => {
    vi.mocked(getPendingCount).mockReturnValue(new Promise(() => {})); // never resolves

    const { result } = renderHook(() => usePendingQueueCount(), {
      wrapper: createWrapper(),
    });

    expect(result.current).toBe(0);
  });
});
