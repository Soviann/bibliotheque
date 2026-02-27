import { useQuery } from "@tanstack/react-query";
import { apiFetch } from "../services/api";
import type { Author, HydraCollection } from "../types/api";

export function useAuthors(search: string = "") {
  const params = search ? `?name=${encodeURIComponent(search)}` : "";

  return useQuery({
    enabled: search.length >= 1,
    queryFn: () => apiFetch<HydraCollection<Author>>(`/authors${params}`),
    queryKey: ["authors", search],
  });
}
