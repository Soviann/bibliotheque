import { QueryClient } from "@tanstack/react-query";

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      gcTime: 60 * 60 * 1000,
      networkMode: "offlineFirst",
      refetchOnWindowFocus: false,
      retry: 1,
      staleTime: 30 * 60 * 1000,
    },
    mutations: {
      networkMode: "offlineFirst",
    },
  },
});
