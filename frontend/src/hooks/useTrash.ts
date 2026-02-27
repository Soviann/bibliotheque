import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "../services/api";
import type { ComicSeries, HydraCollection } from "../types/api";

export function useTrash() {
  return useQuery({
    queryFn: () =>
      apiFetch<HydraCollection<ComicSeries>>("/comic_series?deleted=true"),
    queryKey: ["trash"],
  });
}

export function useRestoreComic() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) =>
      apiFetch<ComicSeries>(`/comic_series/${id}/restore`, {
        body: JSON.stringify({}),
        method: "PUT",
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["trash"] });
      void queryClient.invalidateQueries({ queryKey: ["comics"] });
    },
  });
}

export function usePermanentDelete() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) =>
      apiFetch(`/trash/${id}/permanent`, { method: "DELETE" }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["trash"] });
    },
  });
}
