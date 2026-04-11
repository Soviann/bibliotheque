import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect } from "react";
import { queryKeys } from "../queryKeys";
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
    queryKey: queryKeys.offline.syncFailures,
    refetchInterval: (query) => {
      const count = query.state.data?.length ?? 0;
      return count > 0 ? 3000 : false;
    },
  });

  // Écouter les messages du SW pour rafraîchir immédiatement
  useEffect(() => {
    const handler = (event: MessageEvent) => {
      if (event.data?.type === "sync-failure") {
        void queryClient.invalidateQueries({
          queryKey: queryKeys.offline.syncFailures,
        });
      }
    };

    navigator.serviceWorker?.addEventListener("message", handler);
    return () => {
      navigator.serviceWorker?.removeEventListener("message", handler);
    };
  }, [queryClient]);

  const resolveSyncFailure = async (id: number) => {
    await resolveFailure(id);
    void queryClient.invalidateQueries({
      queryKey: queryKeys.offline.syncFailures,
    });
  };

  const removeSyncFailure = async (id: number) => {
    await removeFailure(id);
    void queryClient.invalidateQueries({
      queryKey: queryKeys.offline.syncFailures,
    });
  };

  return { failures, removeSyncFailure, resolveSyncFailure };
}
