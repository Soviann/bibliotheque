import { type QueryKey, useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import {
  type OperationType,
  type ResourceType,
  enqueue,
} from "../services/offlineQueue";

interface UseOfflineMutationOptions<TData, TVariables> {
  mutationFn: (variables: TVariables) => Promise<TData>;
  offlineOperation: OperationType;
  offlineResourceId?: (variables: TVariables) => string | undefined;
  offlineResourceType: ResourceType;
  onOfflineSuccess?: () => void;
  onSuccess?: (data: TData, variables: TVariables) => void;
  queryKeysToInvalidate: QueryKey[];
}

async function registerSync(): Promise<void> {
  if ("serviceWorker" in navigator) {
    const registration = await navigator.serviceWorker.ready;
    if ("sync" in registration) {
      await registration.sync.register("offline-sync");
    }
  }
}

export function useOfflineMutation<TData, TVariables extends Record<string, unknown>>({
  mutationFn,
  offlineOperation,
  offlineResourceId,
  offlineResourceType,
  onOfflineSuccess,
  onSuccess,
  queryKeysToInvalidate,
}: UseOfflineMutationOptions<TData, TVariables>) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (variables: TVariables) => {
      if (!navigator.onLine) {
        await enqueue({
          operation: offlineOperation,
          payload: variables as Record<string, unknown>,
          resourceId: offlineResourceId?.(variables),
          resourceType: offlineResourceType,
        });

        await registerSync();
        toast.info("Opération enregistrée, sera synchronisée au retour en ligne");
        onOfflineSuccess?.();

        return undefined as TData;
      }

      return mutationFn(variables);
    },
    onSuccess: (data, variables) => {
      if (navigator.onLine) {
        for (const key of queryKeysToInvalidate) {
          void queryClient.invalidateQueries({ queryKey: key });
        }
        onSuccess?.(data, variables);
      }
    },
  });
}
