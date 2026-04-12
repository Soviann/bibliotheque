import { useCallback, useEffect, useRef } from "react";
import type { ReactNode } from "react";
import { useLocation, useNavigationType } from "react-router-dom";
import { Virtuoso } from "react-virtuoso";
import type { StateSnapshot, VirtuosoHandle } from "react-virtuoso";
import { useColumnCount } from "../hooks/useColumnCount";
import {
  getSavedVirtuosoState,
  saveVirtuosoState,
} from "../hooks/useScrollRestoration";

const GRID_CLASSES =
  "grid grid-cols-2 gap-x-5 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6";

interface VirtualGridProps<T> {
  estimateRowHeight?: number;
  items: T[];
  renderItem: (item: T) => ReactNode;
  testId?: string;
}

export default function VirtualGrid<T>({
  estimateRowHeight = 320,
  items,
  renderItem,
  testId = "virtual-grid",
}: VirtualGridProps<T>) {
  const { columnCount, containerRef } = useColumnCount();
  const rowCount = Math.ceil(items.length / columnCount);
  const { key: locationKey } = useLocation();
  const navigationType = useNavigationType();
  const virtuosoRef = useRef<VirtuosoHandle>(null);
  const latestSnapshotRef = useRef<StateSnapshot | undefined>(undefined);
  const gridElRef = useRef<HTMLDivElement | null>(null);

  // Restaurer le state snapshot pour POP navigation
  const savedState =
    navigationType === "POP" ? getSavedVirtuosoState(locationKey) : undefined;

  // Sauvegarder le snapshot en continu via getState à intervalles réguliers
  useEffect(() => {
    const interval = setInterval(() => {
      virtuosoRef.current?.getState((snapshot) => {
        latestSnapshotRef.current = snapshot;
      });
    }, 500);

    return () => {
      clearInterval(interval);
      // Au démontage, persister le dernier snapshot connu
      if (latestSnapshotRef.current) {
        saveVirtuosoState(locationKey, latestSnapshotRef.current);
      }
    };
  }, [locationKey]);

  const combinedRef = useCallback(
    (node: HTMLDivElement | null) => {
      gridElRef.current = node;
      containerRef(node);
    },
    [containerRef],
  );

  return (
    <div data-testid={testId} ref={combinedRef}>
      <Virtuoso
        defaultItemHeight={estimateRowHeight}
        itemContent={(rowIndex) => {
          const startIndex = rowIndex * columnCount;
          const rowItems = items.slice(startIndex, startIndex + columnCount);

          return (
            <div className={GRID_CLASSES}>
              {rowItems.map((item, i) => (
                <div key={startIndex + i}>{renderItem(item)}</div>
              ))}
            </div>
          );
        }}
        overscan={3 * estimateRowHeight}
        ref={virtuosoRef}
        restoreStateFrom={savedState}
        totalCount={rowCount}
        useWindowScroll
      />
    </div>
  );
}
