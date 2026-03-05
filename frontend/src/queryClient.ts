import { QueryClient } from "@tanstack/react-query";
import type { PersistedClient, Persister } from "@tanstack/react-query-persist-client";
import { del, get, set } from "idb-keyval";

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

function createIDBPersister(idbValidKey: IDBValidKey = "reactQuery"): Persister {
  return {
    persistClient: async (client: PersistedClient) => {
      await set(idbValidKey, client);
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
