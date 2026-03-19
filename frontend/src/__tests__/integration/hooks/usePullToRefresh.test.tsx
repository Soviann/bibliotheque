import { act, renderHook } from "@testing-library/react";
import { usePullToRefresh } from "../../../hooks/usePullToRefresh";

function createTouchEvent(type: string, clientY: number): TouchEvent {
  return new TouchEvent(type, {
    touches: type === "touchend" ? [] : [{ clientY } as Touch],
    changedTouches: [{ clientY } as Touch],
    bubbles: true,
    cancelable: true,
  });
}

describe("usePullToRefresh", () => {
  let onRefresh: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    onRefresh = vi.fn().mockResolvedValue(undefined);
    // Scroll at top by default
    Object.defineProperty(window, "scrollY", { configurable: true, value: 0, writable: true });
  });

  it("calls onRefresh after pull exceeding threshold", async () => {
    const { result } = renderHook(() => usePullToRefresh({ onRefresh }));

    act(() => {
      window.dispatchEvent(createTouchEvent("touchstart", 100));
    });
    act(() => {
      window.dispatchEvent(createTouchEvent("touchmove", 200));
    });
    act(() => {
      window.dispatchEvent(createTouchEvent("touchend", 200));
    });

    expect(onRefresh).toHaveBeenCalledTimes(1);
    // Should show refreshing state
    expect(result.current.isRefreshing).toBe(true);
  });

  it("does not call onRefresh if pull is below threshold", () => {
    renderHook(() => usePullToRefresh({ onRefresh }));

    act(() => {
      window.dispatchEvent(createTouchEvent("touchstart", 100));
    });
    act(() => {
      window.dispatchEvent(createTouchEvent("touchmove", 120));
    });
    act(() => {
      window.dispatchEvent(createTouchEvent("touchend", 120));
    });

    expect(onRefresh).not.toHaveBeenCalled();
  });

  it("does not trigger when not scrolled to top", () => {
    Object.defineProperty(window, "scrollY", { configurable: true, value: 100, writable: true });

    renderHook(() => usePullToRefresh({ onRefresh }));

    act(() => {
      window.dispatchEvent(createTouchEvent("touchstart", 100));
    });
    act(() => {
      window.dispatchEvent(createTouchEvent("touchmove", 250));
    });
    act(() => {
      window.dispatchEvent(createTouchEvent("touchend", 250));
    });

    expect(onRefresh).not.toHaveBeenCalled();
  });

  it("returns pullDistance during pull", () => {
    const { result } = renderHook(() => usePullToRefresh({ onRefresh }));

    act(() => {
      window.dispatchEvent(createTouchEvent("touchstart", 100));
    });
    act(() => {
      window.dispatchEvent(createTouchEvent("touchmove", 160));
    });

    expect(result.current.pullDistance).toBe(60);
  });

  it("resets pullDistance on touchend below threshold", () => {
    const { result } = renderHook(() => usePullToRefresh({ onRefresh }));

    act(() => {
      window.dispatchEvent(createTouchEvent("touchstart", 100));
    });
    act(() => {
      window.dispatchEvent(createTouchEvent("touchmove", 120));
    });
    act(() => {
      window.dispatchEvent(createTouchEvent("touchend", 120));
    });

    expect(result.current.pullDistance).toBe(0);
  });

  it("does not trigger on upward swipe", () => {
    renderHook(() => usePullToRefresh({ onRefresh }));

    act(() => {
      window.dispatchEvent(createTouchEvent("touchstart", 200));
    });
    act(() => {
      window.dispatchEvent(createTouchEvent("touchmove", 100));
    });
    act(() => {
      window.dispatchEvent(createTouchEvent("touchend", 100));
    });

    expect(onRefresh).not.toHaveBeenCalled();
  });

  it("resets isRefreshing after onRefresh resolves", async () => {
    let resolve: () => void;
    const slowRefresh = vi.fn(
      () => new Promise<void>((r) => { resolve = r; }),
    );
    const { result } = renderHook(() => usePullToRefresh({ onRefresh: slowRefresh }));

    act(() => {
      window.dispatchEvent(createTouchEvent("touchstart", 100));
    });
    act(() => {
      window.dispatchEvent(createTouchEvent("touchmove", 200));
    });
    act(() => {
      window.dispatchEvent(createTouchEvent("touchend", 200));
    });

    expect(result.current.isRefreshing).toBe(true);

    await act(async () => {
      resolve!();
    });

    expect(result.current.isRefreshing).toBe(false);
  });

  it("cleans up event listeners on unmount", () => {
    const addSpy = vi.spyOn(window, "addEventListener");
    const removeSpy = vi.spyOn(window, "removeEventListener");

    const { unmount } = renderHook(() => usePullToRefresh({ onRefresh }));

    const addedTypes = addSpy.mock.calls.map(([type]) => type).sort();
    expect(addedTypes).toContain("touchend");
    expect(addedTypes).toContain("touchmove");
    expect(addedTypes).toContain("touchstart");

    unmount();

    const removedTypes = removeSpy.mock.calls.map(([type]) => type).sort();
    expect(removedTypes).toContain("touchend");
    expect(removedTypes).toContain("touchmove");
    expect(removedTypes).toContain("touchstart");

    addSpy.mockRestore();
    removeSpy.mockRestore();
  });
});
