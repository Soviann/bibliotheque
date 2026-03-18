import { QueryClient } from "@tanstack/react-query";
import type { PersistedClient, Persister } from "@tanstack/react-query-persist-client";
import { del, get, set } from "idb-keyval";

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      gcTime: 60 * 60 * 1000,
      networkMode: "offlineFirst",
      refetchOnWindowFocus: true,
      retry: 1,
      staleTime: 5 * 60 * 1000,
    },
    mutations: {
      networkMode: "offlineFirst",
    },
  },
});

function createIDBPersister(idbValidKey: IDBValidKey = "reactQuery"): Persister {
  return {
    persistClient: async (client: PersistedClient) => {
      // JSON round-trip strips non-cloneable values (Promises from TanStack Query v5 suspense)
      // that would cause DataCloneError with IndexedDB's structured clone algorithm
      await set(idbValidKey, JSON.parse(JSON.stringify(client)) as PersistedClient);
    },
    removeClient: async () => {
      await del(idbValidKey);
    },
    restoreClient: async () => {
      return await get<PersistedClient>(idbValidKey);
    },
  };
}

export const persister = createIDBPersister("bibliotheque-query-cache");
