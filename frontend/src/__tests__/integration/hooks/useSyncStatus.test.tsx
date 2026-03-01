import { renderHook, act } from "@testing-library/react";
import { useSyncStatus } from "../../../hooks/useSyncStatus";

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
    const { result } = renderHook(() => useSyncStatus());

    expect(result.current.status).toBe("idle");
    expect(result.current.error).toBeNull();
    expect(result.current.syncedCount).toBe(0);
  });

  it("transitions to syncing on sync-start message", () => {
    const { result } = renderHook(() => useSyncStatus());

    act(() => {
      messageHandler?.({ data: { type: "sync-start" } } as MessageEvent);
    });

    expect(result.current.status).toBe("syncing");
    expect(result.current.error).toBeNull();
    expect(result.current.syncedCount).toBe(0);
  });

  it("transitions to success on sync-complete message", () => {
    const { result } = renderHook(() => useSyncStatus());

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
    const { result } = renderHook(() => useSyncStatus());

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
    const { result } = renderHook(() => useSyncStatus());

    act(() => {
      messageHandler?.({
        data: { type: "sync-error" },
      } as MessageEvent);
    });

    expect(result.current.error).toBe("Erreur inconnue");
  });

  it("ignores messages without type", () => {
    const { result } = renderHook(() => useSyncStatus());

    act(() => {
      messageHandler?.({ data: {} } as MessageEvent);
    });

    expect(result.current.status).toBe("idle");
  });

  it("cleans up event listener on unmount", () => {
    const { unmount } = renderHook(() => useSyncStatus());

    unmount();

    expect(removeEventListenerSpy).toHaveBeenCalledWith(
      "message",
      expect.any(Function),
    );
  });
});
