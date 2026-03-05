import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "../services/api";
import type { PurgeableSeries } from "../types/api";

export function usePurgePreview(days: number) {
  return useQuery({
    enabled: days > 0,
    queryFn: () =>
      apiFetch<PurgeableSeries[]>(`/tools/purge/preview?days=${days}`),
    queryKey: ["purge-preview", days],
  });
}

export function useExecutePurge() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (seriesIds: number[]) =>
      apiFetch<{ purged: number }>("/tools/purge/execute", {
        body: JSON.stringify({ seriesIds }),
        method: "POST",
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["purge-preview"] });
      queryClient.invalidateQueries({ queryKey: ["comics"] });
    },
  });
}
