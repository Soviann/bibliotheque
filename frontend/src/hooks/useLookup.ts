import { useQuery } from "@tanstack/react-query";
import { apiFetch } from "../services/api";
import type { LookupCandidatesResponse, LookupResult } from "../types/api";

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

export function useLookupTitleCandidates(title: string, type?: string, limit = 5) {
  const params = new URLSearchParams({ limit: String(limit), title });
  if (type) params.set("type", type);

  return useQuery({
    enabled: title.length >= 2,
    queryFn: () => apiFetch<LookupCandidatesResponse>(`/lookup/title?${params}`),
    queryKey: ["lookup", "title-candidates", title, type, limit],
  });
}

/** Appel impératif — lookup par ISBN */
export async function fetchLookupIsbn(isbn: string, type?: string): Promise<LookupResult> {
  const params = new URLSearchParams({ isbn });
  if (type) params.set("type", type);
  return apiFetch<LookupResult>(`/lookup/isbn?${params}`);
}

/** Appel impératif — lookup par titre */
export async function fetchLookupTitle(title: string, type?: string): Promise<LookupResult> {
  const params = new URLSearchParams({ title });
  if (type) params.set("type", type);
  return apiFetch<LookupResult>(`/lookup/title?${params}`);
}
