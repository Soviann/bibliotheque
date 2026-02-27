import { useQuery } from "@tanstack/react-query";
import { apiFetch } from "../services/api";
import type { ComicSeries } from "../types/api";

export function useComic(id: number | undefined) {
  return useQuery({
    enabled: id !== undefined,
    queryFn: () => apiFetch<ComicSeries>(`/comic_series/${id}`),
    queryKey: ["comic", id],
  });
}
