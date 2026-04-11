import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { endpoints } from "../endpoints";
import { queryKeys } from "../queryKeys";
import { apiFetch } from "../services/api";
import type { PurgeableSeries } from "../types/api";

export function usePurgePreview(days: number) {
  return useQuery({
    enabled: days > 0,
    queryFn: () =>
      apiFetch<PurgeableSeries[]>(`${endpoints.purge.preview}?days=${days}`),
    queryKey: queryKeys.purge.preview(days),
  });
}

export function useExecutePurge() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (seriesIds: number[]) =>
      apiFetch<{ purged: number }>(endpoints.purge.execute, {
        body: JSON.stringify({ seriesIds }),
        method: "POST",
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({
        queryKey: queryKeys.purge.previewPrefix,
      });
      queryClient.invalidateQueries({ queryKey: queryKeys.comics.all });
    },
  });
}
