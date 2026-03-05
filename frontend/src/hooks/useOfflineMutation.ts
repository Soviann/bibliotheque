import { type QueryClient, type QueryKey, useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import {
  type OperationType,
  type ResourceType,
  enqueue,
} from "../services/offlineQueue";

interface UseOfflineMutationOptions<TData, TVariables> {
  generateTempId?: boolean;
  mutationFn: (variables: TVariables) => Promise<TData>;
  offlineContentType?: string;
  offlineHttpMethod?: string;
  offlineOperation: OperationType;
  offlineParentResourceId?: string;
  offlineParentResourceType?: ResourceType;
  offlineResourceId?: (variables: TVariables) => string | undefined;
  offlineResourceType: ResourceType;
  onOfflineSuccess?: () => void;
  onSuccess?: (data: TData, variables: TVariables) => void;
  optimisticRollback?: (queryClient: QueryClient, variables: TVariables) => void;
  optimisticUpdate?: (queryClient: QueryClient, variables: TVariables, tempId?: number) => void;
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
  generateTempId,
  mutationFn,
  offlineContentType,
  offlineHttpMethod,
  offlineOperation,
  offlineParentResourceId,
  offlineParentResourceType,
  offlineResourceId,
  offlineResourceType,
  onOfflineSuccess,
  onSuccess,
  optimisticRollback,
  optimisticUpdate,
  queryKeysToInvalidate,
}: UseOfflineMutationOptions<TData, TVariables>) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (variables: TVariables) => {
      if (!navigator.onLine) {
        let tempId: number | undefined;
        let resourceId = offlineResourceId?.(variables);

        if (generateTempId) {
          tempId = -(Date.now() + Math.floor(Math.random() * 10000));
          resourceId = String(tempId);
        }

        await enqueue({
          contentType: offlineContentType,
          httpMethod: offlineHttpMethod,
          operation: offlineOperation,
          parentResourceId: offlineParentResourceId,
          parentResourceType: offlineParentResourceType,
          payload: variables as Record<string, unknown>,
          resourceId,
          resourceType: offlineResourceType,
        });

        optimisticUpdate?.(queryClient, variables, tempId);

        await registerSync();
        toast.info("Opération enregistrée, sera synchronisée au retour en ligne");
        onOfflineSuccess?.();

        return undefined as TData;
      }

      return mutationFn(variables);
    },
    onError: (_error, variables) => {
      if (!navigator.onLine) {
        optimisticRollback?.(queryClient, variables);
      }
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
