import { useQuery } from "@tanstack/react-query";
import { queryKeys } from "../queryKeys";
import { getPendingCount } from "../services/offlineQueue";
import { useOnlineStatus } from "./useOnlineStatus";

export function usePendingQueueCount(): number {
  const isOnline = useOnlineStatus();
  const { data } = useQuery({
    queryFn: getPendingCount,
    queryKey: queryKeys.offline.queueCount,
    refetchInterval: (query) => {
      const count = query.state.data ?? 0;
      // Poll every 2s when offline or queue has items; stop otherwise
      if (!isOnline || count > 0) return 2000;
      return false;
    },
  });

  return data ?? 0;
}
