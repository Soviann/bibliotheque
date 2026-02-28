import { apiFetch } from "../services/api";
import { useOfflineMutation } from "./useOfflineMutation";

export function useDeleteComic() {
  return useOfflineMutation<unknown, { id: number }>({
    mutationFn: ({ id }) =>
      apiFetch(`/comic_series/${id}`, { method: "DELETE" }),
    offlineOperation: "delete",
    offlineResourceId: (v) => String(v.id),
    offlineResourceType: "comic_series",
    queryKeysToInvalidate: [["comics"], ["trash"]],
  });
}
