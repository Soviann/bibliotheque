import { renderHook, act } from "@testing-library/react";
import type { ReactNode } from "react";
import { MemoryRouter, useNavigate } from "react-router-dom";
import { useGoBack } from "../../../hooks/useGoBack";

// Mock useNavigate
const mockNavigate = vi.fn();
vi.mock("react-router-dom", async () => {
  const actual = await vi.importActual("react-router-dom");
  return { ...actual, useNavigate: () => mockNavigate };
});

function wrapper({ children }: { children: ReactNode }) {
  return <MemoryRouter>{children}</MemoryRouter>;
}

describe("useGoBack", () => {
  beforeEach(() => {
    mockNavigate.mockClear();
  });

  it("navigates back when there is in-app history (idx > 0)", () => {
    // Simulate in-app history
    Object.defineProperty(window.history, "state", {
      configurable: true,
      value: { idx: 2 },
      writable: true,
    });

    const { result } = renderHook(() => useGoBack(), { wrapper });

    act(() => {
      result.current();
    });

    expect(mockNavigate).toHaveBeenCalledWith(-1);
  });

  it("navigates to fallback (/) when there is no in-app history (idx === 0)", () => {
    Object.defineProperty(window.history, "state", {
      configurable: true,
      value: { idx: 0 },
      writable: true,
    });

    const { result } = renderHook(() => useGoBack(), { wrapper });

    act(() => {
      result.current();
    });

    expect(mockNavigate).toHaveBeenCalledWith("/", { viewTransition: true });
  });

  it("navigates to fallback when history.state is null", () => {
    Object.defineProperty(window.history, "state", {
      configurable: true,
      value: null,
      writable: true,
    });

    const { result } = renderHook(() => useGoBack(), { wrapper });

    act(() => {
      result.current();
    });

    expect(mockNavigate).toHaveBeenCalledWith("/", { viewTransition: true });
  });

  it("navigates to custom fallback path when provided", () => {
    Object.defineProperty(window.history, "state", {
      configurable: true,
      value: { idx: 0 },
      writable: true,
    });

    const { result } = renderHook(() => useGoBack("/trash"), { wrapper });

    act(() => {
      result.current();
    });

    expect(mockNavigate).toHaveBeenCalledWith("/trash", { viewTransition: true });
  });
});
