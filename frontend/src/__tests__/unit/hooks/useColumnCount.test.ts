import { act, renderHook } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { useColumnCount } from "../../../hooks/useColumnCount";

let resizeCallback: ResizeObserverCallback;
const observeMock = vi.fn();
const disconnectMock = vi.fn();

beforeEach(() => {
  observeMock.mockClear();
  disconnectMock.mockClear();

  class MockResizeObserver {
    constructor(cb: ResizeObserverCallback) {
      resizeCallback = cb;
    }
    disconnect = disconnectMock;
    observe = observeMock;
    unobserve = vi.fn();
  }

  vi.stubGlobal("ResizeObserver", MockResizeObserver);
});

afterEach(() => {
  vi.restoreAllMocks();
});

function triggerResize(width: number) {
  act(() => {
    resizeCallback(
      [{ contentRect: { width } } as ResizeObserverEntry],
      {} as ResizeObserver,
    );
  });
}

describe("useColumnCount", () => {
  it("returns default column count of 2", () => {
    const { result } = renderHook(() => useColumnCount());
    expect(result.current.columnCount).toBe(2);
  });

  it("returns 3 columns for sm breakpoint (≥640px)", () => {
    const { result } = renderHook(() => useColumnCount());
    triggerResize(640);
    expect(result.current.columnCount).toBe(3);
  });

  it("returns 4 columns for md breakpoint (≥768px)", () => {
    const { result } = renderHook(() => useColumnCount());
    triggerResize(768);
    expect(result.current.columnCount).toBe(4);
  });

  it("returns 5 columns for lg breakpoint (≥1024px)", () => {
    const { result } = renderHook(() => useColumnCount());
    triggerResize(1024);
    expect(result.current.columnCount).toBe(5);
  });

  it("returns 6 columns for xl breakpoint (≥1280px)", () => {
    const { result } = renderHook(() => useColumnCount());
    triggerResize(1280);
    expect(result.current.columnCount).toBe(6);
  });

  it("returns 2 columns for narrow width (<640px)", () => {
    const { result } = renderHook(() => useColumnCount());
    triggerResize(400);
    expect(result.current.columnCount).toBe(2);
  });

  it("observes the container ref element", () => {
    const { result } = renderHook(() => useColumnCount());
    const div = document.createElement("div");

    act(() => {
      (result.current.containerRef as React.RefCallback<HTMLDivElement>)(div);
    });

    expect(observeMock).toHaveBeenCalledWith(div);
  });

  it("disconnects observer on unmount", () => {
    const { unmount } = renderHook(() => useColumnCount());
    unmount();
    expect(disconnectMock).toHaveBeenCalled();
  });
});
