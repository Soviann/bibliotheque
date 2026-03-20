import { ChevronDown, ChevronUp } from "lucide-react";
import { useCallback, useEffect, useState } from "react";
import { usePendingQueueCount } from "../hooks/usePendingQueueCount";
import { useOnlineStatus } from "../hooks/useOnlineStatus";
import { getAll, type QueueItem } from "../services/offlineQueue";
import { operationLabels, resourceLabels } from "../utils/syncLabels";

export default function OfflineBanner() {
  const isOnline = useOnlineStatus();
  const pendingCount = usePendingQueueCount();
  const [expanded, setExpanded] = useState(false);
  const [queueItems, setQueueItems] = useState<QueueItem[]>([]);

  const loadQueue = useCallback(async () => {
    const items = await getAll();
    setQueueItems(items);
  }, []);

  useEffect(() => {
    if (expanded && pendingCount > 0) {
      void loadQueue();
    }
  }, [expanded, loadQueue, pendingCount]);

  if (isOnline && pendingCount === 0) return null;

  const message = !isOnline
    ? pendingCount > 0
      ? `Mode hors ligne — ${pendingCount} opération${pendingCount > 1 ? "s" : ""} en attente`
      : "Mode hors ligne"
    : `${pendingCount} opération${pendingCount > 1 ? "s" : ""} en attente de synchronisation`;

  const Chevron = expanded ? ChevronUp : ChevronDown;

  return (
    <div className="fixed top-0 left-0 right-0 z-50 bg-amber-500 text-amber-950">
      <div className="flex items-center justify-center gap-2 py-1 text-xs font-medium">
        <span>{message}</span>
        {pendingCount > 0 && (
          <button
            aria-label="Voir les opérations en attente"
            className="rounded p-0.5 hover:bg-amber-600/30"
            onClick={() => setExpanded((v) => !v)}
            type="button"
          >
            <Chevron className="h-3.5 w-3.5" />
          </button>
        )}
      </div>
      {expanded && queueItems.length > 0 && (
        <ul className="border-t border-amber-600/30 px-4 py-1.5 text-xs">
          {queueItems.map((item) => (
            <li className="py-0.5" key={item.id}>
              {operationLabels[item.operation] ?? item.operation}{" "}
              {resourceLabels[item.resourceType] ?? item.resourceType}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
