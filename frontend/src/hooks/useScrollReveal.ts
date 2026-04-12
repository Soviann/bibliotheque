import { type RefObject, useEffect, useRef, useState } from "react";

interface UseScrollRevealOptions {
  sentinelRef: RefObject<HTMLElement | null>;
}

const SCROLL_HYSTERESIS = 3;

export function useScrollReveal({ sentinelRef }: UseScrollRevealOptions): {
  showStickyBar: boolean;
} {
  const [isAboveViewport, setIsAboveViewport] = useState(false);
  const [isScrollingUp, setIsScrollingUp] = useState(false);
  const prevScrollY = useRef(0);

  useEffect(() => {
    const sentinel = sentinelRef.current;
    if (!sentinel) return;

    const observer = new IntersectionObserver(
      ([entry]) => {
        setIsAboveViewport(!entry.isIntersecting);
      },
      { threshold: 0 },
    );

    observer.observe(sentinel);

    return () => observer.disconnect();
  }, [sentinelRef]);

  useEffect(() => {
    function handleScroll() {
      const currentY = window.scrollY;
      const delta = currentY - prevScrollY.current;

      if (Math.abs(delta) >= SCROLL_HYSTERESIS) {
        setIsScrollingUp(delta < 0);
        prevScrollY.current = currentY;
      }
    }

    window.addEventListener("scroll", handleScroll, { passive: true });

    return () => window.removeEventListener("scroll", handleScroll);
  }, []);

  return { showStickyBar: isAboveViewport && isScrollingUp };
}
