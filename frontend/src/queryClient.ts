import { QueryClient } from "@tanstack/react-query";
import type {
  PersistedClient,
  Persister,
} from "@tanstack/react-query-persist-client";
import { del, get, set } from "idb-keyval";

// Safari < 16.4 fallback
const scheduleIdle: typeof requestIdleCallback =
  typeof requestIdleCallback === "function"
    ? requestIdleCallback
    : (cb) => setTimeout(cb, 1) as unknown as number;

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

function createIDBPersister(
  idbValidKey: IDBValidKey = "reactQuery",
): Persister {
  return {
    persistClient: (client: PersistedClient) => {
      // Schedule off main thread to avoid blocking renders.
      // JSON round-trip strips non-cloneable values (Promises from TanStack Query v5 suspense)
      // that would cause DataCloneError with IndexedDB's structured clone algorithm.
      return new Promise<void>((resolve, reject) => {
        scheduleIdle(() => {
          const cleaned = JSON.parse(JSON.stringify(client)) as PersistedClient;
          set(idbValidKey, cleaned).then(resolve, reject);
        });
      });
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
