import { useQuery } from "@tanstack/react-query";
import { queryKeys } from "../queryKeys";
import { getPendingCount } from "../services/offlineQueue";

export function usePendingQueueCount(): number {
  const { data } = useQuery({
    queryFn: getPendingCount,
    queryKey: queryKeys.offline.queueCount,
    refetchInterval: 2000,
  });

  return data ?? 0;
}
