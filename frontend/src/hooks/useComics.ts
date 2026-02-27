import { useQuery } from "@tanstack/react-query";
import { apiFetch } from "../services/api";
import type { ComicSeries, HydraCollection } from "../types/api";

export function useComics() {
  return useQuery({
    queryFn: () => apiFetch<HydraCollection<ComicSeries>>("/comic_series"),
    queryKey: ["comics"],
    staleTime: 30 * 60 * 1000,
  });
}
