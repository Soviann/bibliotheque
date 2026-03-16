import { endpoints } from "../endpoints";
import { queryKeys } from "../queryKeys";
import { apiFetch } from "../services/api";
import type { ComicSeries, Tome } from "../types/api";
import { useOfflineMutation } from "./useOfflineMutation";

export function useUpdateTome(seriesId?: number) {
  return useOfflineMutation<Tome, Partial<Tome> & { id: number }>({
    mutationFn: ({ id, ...data }) =>
      apiFetch<Tome>(endpoints.tomes.detail(id), {
        body: JSON.stringify(data),
        headers: { "Content-Type": "application/merge-patch+json" },
        method: "PATCH",
      }),
    offlineContentType: "application/merge-patch+json",
    offlineHttpMethod: "PATCH",
    offlineOperation: "update",
    offlineParentResourceId: seriesId ? String(seriesId) : undefined,
    offlineParentResourceType: "comic_series",
    offlineResourceId: (v) => String(v.id),
    offlineResourceType: "tome",
    optimisticUpdate: (qc, variables) => {
      if (!seriesId) return;
      qc.setQueryData<ComicSeries>(queryKeys.comics.detail(seriesId), (old) => {
        if (!old) return old;
        return {
          ...old,
          tomes: old.tomes.map((t) =>
            t.id === variables.id ? { ...t, ...variables, _syncPending: true } : t,
          ),
        };
      });
    },
    queryKeysToInvalidate: [queryKeys.comics.detailPrefix],
  });
}
