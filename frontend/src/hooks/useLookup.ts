import { useQuery } from "@tanstack/react-query";
import { endpoints } from "../endpoints";
import { queryKeys } from "../queryKeys";
import { apiFetch } from "../services/api";
import type { LookupCandidatesResponse, LookupResult } from "../types/api";

export function useLookupIsbn(isbn: string, type?: string) {
  const params = new URLSearchParams({ isbn });
  if (type) params.set("type", type);

  return useQuery({
    enabled: isbn.length >= 10,
    queryFn: () => apiFetch<LookupResult>(`${endpoints.lookup.isbn}?${params}`),
    queryKey: queryKeys.lookup.isbn(isbn, type),
  });
}

export function useLookupTitle(title: string, type?: string) {
  const params = new URLSearchParams({ title });
  if (type) params.set("type", type);

  return useQuery({
    enabled: title.length >= 2,
    queryFn: () => apiFetch<LookupResult>(`${endpoints.lookup.title}?${params}`),
    queryKey: queryKeys.lookup.title(title, type),
  });
}

export function useLookupTitleCandidates(title: string, type?: string, limit = 5) {
  const params = new URLSearchParams({ limit: String(limit), title });
  if (type) params.set("type", type);

  return useQuery({
    enabled: title.length >= 2,
    queryFn: () => apiFetch<LookupCandidatesResponse>(`${endpoints.lookup.title}?${params}`),
    queryKey: queryKeys.lookup.titleCandidates(title, type, limit),
  });
}

/** Appel impératif — lookup par ISBN */
export async function fetchLookupIsbn(isbn: string, type?: string): Promise<LookupResult> {
  const params = new URLSearchParams({ isbn });
  if (type) params.set("type", type);
  return apiFetch<LookupResult>(`${endpoints.lookup.isbn}?${params}`);
}

/** Appel impératif — lookup par titre */
export async function fetchLookupTitle(title: string, type?: string): Promise<LookupResult> {
  const params = new URLSearchParams({ title });
  if (type) params.set("type", type);
  return apiFetch<LookupResult>(`${endpoints.lookup.title}?${params}`);
}
