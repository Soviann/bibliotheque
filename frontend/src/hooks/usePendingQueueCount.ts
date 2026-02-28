import { useQuery } from "@tanstack/react-query";
import { getPendingCount } from "../services/offlineQueue";

export function usePendingQueueCount(): number {
  const { data } = useQuery({
    queryFn: getPendingCount,
    queryKey: ["offline-queue-count"],
    refetchInterval: 2000,
  });

  return data ?? 0;
}
