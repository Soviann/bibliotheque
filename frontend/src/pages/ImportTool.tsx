import {
  Tab,
  TabGroup,
  TabList,
  TabPanel,
  TabPanels,
} from "@headlessui/react";
import { CheckCircle, Loader2, Upload } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";
import FileDropZone from "../components/FileDropZone";
import { useImportBooks, useImportExcel } from "../hooks/useImport";
import type { ImportBooksResult, ImportExcelResult } from "../types/api";

function ExcelTab() {
  const [file, setFile] = useState<File | null>(null);
  const [dryRun, setDryRun] = useState(true);
  const [result, setResult] = useState<ImportExcelResult | null>(null);
  const importExcel = useImportExcel();

  const handleImport = () => {
    if (!file) return;

    importExcel.mutate(
      { dryRun, file },
      {
        onSuccess: (data) => {
          setResult(data);
          if (!dryRun) {
            toast.success(
              `${data.totalCreated} creee(s), ${data.totalUpdated} mise(s) a jour, ${data.totalTomes} nouveau(x) tome(s)`,
            );
          }
        },
      },
    );
  };

  return (
    <div className="space-y-4">
      <FileDropZone onFileSelect={setFile} />

      <div className="flex items-center gap-4">
        <label className="flex items-center gap-2 text-sm text-text-secondary">
          <input
            checked={dryRun}
            className="rounded border-surface-border text-primary-600 focus:ring-primary-500"
            onChange={(e) => {
              setDryRun(e.target.checked);
              setResult(null);
            }}
            type="checkbox"
          />
          Simulation (dry run)
        </label>

        <button
          className="flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
          disabled={!file || importExcel.isPending}
          onClick={handleImport}
          type="button"
        >
          {importExcel.isPending ? (
            <Loader2 className="h-4 w-4 animate-spin" />
          ) : (
            <Upload className="h-4 w-4" />
          )}
          {dryRun ? "Simuler" : "Importer"}
        </button>
      </div>

      {result && (
        <div className="rounded-lg border border-surface-border bg-surface-secondary p-4">
          <div className="flex items-center gap-2 text-sm font-medium text-text-primary">
            <CheckCircle className="h-4 w-4 text-green-600" />
            {dryRun ? "Simulation terminee" : "Import termine"}
          </div>
          <dl className="mt-2 grid grid-cols-2 gap-2 text-sm">
            <dt className="text-text-secondary">Creees</dt>
            <dd className="text-text-primary">{result.totalCreated}</dd>
            <dt className="text-text-secondary">Mises a jour</dt>
            <dd className="text-text-primary">{result.totalUpdated}</dd>
            <dt className="text-text-secondary">Nouveaux tomes</dt>
            <dd className="text-text-primary">{result.totalTomes}</dd>
          </dl>
          {Object.keys(result.sheetDetails).length > 0 && (
            <div className="mt-3 space-y-1">
              <p className="text-xs font-medium text-text-secondary">
                Detail par onglet
              </p>
              {Object.entries(result.sheetDetails).map(([sheet, details]) => (
                <p className="text-xs text-text-secondary" key={sheet}>
                  {sheet}: {details.created} creee(s), {details.updated}{" "}
                  maj, {details.tomes} nouveau(x) tome(s)
                </p>
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  );
}

function BooksTab() {
  const [file, setFile] = useState<File | null>(null);
  const [dryRun, setDryRun] = useState(true);
  const [result, setResult] = useState<ImportBooksResult | null>(null);
  const importBooks = useImportBooks();

  const handleImport = () => {
    if (!file) return;

    importBooks.mutate(
      { dryRun, file },
      {
        onSuccess: (data) => {
          setResult(data);
          if (!dryRun) {
            toast.success(
              `${data.created} cree(s), ${data.enriched} enrichi(s)`,
            );
          }
        },
      },
    );
  };

  return (
    <div className="space-y-4">
      <FileDropZone onFileSelect={setFile} />

      <div className="flex items-center gap-4">
        <label className="flex items-center gap-2 text-sm text-text-secondary">
          <input
            checked={dryRun}
            className="rounded border-surface-border text-primary-600 focus:ring-primary-500"
            onChange={(e) => {
              setDryRun(e.target.checked);
              setResult(null);
            }}
            type="checkbox"
          />
          Simulation (dry run)
        </label>

        <button
          className="flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
          disabled={!file || importBooks.isPending}
          onClick={handleImport}
          type="button"
        >
          {importBooks.isPending ? (
            <Loader2 className="h-4 w-4 animate-spin" />
          ) : (
            <Upload className="h-4 w-4" />
          )}
          {dryRun ? "Simuler" : "Importer"}
        </button>
      </div>

      {result && (
        <div className="rounded-lg border border-surface-border bg-surface-secondary p-4">
          <div className="flex items-center gap-2 text-sm font-medium text-text-primary">
            <CheckCircle className="h-4 w-4 text-green-600" />
            {dryRun ? "Simulation terminee" : "Import termine"}
          </div>
          <dl className="mt-2 grid grid-cols-2 gap-2 text-sm">
            <dt className="text-text-secondary">Groupes</dt>
            <dd className="text-text-primary">{result.groupCount}</dd>
            <dt className="text-text-secondary">Crees</dt>
            <dd className="text-text-primary">{result.created}</dd>
            <dt className="text-text-secondary">Enrichis</dt>
            <dd className="text-text-primary">{result.enriched}</dd>
          </dl>
        </div>
      )}
    </div>
  );
}

export default function ImportTool() {
  return (
    <div className="mx-auto max-w-4xl px-4 py-6">
      <h1 className="text-xl font-bold text-text-primary">Import Excel</h1>

      <TabGroup className="mt-4">
        <TabList className="flex gap-1 rounded-lg bg-surface-secondary p-1">
          <Tab className="flex-1 rounded-md px-3 py-2 text-sm font-medium text-text-secondary transition data-[selected]:bg-surface-primary data-[selected]:text-text-primary data-[selected]:shadow-sm">
            Excel suivi
          </Tab>
          <Tab className="flex-1 rounded-md px-3 py-2 text-sm font-medium text-text-secondary transition data-[selected]:bg-surface-primary data-[selected]:text-text-primary data-[selected]:shadow-sm">
            Livres
          </Tab>
        </TabList>

        <TabPanels className="mt-4">
          <TabPanel>
            <ExcelTab />
          </TabPanel>
          <TabPanel>
            <BooksTab />
          </TabPanel>
        </TabPanels>
      </TabGroup>
    </div>
  );
}
