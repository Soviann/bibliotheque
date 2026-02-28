import { apiFetch } from "../services/api";
import type { ComicSeries } from "../types/api";
import { useOfflineMutation } from "./useOfflineMutation";

export function useUpdateComic() {
  return useOfflineMutation<ComicSeries, Partial<ComicSeries> & { id: number } & Record<string, unknown>>({
    mutationFn: ({ id, ...data }) =>
      apiFetch<ComicSeries>(`/comic_series/${id}`, {
        body: JSON.stringify(data),
        method: "PUT",
      }),
    offlineOperation: "update",
    offlineResourceId: (v) => String(v.id),
    offlineResourceType: "comic_series",
    queryKeysToInvalidate: [["comics"]],
  });
}
