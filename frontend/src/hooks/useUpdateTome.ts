import { apiFetch } from "../services/api";
import type { Tome } from "../types/api";
import { useOfflineMutation } from "./useOfflineMutation";

export function useUpdateTome() {
  return useOfflineMutation<Tome, Partial<Tome> & { id: number }>({
    mutationFn: ({ id, ...data }) =>
      apiFetch<Tome>(`/tomes/${id}`, {
        body: JSON.stringify(data),
        headers: { "Content-Type": "application/merge-patch+json" },
        method: "PATCH",
      }),
    offlineOperation: "update",
    offlineResourceId: (v) => String(v.id),
    offlineResourceType: "tome",
    queryKeysToInvalidate: [["comic"]],
  });
}
