import { useQueryClient } from "@tanstack/react-query";
import { useCallback, useEffect, useRef, useState } from "react";
import { queryKeys } from "../queryKeys";

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

  // Stable ref to avoid re-registering SW listener on queryClient change
  const queryClientRef = useRef(queryClient);
  queryClientRef.current = queryClient;

  const handleMessage = useCallback((event: MessageEvent) => {
    const { data } = event;
    if (!data?.type) return;

    switch (data.type) {
      case "sync-start":
        setState({ error: null, status: "syncing", syncedCount: 0 });
        break;
      case "sync-complete":
        setState({
          error: null,
          status: "success",
          syncedCount: data.count ?? 0,
        });
        if ((data.count ?? 0) > 0) {
          void queryClientRef.current.invalidateQueries({
            queryKey: queryKeys.comics.all,
          });
          void queryClientRef.current.invalidateQueries({
            queryKey: queryKeys.comics.detailPrefix,
          });
        }
        break;
      case "sync-error":
        setState((prev) => ({
          ...prev,
          error: data.error ?? "Erreur inconnue",
          status: "error",
        }));
        break;
    }
  }, []);

  useEffect(() => {
    const sw = navigator.serviceWorker;
    if (!sw) return;

    sw.addEventListener("message", handleMessage);
    return () => sw.removeEventListener("message", handleMessage);
  }, [handleMessage]);

  return state;
}
