import { useQuery } from "@tanstack/react-query";
import { apiFetch } from "../services/api";
import type { LookupResult } from "../types/api";

export function useLookupIsbn(isbn: string, type?: string) {
  const params = new URLSearchParams({ isbn });
  if (type) params.set("type", type);

  return useQuery({
    enabled: isbn.length >= 10,
    queryFn: () => apiFetch<LookupResult>(`/lookup/isbn?${params}`),
    queryKey: ["lookup", "isbn", isbn, type],
  });
}

export function useLookupTitle(title: string, type?: string) {
  const params = new URLSearchParams({ title });
  if (type) params.set("type", type);

  return useQuery({
    enabled: title.length >= 2,
    queryFn: () => apiFetch<LookupResult>(`/lookup/title?${params}`),
    queryKey: ["lookup", "title", title, type],
  });
}
