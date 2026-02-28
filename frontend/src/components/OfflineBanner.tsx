import { usePendingQueueCount } from "../hooks/usePendingQueueCount";
import { useOnlineStatus } from "../hooks/useOnlineStatus";

export default function OfflineBanner() {
  const isOnline = useOnlineStatus();
  const pendingCount = usePendingQueueCount();

  if (isOnline && pendingCount === 0) return null;

  const message = !isOnline
    ? pendingCount > 0
      ? `Mode hors ligne — ${pendingCount} opération${pendingCount > 1 ? "s" : ""} en attente`
      : "Mode hors ligne"
    : `${pendingCount} opération${pendingCount > 1 ? "s" : ""} en attente de synchronisation`;

  return (
    <div className="fixed top-0 left-0 right-0 z-50 bg-amber-500 py-1 text-center text-xs font-medium text-amber-950">
      {message}
    </div>
  );
}
