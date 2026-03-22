import { QueryClientProvider } from "@tanstack/react-query";
import { renderHook, act } from "@testing-library/react";
import type { ReactNode } from "react";
import { useSyncStatus } from "../../../hooks/useSyncStatus";
import { createTestQueryClient } from "../../helpers/test-utils";

function createWrapper() {
  const queryClient = createTestQueryClient();
  return function Wrapper({ children }: { children: ReactNode }) {
    return (
      <QueryClientProvider client={queryClient}>
        {children}
      </QueryClientProvider>
    );
  };
}

describe("useSyncStatus", () => {
  let addEventListenerSpy: ReturnType<typeof vi.fn>;
  let removeEventListenerSpy: ReturnType<typeof vi.fn>;
  let messageHandler: ((event: MessageEvent) => void) | null = null;

  beforeEach(() => {
    addEventListenerSpy = vi.fn((event: string, handler: (event: MessageEvent) => void) => {
      if (event === "message") {
        messageHandler = handler;
      }
    });
    removeEventListenerSpy = vi.fn();

    Object.defineProperty(navigator, "serviceWorker", {
      configurable: true,
      value: {
        addEventListener: addEventListenerSpy,
        removeEventListener: removeEventListenerSpy,
      },
      writable: true,
    });
  });

  afterEach(() => {
    messageHandler = null;
  });

  it("returns idle status initially", () => {
    const { result } = renderHook(() => useSyncStatus(), { wrapper: createWrapper() });

    expect(result.current.status).toBe("idle");
    expect(result.current.error).toBeNull();
    expect(result.current.syncedCount).toBe(0);
  });

  it("transitions to syncing on sync-start message", () => {
    const { result } = renderHook(() => useSyncStatus(), { wrapper: createWrapper() });

    act(() => {
      messageHandler?.({ data: { type: "sync-start" } } as MessageEvent);
    });

    expect(result.current.status).toBe("syncing");
    expect(result.current.error).toBeNull();
    expect(result.current.syncedCount).toBe(0);
  });

  it("transitions to success on sync-complete message", () => {
    const { result } = renderHook(() => useSyncStatus(), { wrapper: createWrapper() });

    act(() => {
      messageHandler?.({ data: { type: "sync-start" } } as MessageEvent);
    });

    act(() => {
      messageHandler?.({ data: { count: 5, type: "sync-complete" } } as MessageEvent);
    });

    expect(result.current.status).toBe("success");
    expect(result.current.syncedCount).toBe(5);
    expect(result.current.error).toBeNull();
  });

  it("transitions to error on sync-error message", () => {
    const { result } = renderHook(() => useSyncStatus(), { wrapper: createWrapper() });

    act(() => {
      messageHandler?.({ data: { type: "sync-start" } } as MessageEvent);
    });

    act(() => {
      messageHandler?.({
        data: { error: "Network failed", type: "sync-error" },
      } as MessageEvent);
    });

    expect(result.current.status).toBe("error");
    expect(result.current.error).toBe("Network failed");
  });

  it("uses default error message when none provided", () => {
    const { result } = renderHook(() => useSyncStatus(), { wrapper: createWrapper() });

    act(() => {
      messageHandler?.({
        data: { type: "sync-error" },
      } as MessageEvent);
    });

    expect(result.current.error).toBe("Erreur inconnue");
  });

  it("ignores messages without type", () => {
    const { result } = renderHook(() => useSyncStatus(), { wrapper: createWrapper() });

    act(() => {
      messageHandler?.({ data: {} } as MessageEvent);
    });

    expect(result.current.status).toBe("idle");
  });

  it("handles sync-complete with count 0", () => {
    const { result } = renderHook(() => useSyncStatus(), { wrapper: createWrapper() });

    act(() => {
      messageHandler?.({ data: { type: "sync-start" } } as MessageEvent);
    });

    act(() => {
      messageHandler?.({ data: { count: 0, type: "sync-complete" } } as MessageEvent);
    });

    expect(result.current.status).toBe("success");
    expect(result.current.syncedCount).toBe(0);
  });

  it("does not register listener when serviceWorker is absent", () => {
    Object.defineProperty(navigator, "serviceWorker", {
      configurable: true,
      value: undefined,
      writable: true,
    });

    renderHook(() => useSyncStatus(), { wrapper: createWrapper() });

    expect(addEventListenerSpy).not.toHaveBeenCalled();
  });

  it("cleans up event listener on unmount", () => {
    const { unmount } = renderHook(() => useSyncStatus(), { wrapper: createWrapper() });

    unmount();

    expect(removeEventListenerSpy).toHaveBeenCalledWith(
      "message",
      expect.any(Function),
    );
  });

  it("defaults syncedCount to 0 when sync-complete has no count field", () => {
    const { result } = renderHook(() => useSyncStatus(), { wrapper: createWrapper() });

    act(() => {
      messageHandler?.({ data: { type: "sync-complete" } } as MessageEvent);
    });

    expect(result.current.status).toBe("success");
    expect(result.current.syncedCount).toBe(0);
  });

  it("stays idle when message data is null", () => {
    const { result } = renderHook(() => useSyncStatus(), { wrapper: createWrapper() });

    act(() => {
      messageHandler?.({ data: null } as MessageEvent);
    });

    expect(result.current.status).toBe("idle");
    expect(result.current.syncedCount).toBe(0);
    expect(result.current.error).toBeNull();
  });

  it("does not change state on unknown message type", () => {
    const { result } = renderHook(() => useSyncStatus(), { wrapper: createWrapper() });

    act(() => {
      messageHandler?.({ data: { type: "unknown" } } as MessageEvent);
    });

    expect(result.current.status).toBe("idle");
    expect(result.current.syncedCount).toBe(0);
    expect(result.current.error).toBeNull();
  });

  it("does not re-register the SW listener on re-render", () => {
    const { rerender } = renderHook(() => useSyncStatus(), { wrapper: createWrapper() });

    const initialCallCount = addEventListenerSpy.mock.calls.length;

    // Re-render multiple times
    rerender();
    rerender();
    rerender();

    // addEventListener should not have been called again
    expect(addEventListenerSpy).toHaveBeenCalledTimes(initialCallCount);
  });

  it("invalidates only comics queries on sync-complete with count > 0", () => {
    const queryClient = createTestQueryClient();
    const invalidateSpy = vi.spyOn(queryClient, "invalidateQueries");

    function Wrapper({ children }: { children: ReactNode }) {
      return (
        <QueryClientProvider client={queryClient}>
          {children}
        </QueryClientProvider>
      );
    }

    renderHook(() => useSyncStatus(), { wrapper: Wrapper });

    act(() => {
      messageHandler?.({ data: { count: 2, type: "sync-complete" } } as MessageEvent);
    });

    // Should invalidate comics.all and comics.detailPrefix, not everything
    expect(invalidateSpy).toHaveBeenCalledTimes(2);
    expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: ["comics"] });
    expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: ["comic"] });
  });
});
