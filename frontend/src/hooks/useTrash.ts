import { useQuery } from "@tanstack/react-query";
import { apiFetch } from "../services/api";
import type { ComicSeries, HydraCollection } from "../types/api";
import { useOfflineMutation } from "./useOfflineMutation";

export function useTrash() {
  return useQuery({
    queryFn: () =>
      apiFetch<HydraCollection<ComicSeries>>("/trash"),
    queryKey: ["trash"],
  });
}

export function useRestoreComic() {
  return useOfflineMutation<ComicSeries, { id: number }>({
    mutationFn: ({ id }) =>
      apiFetch<ComicSeries>(`/comic_series/${id}/restore`, {
        body: JSON.stringify({}),
        method: "PUT",
      }),
    offlineOperation: "update",
    offlineResourceId: (v) => String(v.id),
    offlineResourceType: "comic_series",
    queryKeysToInvalidate: [["trash"], ["comics"]],
  });
}

export function usePermanentDelete() {
  return useOfflineMutation<unknown, { id: number }>({
    mutationFn: ({ id }) =>
      apiFetch(`/trash/${id}/permanent`, { method: "DELETE" }),
    offlineOperation: "delete",
    offlineResourceId: (v) => String(v.id),
    offlineResourceType: "comic_series",
    queryKeysToInvalidate: [["trash"]],
  });
}
