import { useCallback, useEffect, useRef, useState } from "react";

interface UsePullToRefreshOptions {
  onRefresh: () => Promise<void>;
  threshold?: number;
}

interface UsePullToRefreshReturn {
  isRefreshing: boolean;
  pullDistance: number;
}

const DEFAULT_THRESHOLD = 80;

export function usePullToRefresh({
  onRefresh,
  threshold = DEFAULT_THRESHOLD,
}: UsePullToRefreshOptions): UsePullToRefreshReturn {
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [pullDistance, setPullDistance] = useState(0);
  const startY = useRef<number | null>(null);
  const isTracking = useRef(false);
  const pullDistanceRef = useRef(0);
  const onRefreshRef = useRef(onRefresh);
  onRefreshRef.current = onRefresh;

  const handleTouchStart = useCallback((e: TouchEvent) => {
    if (window.scrollY > 0) return;
    startY.current = e.touches[0].clientY;
    isTracking.current = true;
  }, []);

  const handleTouchMove = useCallback((e: TouchEvent) => {
    if (!isTracking.current || startY.current === null) return;
    const delta = e.touches[0].clientY - startY.current;
    if (delta > 0) {
      pullDistanceRef.current = delta;
      setPullDistance(delta);
    }
  }, []);

  const handleTouchEnd = useCallback(() => {
    if (!isTracking.current) return;
    isTracking.current = false;
    const distance = pullDistanceRef.current;

    if (distance >= threshold) {
      setIsRefreshing(true);
      setPullDistance(0);
      pullDistanceRef.current = 0;
      onRefreshRef.current().finally(() => {
        setIsRefreshing(false);
      });
    } else {
      setPullDistance(0);
      pullDistanceRef.current = 0;
    }

    startY.current = null;
  }, [threshold]);

  useEffect(() => {
    window.addEventListener("touchstart", handleTouchStart, { passive: true });
    window.addEventListener("touchmove", handleTouchMove, { passive: true });
    window.addEventListener("touchend", handleTouchEnd);

    return () => {
      window.removeEventListener("touchstart", handleTouchStart);
      window.removeEventListener("touchmove", handleTouchMove);
      window.removeEventListener("touchend", handleTouchEnd);
    };
  }, [handleTouchEnd, handleTouchMove, handleTouchStart]);

  return { isRefreshing, pullDistance };
}
