import { act, renderHook } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { useQuickAdd } from "../../../hooks/useQuickAdd";

describe("useQuickAdd", () => {
  it("starts with empty added list and batch off", () => {
    const { result } = renderHook(() => useQuickAdd());
    expect(result.current.addedItems).toEqual([]);
    expect(result.current.batchMode).toBe(false);
  });

  it("toggles batch mode", () => {
    const { result } = renderHook(() => useQuickAdd());
    act(() => result.current.toggleBatchMode());
    expect(result.current.batchMode).toBe(true);
    act(() => result.current.toggleBatchMode());
    expect(result.current.batchMode).toBe(false);
  });

  it("adds an item to the stack", () => {
    const { result } = renderHook(() => useQuickAdd());
    act(() =>
      result.current.addItem({
        coverUrl: "/cover.jpg",
        title: "One Piece",
        tomeNumber: 5,
      }),
    );
    expect(result.current.addedItems).toHaveLength(1);
    expect(result.current.addedItems[0].title).toBe("One Piece");
  });

  it("clears items", () => {
    const { result } = renderHook(() => useQuickAdd());
    act(() =>
      result.current.addItem({ coverUrl: null, title: "Test", tomeNumber: 1 }),
    );
    act(() => result.current.clearItems());
    expect(result.current.addedItems).toEqual([]);
  });
});
