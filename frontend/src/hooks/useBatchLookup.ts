import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { endpoints } from "../endpoints";
import { queryKeys } from "../queryKeys";
import { apiFetch } from "../services/api";

export function useBatchLookupPreview(type?: string, force: boolean = false) {
  const params = new URLSearchParams();
  if (type) params.set("type", type);
  if (force) params.set("force", "true");
  const qs = params.toString();

  return useQuery({
    queryFn: () =>
      apiFetch<{ count: number }>(
        `${endpoints.batchLookup.preview}${qs ? `?${qs}` : ""}`,
      ),
    queryKey: queryKeys.batchLookup.preview(type ?? "", force),
  });
}

interface BatchLookupOptions {
  force?: boolean;
  limit?: number;
  type?: string;
}

/**
 * Met en file l'enrichissement des séries à traiter. Le traitement est
 * effectué de façon asynchrone par le worker Messenger ; la mutation
 * renvoie simplement le nombre de séries mises en file.
 */
export function useBatchLookup() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (options: BatchLookupOptions) =>
      apiFetch<{ queued: number }>(endpoints.batchLookup.run, {
        body: JSON.stringify(options),
        method: "POST",
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({
        queryKey: queryKeys.batchLookup.previewPrefix,
      });
    },
  });
}
