import { useWindowVirtualizer } from "@tanstack/react-virtual";
import type { ReactNode } from "react";
import { useColumnCount } from "../hooks/useColumnCount";

const GRID_CLASSES = "grid grid-cols-2 gap-x-5 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6";
const ROW_GAP = 20; // Tailwind gap-5 = 1.25rem = 20px

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

  const virtualizer = useWindowVirtualizer({
    count: rowCount,
    estimateSize: () => estimateRowHeight,
    gap: ROW_GAP,
    overscan: 3,
  });

  return (
    <div data-testid={testId} ref={containerRef}>
      <div
        style={{
          height: `${virtualizer.getTotalSize()}px`,
          position: "relative",
          width: "100%",
        }}
      >
        {virtualizer.getVirtualItems().map((virtualRow) => {
          const startIndex = virtualRow.index * columnCount;
          const rowItems = items.slice(startIndex, startIndex + columnCount);

          return (
            <div
              className={GRID_CLASSES}
              data-index={virtualRow.index}
              key={virtualRow.key}
              ref={virtualizer.measureElement}
              style={{
                left: 0,
                position: "absolute",
                top: 0,
                transform: `translateY(${virtualRow.start}px)`,
                width: "100%",
              }}
            >
              {rowItems.map((item, i) => (
                <div key={startIndex + i}>{renderItem(item)}</div>
              ))}
            </div>
          );
        })}
      </div>
    </div>
  );
}
