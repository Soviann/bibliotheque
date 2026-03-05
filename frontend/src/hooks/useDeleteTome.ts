import { apiFetch } from "../services/api";
import type { ComicSeries } from "../types/api";
import { useOfflineMutation } from "./useOfflineMutation";

export function useDeleteTome(seriesId: number) {
  return useOfflineMutation<unknown, { id: number }>({
    mutationFn: ({ id }) =>
      apiFetch(`/tomes/${id}`, { method: "DELETE" }),
    offlineOperation: "delete",
    offlineResourceId: (v) => String(v.id),
    offlineResourceType: "tome",
    optimisticUpdate: (qc, variables) => {
      qc.setQueryData<ComicSeries>(["comic", seriesId], (old) => {
        if (!old) return old;
        return {
          ...old,
          tomes: old.tomes.filter((t) => t.id !== variables.id),
        };
      });
    },
    queryKeysToInvalidate: [["comic", seriesId]],
  });
}
