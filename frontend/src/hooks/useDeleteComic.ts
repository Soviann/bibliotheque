import { endpoints } from "../endpoints";
import { queryKeys } from "../queryKeys";
import { apiFetch } from "../services/api";
import type { ComicSeries, HydraCollection } from "../types/api";
import { useOfflineMutation } from "./useOfflineMutation";

export function useDeleteComic() {
  return useOfflineMutation<unknown, { id: number }>({
    mutationFn: ({ id }) =>
      apiFetch(endpoints.comicSeries.detail(id), { method: "DELETE" }),
    offlineOperation: "delete",
    offlineResourceId: (v) => String(v.id),
    offlineResourceType: "comic_series",
    optimisticUpdate: (qc, variables) => {
      qc.setQueryData<HydraCollection<ComicSeries>>(
        queryKeys.comics.all,
        (old) => {
          if (!old) return old;
          return {
            ...old,
            member: old.member.filter((c) => c.id !== variables.id),
            totalItems: old.totalItems - 1,
          };
        },
      );
    },
    queryKeysToInvalidate: (variables) => [
      queryKeys.comics.all,
      queryKeys.comics.detail(variables.id),
      queryKeys.trash.all,
    ],
  });
}
