import { useCallback, useEffect, useRef, useState } from "react";

const BREAKPOINTS: [number, number][] = [
  [1280, 6],
  [1024, 5],
  [768, 4],
  [640, 3],
];

function getColumnCount(width: number): number {
  for (const [breakpoint, cols] of BREAKPOINTS) {
    if (width >= breakpoint) return cols;
  }
  return 2;
}

export function useColumnCount() {
  const [columnCount, setColumnCount] = useState(2);
  const observerRef = useRef<ResizeObserver | null>(null);
  const elementRef = useRef<HTMLDivElement | null>(null);

  useEffect(() => {
    observerRef.current = new ResizeObserver((entries) => {
      const entry = entries[0];
      if (entry) {
        setColumnCount(getColumnCount(entry.contentRect.width));
      }
    });

    if (elementRef.current) {
      observerRef.current.observe(elementRef.current);
    }

    return () => {
      observerRef.current?.disconnect();
    };
  }, []);

  const containerRef = useCallback((node: HTMLDivElement | null) => {
    elementRef.current = node;
    if (node && observerRef.current) {
      observerRef.current.observe(node);
    }
  }, []);

  return { columnCount, containerRef };
}
