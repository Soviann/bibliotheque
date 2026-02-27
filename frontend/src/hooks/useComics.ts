import { useQuery } from "@tanstack/react-query";
import { apiFetch } from "../services/api";
import type { ComicSeries, HydraCollection } from "../types/api";

interface UseComicsParams {
  isOneShot?: boolean;
  page?: number;
  search?: string;
  status?: string;
  type?: string;
}

export function useComics(params: UseComicsParams = {}) {
  const searchParams = new URLSearchParams();

  if (params.page && params.page > 1) searchParams.set("page", String(params.page));
  if (params.status) searchParams.set("status", params.status);
  if (params.type) searchParams.set("type", params.type);
  if (params.search) searchParams.set("title", params.search);
  if (params.isOneShot !== undefined) searchParams.set("isOneShot", String(params.isOneShot));

  const query = searchParams.toString();
  const path = `/comic_series${query ? `?${query}` : ""}`;

  return useQuery({
    queryFn: () => apiFetch<HydraCollection<ComicSeries>>(path),
    queryKey: ["comics", params],
  });
}
