import { useMutation, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "../services/api";
import type { ImportBooksResult, ImportExcelResult } from "../types/api";

function buildFormData(file: File, dryRun: boolean): FormData {
  const formData = new FormData();
  formData.append("file", file);
  formData.append("dryRun", String(dryRun));
  return formData;
}

export function useImportExcel() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ dryRun, file }: { dryRun: boolean; file: File }) =>
      apiFetch<ImportExcelResult>("/tools/import/excel", {
        body: buildFormData(file, dryRun),
        method: "POST",
      }),
    onSuccess: (_, variables) => {
      if (!variables.dryRun) {
        queryClient.invalidateQueries({ queryKey: ["comics"] });
      }
    },
  });
}

export function useImportBooks() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ dryRun, file }: { dryRun: boolean; file: File }) =>
      apiFetch<ImportBooksResult>("/tools/import/books", {
        body: buildFormData(file, dryRun),
        method: "POST",
      }),
    onSuccess: (_, variables) => {
      if (!variables.dryRun) {
        queryClient.invalidateQueries({ queryKey: ["comics"] });
      }
    },
  });
}
