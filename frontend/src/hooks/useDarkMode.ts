import { useCallback, useEffect, useState } from "react";
import { THEME_COLOR_DARK, THEME_COLOR_LIGHT } from "../theme";

function getInitialDark(): boolean {
  const stored = localStorage.getItem("theme");
  if (stored === "dark") return true;
  if (stored === "light") return false;
  return window.matchMedia("(prefers-color-scheme: dark)").matches;
}

export function useDarkMode() {
  const [isDark, setIsDark] = useState(getInitialDark);

  useEffect(() => {
    document.documentElement.classList.toggle("dark", isDark);
    localStorage.setItem("theme", isDark ? "dark" : "light");

    const meta = document.querySelector(
      'meta[name="theme-color"]:not([media])',
    );
    if (meta) {
      meta.setAttribute(
        "content",
        isDark ? THEME_COLOR_DARK : THEME_COLOR_LIGHT,
      );
    }
  }, [isDark]);

  const toggle = useCallback(() => setIsDark((prev) => !prev), []);

  return { isDark, toggle };
}
