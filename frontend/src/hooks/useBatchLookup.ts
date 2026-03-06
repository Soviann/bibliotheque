import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useCallback, useRef, useState } from "react";
import { apiFetch, fetchSSE } from "../services/api";
import type { BatchLookupProgress, BatchLookupSummary } from "../types/api";

export function useBatchLookupPreview(
  type?: string,
  force: boolean = false,
) {
  const params = new URLSearchParams();
  if (type) params.set("type", type);
  if (force) params.set("force", "true");
  const qs = params.toString();

  return useQuery({
    queryFn: () =>
      apiFetch<{ count: number }>(
        `/tools/batch-lookup/preview${qs ? `?${qs}` : ""}`,
      ),
    queryKey: ["batch-lookup-preview", type ?? "", force],
  });
}

interface BatchLookupState {
  cancel: () => void;
  isRunning: boolean;
  progress: BatchLookupProgress[];
  start: (options: {
    delay?: number;
    force?: boolean;
    limit?: number;
    type?: string;
  }) => void;
  summary: BatchLookupSummary | null;
}

export function useBatchLookup(): BatchLookupState {
  const [isRunning, setIsRunning] = useState(false);
  const [progress, setProgress] = useState<BatchLookupProgress[]>([]);
  const [summary, setSummary] = useState<BatchLookupSummary | null>(null);
  const abortRef = useRef<AbortController | null>(null);
  const queryClient = useQueryClient();

  const start = useCallback(
    (options: {
      delay?: number;
      force?: boolean;
      limit?: number;
      type?: string;
    }) => {
      const controller = new AbortController();
      abortRef.current = controller;

      setIsRunning(true);
      setProgress([]);
      setSummary(null);

      void fetchSSE<BatchLookupProgress, BatchLookupSummary>(
        "/tools/batch-lookup/run",
        options,
        (data) => {
          setProgress((prev) => [...prev, data]);
        },
        (data) => {
          setSummary(data);
          setIsRunning(false);
          abortRef.current = null;
          queryClient.invalidateQueries({ queryKey: ["comics"] });
          queryClient.invalidateQueries({
            queryKey: ["batch-lookup-preview"],
          });
        },
        (error) => {
          setSummary(null);
          setIsRunning(false);
          abortRef.current = null;
          throw error;
        },
        controller.signal,
      );
    },
    [queryClient],
  );

  const cancel = useCallback(() => {
    abortRef.current?.abort();
    setIsRunning(false);
    abortRef.current = null;
  }, []);

  return { cancel, isRunning, progress, start, summary };
}
