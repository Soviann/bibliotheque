import { useQuery } from "@tanstack/react-query";
import { apiFetch } from "../services/api";
import type { CoverSearchResult } from "../types/api";

export function useCoverSearch(query: string, type?: string) {
  const params = new URLSearchParams({ query });
  if (type) params.set("type", type);

  return useQuery({
    enabled: query.length >= 2,
    queryFn: () => apiFetch<CoverSearchResult[]>(`/lookup/covers?${params}`),
    queryKey: ["lookup", "covers", query, type],
    staleTime: 5 * 60 * 1000,
  });
}
