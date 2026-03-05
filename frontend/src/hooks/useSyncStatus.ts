import { useQueryClient } from "@tanstack/react-query";
import { useCallback, useEffect, useState } from "react";

export type SyncStatus = "error" | "idle" | "success" | "syncing";

interface SyncState {
  error: string | null;
  status: SyncStatus;
  syncedCount: number;
}

export function useSyncStatus() {
  const queryClient = useQueryClient();
  const [state, setState] = useState<SyncState>({
    error: null,
    status: "idle",
    syncedCount: 0,
  });

  const handleMessage = useCallback((event: MessageEvent) => {
    const { data } = event;
    if (!data?.type) return;

    switch (data.type) {
      case "sync-start":
        setState({ error: null, status: "syncing", syncedCount: 0 });
        break;
      case "sync-complete":
        setState({ error: null, status: "success", syncedCount: data.count ?? 0 });
        if ((data.count ?? 0) > 0) {
          void queryClient.invalidateQueries({ queryKey: ["comics"] });
          void queryClient.invalidateQueries({ queryKey: ["comic"] });
        }
        break;
      case "sync-error":
        setState((prev) => ({ ...prev, error: data.error ?? "Erreur inconnue", status: "error" }));
        break;
    }
  }, [queryClient]);

  useEffect(() => {
    const sw = navigator.serviceWorker;
    if (!sw) return;

    sw.addEventListener("message", handleMessage);
    return () => sw.removeEventListener("message", handleMessage);
  }, [handleMessage]);

  return state;
}
