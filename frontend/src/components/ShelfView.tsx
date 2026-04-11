import { useMemo } from "react";
import type { ComicSeries } from "../types/api";
import type { ComicStatus } from "../types/enums";
import { ComicStatusLabel } from "../types/enums";
import ShelfRow from "./ShelfRow";

interface ShelfViewProps {
  comics: ComicSeries[];
  onFilterByStatus: (status: string) => void;
}

/** Ordre d'affichage des étagères */
const STATUS_ORDER: ComicStatus[] = [
  "buying",
  "finished",
  "wishlist",
  "stopped",
];

export default function ShelfView({
  comics,
  onFilterByStatus,
}: ShelfViewProps) {
  const grouped = useMemo(() => {
    const groups = new Map<ComicStatus, ComicSeries[]>();
    for (const comic of comics) {
      const list = groups.get(comic.status) ?? [];
      list.push(comic);
      groups.set(comic.status, list);
    }
    return groups;
  }, [comics]);

  return (
    <div className="space-y-6">
      {STATUS_ORDER.map((status) => {
        const group = grouped.get(status);
        if (!group || group.length === 0) return null;
        return (
          <ShelfRow
            comics={group}
            key={status}
            onSeeAll={() => onFilterByStatus(status)}
            title={ComicStatusLabel[status]}
          />
        );
      })}
    </div>
  );
}
