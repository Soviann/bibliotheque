import { endpoints } from "../endpoints";
import { queryKeys } from "../queryKeys";
import { apiFetch } from "../services/api";
import type { ComicSeries } from "../types/api";
import { useOfflineMutation } from "./useOfflineMutation";

export function useDeleteTome(seriesId: number) {
  return useOfflineMutation<unknown, { id: number }>({
    mutationFn: ({ id }) =>
      apiFetch(endpoints.tomes.detail(id), { method: "DELETE" }),
    offlineOperation: "delete",
    offlineParentResourceId: String(seriesId),
    offlineParentResourceType: "comic_series",
    offlineResourceId: (v) => String(v.id),
    offlineResourceType: "tome",
    optimisticUpdate: (qc, variables) => {
      qc.setQueryData<ComicSeries>(queryKeys.comics.detail(seriesId), (old) => {
        if (!old) return old;
        return {
          ...old,
          tomes: old.tomes.filter((t) => t.id !== variables.id),
        };
      });
    },
    queryKeysToInvalidate: [queryKeys.comics.detail(seriesId)],
  });
}
