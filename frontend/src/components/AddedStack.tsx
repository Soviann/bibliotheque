import { Check } from "lucide-react";
import type { QuickAddItem } from "../hooks/useQuickAdd";

interface AddedStackProps {
  items: QuickAddItem[];
}

export default function AddedStack({ items }: AddedStackProps) {
  if (items.length === 0) return null;

  return (
    <div className="flex items-center gap-3 rounded-xl bg-green-50 px-4 py-2.5 dark:bg-green-950/30">
      {/* Stacked covers */}
      <div className="flex -space-x-3">
        {items.slice(-3).map((item, i) => (
          <div
            className="h-10 w-7 overflow-hidden rounded border-2 border-green-100 bg-surface-tertiary dark:border-green-900"
            key={`${item.title}-${item.tomeNumber}-${i}`}
          >
            {item.coverUrl ? (
              <img alt="" className="h-full w-full object-cover" src={item.coverUrl} />
            ) : (
              <div className="flex h-full items-center justify-center text-[8px] text-text-muted">
                {item.tomeNumber}
              </div>
            )}
          </div>
        ))}
      </div>
      {/* Count */}
      <div className="flex items-center gap-1.5 text-sm font-medium text-green-700 dark:text-green-400">
        <Check className="h-4 w-4" />
        {items.length} tome{items.length > 1 ? "s" : ""} ajouté{items.length > 1 ? "s" : ""}
      </div>
    </div>
  );
}
