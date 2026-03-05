import { apiFetch } from "../services/api";
import type { ComicSeries, HydraCollection } from "../types/api";
import { useOfflineMutation } from "./useOfflineMutation";

export function useDeleteComic() {
  return useOfflineMutation<unknown, { id: number }>({
    mutationFn: ({ id }) =>
      apiFetch(`/comic_series/${id}`, { method: "DELETE" }),
    offlineOperation: "delete",
    offlineResourceId: (v) => String(v.id),
    offlineResourceType: "comic_series",
    optimisticUpdate: (qc, variables) => {
      qc.setQueryData<HydraCollection<ComicSeries>>(["comics"], (old) => {
        if (!old) return old;
        return {
          ...old,
          member: old.member.filter((c) => c.id !== variables.id),
          totalItems: old.totalItems - 1,
        };
      });
    },
    queryKeysToInvalidate: [["comics"], ["trash"]],
  });
}
