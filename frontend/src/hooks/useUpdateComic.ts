import { useMutation, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "../services/api";
import type { ComicSeries } from "../types/api";

export function useUpdateComic() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, ...data }: Partial<ComicSeries> & { id: number }) =>
      apiFetch<ComicSeries>(`/comic_series/${id}`, {
        body: JSON.stringify(data),
        method: "PUT",
      }),
    onSuccess: (_data, variables) => {
      void queryClient.invalidateQueries({ queryKey: ["comic", variables.id] });
      void queryClient.invalidateQueries({ queryKey: ["comics"] });
    },
  });
}
