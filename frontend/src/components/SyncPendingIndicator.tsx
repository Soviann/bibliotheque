import { RefreshCw } from "lucide-react";

interface SyncPendingIndicatorProps {
  className?: string;
}

export default function SyncPendingIndicator({ className = "" }: SyncPendingIndicatorProps) {
  return (
    <span className={`inline-flex items-center gap-1 text-amber-500 ${className}`} title="En attente de synchronisation">
      <RefreshCw className="h-3.5 w-3.5 animate-spin" />
    </span>
  );
}
