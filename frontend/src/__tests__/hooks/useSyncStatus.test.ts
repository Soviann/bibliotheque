import { act, renderHook } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { useSyncStatus } from "../../hooks/useSyncStatus";

describe("useSyncStatus", () => {
  let messageHandler: ((event: MessageEvent) => void) | null = null;

  beforeEach(() => {
    messageHandler = null;
    vi.stubGlobal("navigator", {
      ...navigator,
      serviceWorker: {
        addEventListener: vi.fn((_event: string, handler: (event: MessageEvent) => void) => {
          if (_event === "message") messageHandler = handler;
        }),
        removeEventListener: vi.fn(),
      },
    });
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it("initial state is idle", () => {
    const { result } = renderHook(() => useSyncStatus());
    expect(result.current.status).toBe("idle");
    expect(result.current.syncedCount).toBe(0);
    expect(result.current.error).toBeNull();
  });

  it("updates to syncing on sync-start message", () => {
    const { result } = renderHook(() => useSyncStatus());

    act(() => {
      messageHandler?.(new MessageEvent("message", { data: { type: "sync-start" } }));
    });

    expect(result.current.status).toBe("syncing");
  });

  it("updates to success on sync-complete message", () => {
    const { result } = renderHook(() => useSyncStatus());

    act(() => {
      messageHandler?.(new MessageEvent("message", { data: { count: 3, type: "sync-complete" } }));
    });

    expect(result.current.status).toBe("success");
    expect(result.current.syncedCount).toBe(3);
  });

  it("updates to error on sync-error message", () => {
    const { result } = renderHook(() => useSyncStatus());

    act(() => {
      messageHandler?.(new MessageEvent("message", { data: { error: "Network failed", type: "sync-error" } }));
    });

    expect(result.current.status).toBe("error");
    expect(result.current.error).toBe("Network failed");
  });
});
