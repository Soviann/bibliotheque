import { useQuery } from "@tanstack/react-query";
import { endpoints } from "../endpoints";
import { queryKeys } from "../queryKeys";
import { apiFetch } from "../services/api";
import type { ComicSeries, HydraCollection } from "../types/api";
import { useOfflineMutation } from "./useOfflineMutation";

export function useTrash() {
  return useQuery({
    queryFn: () =>
      apiFetch<HydraCollection<ComicSeries>>(endpoints.trash.collection),
    queryKey: queryKeys.trash.all,
  });
}

export function useRestoreComic() {
  return useOfflineMutation<ComicSeries, { id: number }>({
    mutationFn: ({ id }) =>
      apiFetch<ComicSeries>(endpoints.comicSeries.restore(id), {
        body: JSON.stringify({}),
        method: "PUT",
      }),
    offlineOperation: "update",
    offlineResourceId: (v) => String(v.id),
    offlineResourceType: "comic_series",
    queryKeysToInvalidate: [queryKeys.trash.all, queryKeys.comics.all],
  });
}

export function usePermanentDelete() {
  return useOfflineMutation<unknown, { id: number }>({
    mutationFn: ({ id }) =>
      apiFetch(endpoints.trash.permanent(id), { method: "DELETE" }),
    offlineOperation: "delete",
    offlineResourceId: (v) => String(v.id),
    offlineResourceType: "comic_series",
    queryKeysToInvalidate: [queryKeys.trash.all],
  });
}
