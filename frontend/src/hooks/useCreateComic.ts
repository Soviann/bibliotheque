import { useMutation, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "../services/api";
import type { ComicSeries } from "../types/api";

export function useCreateComic() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: Partial<ComicSeries>) =>
      apiFetch<ComicSeries>("/comic_series", {
        body: JSON.stringify(data),
        method: "POST",
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["comics"] });
    },
  });
}
