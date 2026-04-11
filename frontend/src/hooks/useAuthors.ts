import { useQuery } from "@tanstack/react-query";
import { endpoints } from "../endpoints";
import { queryKeys } from "../queryKeys";
import { apiFetch } from "../services/api";
import type { Author, HydraCollection } from "../types/api";

const STALE_TIME_30MIN = 30 * 60 * 1000;

export function useAuthors(search: string = "") {
  const params = search ? `?name=${encodeURIComponent(search)}` : "";

  return useQuery({
    enabled: search.length >= 1,
    queryFn: () =>
      apiFetch<HydraCollection<Author>>(`${endpoints.authors}${params}`),
    queryKey: queryKeys.authors.search(search),
    staleTime: STALE_TIME_30MIN,
  });
}
