import { renderHook, act } from "@testing-library/react";
import { useDarkMode } from "../../../hooks/useDarkMode";

describe("useDarkMode", () => {
  beforeEach(() => {
    localStorage.clear();
    document.documentElement.classList.remove("dark");
  });

  it("reads initial preference from localStorage (dark)", () => {
    localStorage.setItem("theme", "dark");

    const { result } = renderHook(() => useDarkMode());

    expect(result.current.isDark).toBe(true);
  });

  it("reads initial preference from localStorage (light)", () => {
    localStorage.setItem("theme", "light");

    const { result } = renderHook(() => useDarkMode());

    expect(result.current.isDark).toBe(false);
  });

  it("defaults to system preference when no localStorage value", () => {
    // matchMedia stub in test-setup.ts returns matches: false
    const { result } = renderHook(() => useDarkMode());

    expect(result.current.isDark).toBe(false);
  });

  it("toggle adds dark class on html element", () => {
    localStorage.setItem("theme", "light");

    const { result } = renderHook(() => useDarkMode());

    act(() => {
      result.current.toggle();
    });

    expect(result.current.isDark).toBe(true);
    expect(document.documentElement.classList.contains("dark")).toBe(true);
  });

  it("toggle removes dark class on html element", () => {
    localStorage.setItem("theme", "dark");

    const { result } = renderHook(() => useDarkMode());

    // Wait for initial effect
    expect(document.documentElement.classList.contains("dark")).toBe(true);

    act(() => {
      result.current.toggle();
    });

    expect(result.current.isDark).toBe(false);
    expect(document.documentElement.classList.contains("dark")).toBe(false);
  });

  it("defaults to dark mode when system preference is dark", () => {
    const originalMatchMedia = window.matchMedia;
    window.matchMedia = vi.fn().mockImplementation((query: string) => ({
      addEventListener: vi.fn(),
      addListener: vi.fn(),
      dispatchEvent: vi.fn(),
      matches: query === "(prefers-color-scheme: dark)",
      media: query,
      onchange: null,
      removeEventListener: vi.fn(),
      removeListener: vi.fn(),
    }));

    const { result } = renderHook(() => useDarkMode());

    expect(result.current.isDark).toBe(true);

    window.matchMedia = originalMatchMedia;
  });

  it("persists preference in localStorage", () => {
    localStorage.setItem("theme", "light");

    const { result } = renderHook(() => useDarkMode());

    act(() => {
      result.current.toggle();
    });

    expect(localStorage.getItem("theme")).toBe("dark");

    act(() => {
      result.current.toggle();
    });

    expect(localStorage.getItem("theme")).toBe("light");
  });
});
