import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect } from "react";
import {
  getSyncFailures,
  removeSyncFailure as removeFailure,
  resolveSyncFailure as resolveFailure,
  type SyncFailure,
} from "../services/offlineQueue";

export function useSyncFailures() {
  const queryClient = useQueryClient();

  const { data: failures = [] } = useQuery<SyncFailure[]>({
    queryFn: getSyncFailures,
    queryKey: ["syncFailures"],
    refetchInterval: 3000,
  });

  // Écouter les messages du SW pour rafraîchir immédiatement
  useEffect(() => {
    const handler = (event: MessageEvent) => {
      if (event.data?.type === "sync-failure") {
        void queryClient.invalidateQueries({ queryKey: ["syncFailures"] });
      }
    };

    navigator.serviceWorker?.addEventListener("message", handler);
    return () => {
      navigator.serviceWorker?.removeEventListener("message", handler);
    };
  }, [queryClient]);

  const resolveSyncFailure = async (id: number) => {
    await resolveFailure(id);
    void queryClient.invalidateQueries({ queryKey: ["syncFailures"] });
  };

  const removeSyncFailure = async (id: number) => {
    await removeFailure(id);
    void queryClient.invalidateQueries({ queryKey: ["syncFailures"] });
  };

  return { failures, removeSyncFailure, resolveSyncFailure };
}
