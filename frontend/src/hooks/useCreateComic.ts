import { apiFetch } from "../services/api";
import type { ComicSeries } from "../types/api";
import { useOfflineMutation } from "./useOfflineMutation";

export function useCreateComic() {
  return useOfflineMutation<ComicSeries, Partial<ComicSeries> & Record<string, unknown>>({
    mutationFn: (data) =>
      apiFetch<ComicSeries>("/comic_series", {
        body: JSON.stringify(data),
        method: "POST",
      }),
    offlineOperation: "create",
    offlineResourceType: "comic_series",
    queryKeysToInvalidate: [["comics"]],
  });
}
