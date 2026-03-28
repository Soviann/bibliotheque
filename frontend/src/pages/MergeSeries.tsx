import {
  Switch,
  Tab,
  TabGroup,
  TabList,
  TabPanel,
  TabPanels,
} from "@headlessui/react";
import { Loader2, Merge, Search as SearchIcon } from "lucide-react";
import { useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { toast } from "sonner";
import Breadcrumb from "../components/Breadcrumb";
import { queryKeys } from "../queryKeys";
import EmptyState from "../components/EmptyState";
import SelectListbox from "../components/SelectListbox";
import MergeGroupCard from "../components/MergeGroupCard";
import MergePreviewModal from "../components/MergePreviewModal";
import MergeSeriesConfirmModal, { type MergeSeriesEntry } from "../components/MergeSeriesConfirmModal";
import SeriesMultiSelect from "../components/SeriesMultiSelect";
import {
  useDetectMergeGroups,
  useExecuteMerge,
  useMergePreview,
  useMergeSuggest,
} from "../hooks/useMergeSeries";
import { getErrorMessage } from "../services/api";
import type { ComicSeries, HydraCollection, MergeGroup, MergePreview, MergeSuggestion } from "../types/api";
import { type SelectOption, typeOptions } from "../types/enums";

const letterOptions: SelectOption[] = [
  { label: "0-9", value: "0-9" },
  ..."ABCDEFGHIJKLMNOPQRSTUVWXYZ".split("").map((letter) => ({
    label: letter,
    value: letter,
  })),
];

export default function MergeSeries() {
  // Shared state
  const [previewData, setPreviewData] = useState<MergePreview | null>(null);
  const [previewOpen, setPreviewOpen] = useState(false);
  const [suggestion, setSuggestion] = useState<MergeSuggestion | null>(null);

  // Confirmation step state
  const [confirmEntries, setConfirmEntries] = useState<MergeSeriesEntry[]>([]);
  const [confirmOpen, setConfirmOpen] = useState(false);

  // Auto-detect state
  const [selectedType, setSelectedType] = useState("");
  const [selectedLetter, setSelectedLetter] = useState("");
  const [includeChecked, setIncludeChecked] = useState(false);
  const [groups, setGroups] = useState<MergeGroup[]>([]);
  const [hasDetected, setHasDetected] = useState(false);

  // Tab state
  const [selectedTab, setSelectedTab] = useState(0);

  // Manual select state
  const [selectedIds, setSelectedIds] = useState<number[]>([]);

  // Mutations
  const queryClient = useQueryClient();
  const detectMutation = useDetectMergeGroups();
  const previewMutation = useMergePreview();
  const suggestMutation = useMergeSuggest();
  const executeMerge = useExecuteMerge();

  const handleDetect = () => {
    const params: { all?: boolean; startsWith?: string; type?: string } = {};
    if (selectedType) params.type = selectedType;
    if (selectedLetter) params.startsWith = selectedLetter;
    if (includeChecked) params.all = true;

    detectMutation.mutate(params, {
      onError: (error) => {
        toast.error(getErrorMessage(error, "Erreur lors de la détection"));
      },
      onSuccess: (data) => {
        setGroups(data);
        setHasDetected(true);
      },
    });
  };

  const handlePreviewGroup = (group: MergeGroup) => {
    setConfirmEntries(
      group.entries.map((e) => ({ id: e.seriesId, title: e.originalTitle })),
    );
    setConfirmOpen(true);
  };

  const handleSkipGroup = (group: MergeGroup) => {
    setGroups((prev) => prev.filter((g) => g !== group));
  };

  const handleManualPreview = () => {
    const comicsData = queryClient.getQueryData<HydraCollection<ComicSeries>>(queryKeys.comics.all);
    const comics = comicsData?.member ?? [];
    const entries = selectedIds
      .map((id) => {
        const comic = comics.find((c) => c.id === id);
        return comic ? { id: comic.id, title: comic.title } : null;
      })
      .filter((e): e is MergeSeriesEntry => e !== null);
    setConfirmEntries(entries);
    setConfirmOpen(true);
  };

  const handleConfirmSeries = (confirmedIds: number[]) => {
    setConfirmOpen(false);
    setSuggestion(null);

    previewMutation.mutate(confirmedIds, {
      onError: (error) => {
        toast.error(getErrorMessage(error, "Erreur lors de la génération de l'aperçu"));
      },
      onSuccess: (data) => {
        setPreviewData(data);
        setPreviewOpen(true);
      },
    });

    // Lancer les suggestions IA en parallèle
    suggestMutation.mutate(confirmedIds, {
      onSuccess: (data) => {
        setSuggestion(data);
      },
    });
  };

  const handleConfirmMerge = (preview: MergePreview) => {
    executeMerge.mutate(preview, {
      onError: (error) => {
        toast.error(getErrorMessage(error, "Erreur lors de la fusion"));
      },
      onSuccess: () => {
        toast.success("Séries fusionnées avec succès");
        setPreviewOpen(false);
        setPreviewData(null);
        setGroups((prev) =>
          prev.filter(
            (g) =>
              !g.entries.every((e) =>
                preview.sourceSeriesIds.includes(e.seriesId),
              ),
          ),
        );
        setSelectedIds([]);
      },
    });
  };

  return (
    <>
      <div className="mx-auto max-w-4xl px-4 py-6">
        <Breadcrumb items={[{ href: "/tools", label: "Outils" }, { label: "Fusion de séries" }]} />
        <h1 className="font-display text-xl font-bold text-text-primary">
          Fusion de séries
        </h1>

        <TabGroup className="mt-4" onChange={setSelectedTab} selectedIndex={selectedTab}>
          <TabList className="flex gap-1 rounded-lg bg-surface-secondary p-1">
            <Tab className="flex-1 rounded-md px-3 py-2 text-sm font-medium text-text-secondary transition data-[selected]:bg-surface-primary data-[selected]:text-text-primary data-[selected]:shadow-sm">
              Détection automatique
            </Tab>
            <Tab className="flex-1 rounded-md px-3 py-2 text-sm font-medium text-text-secondary transition data-[selected]:bg-surface-primary data-[selected]:text-text-primary data-[selected]:shadow-sm">
              Sélection manuelle
            </Tab>
          </TabList>

          <TabPanels className="mt-4">
            {/* Auto detect tab */}
            <TabPanel>
              <div className="space-y-4">
                {/* Filters row */}
                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                  <div className="min-w-[140px] sm:flex-1">
                    <SelectListbox
                      onChange={setSelectedType}
                      options={typeOptions}
                      placeholder="Type"
                      value={selectedType}
                    />
                  </div>

                  <div className="min-w-[140px] sm:flex-1">
                    <SelectListbox
                      onChange={setSelectedLetter}
                      options={letterOptions}
                      placeholder="Lettre"
                      value={selectedLetter}
                    />
                  </div>

                  <label className="flex items-center gap-2 text-sm text-text-secondary">
                    <Switch
                      checked={includeChecked}
                      className="group relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent bg-surface-tertiary transition-colors data-[checked]:bg-primary-600"
                      onChange={setIncludeChecked}
                    >
                      <span className="pointer-events-none inline-block h-4 w-4 translate-x-0 rounded-full bg-white shadow-sm transition-transform group-data-[checked]:translate-x-4" />
                    </Switch>
                    Inclure les déjà vérifiées
                  </label>
                </div>

                {/* Loading state for preview */}
                {previewMutation.isPending && (
                  <div className="flex items-center justify-center py-8">
                    <Loader2 className="h-6 w-6 animate-spin text-primary-600" />
                    <span className="ml-2 text-sm text-text-secondary">
                      Génération de l'aperçu...
                    </span>
                  </div>
                )}

                {/* Results */}
                {hasDetected && groups.length === 0 && (
                  <EmptyState
                    description="Aucun groupe de séries à fusionner n'a été détecté"
                    icon={Merge}
                    title="Aucun groupe détecté"
                  />
                )}

                {groups.length > 0 && (
                  <div className="space-y-3">
                    {groups.map((group, index) => (
                      <MergeGroupCard
                        group={group}
                        key={`${group.suggestedTitle}-${index}`}
                        onPreview={handlePreviewGroup}
                        onSkip={handleSkipGroup}
                      />
                    ))}
                  </div>
                )}
              </div>
            </TabPanel>

            {/* Manual select tab */}
            <TabPanel>
              <div className="space-y-4">
                <SeriesMultiSelect
                  onSelectionChange={setSelectedIds}
                  selectedIds={selectedIds}
                />
              </div>
            </TabPanel>
          </TabPanels>
        </TabGroup>
      </div>

      {/* Fixed action bar — above bottom nav */}
      <div
        className="fixed inset-x-0 bottom-[var(--bottom-nav-h)] z-40 border-t border-surface-border bg-surface-primary px-4 py-3"
        data-testid="sticky-action-bar"
      >
        <div className="mx-auto flex max-w-4xl justify-center">
          {selectedTab === 0 ? (
            <button
              className="flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
              disabled={detectMutation.isPending || !selectedType || !selectedLetter}
              onClick={handleDetect}
              type="button"
            >
              {detectMutation.isPending ? (
                <Loader2 className="h-4 w-4 animate-spin" />
              ) : (
                <SearchIcon className="h-4 w-4" />
              )}
              Détecter les groupes
            </button>
          ) : (
            <div className="flex items-center gap-3">
              <button
                className="flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                disabled={selectedIds.length < 2 || previewMutation.isPending}
                onClick={handleManualPreview}
                type="button"
              >
                {previewMutation.isPending ? (
                  <Loader2 className="h-4 w-4 animate-spin" />
                ) : (
                  <Merge className="h-4 w-4" />
                )}
                Aperçu de la fusion
              </button>
              {selectedIds.length > 0 && selectedIds.length < 2 && (
                <span className="text-sm text-text-muted">
                  Sélectionnez au moins 2 séries
                </span>
              )}
            </div>
          )}
        </div>
      </div>

      <MergeSeriesConfirmModal
        entries={confirmEntries}
        onClose={() => setConfirmOpen(false)}
        onConfirm={handleConfirmSeries}
        open={confirmOpen}
      />

      <MergePreviewModal
        isExecuting={executeMerge.isPending}
        isSuggesting={suggestMutation.isPending}
        onClose={() => {
          setPreviewOpen(false);
          setPreviewData(null);
          setSuggestion(null);
        }}
        onConfirm={handleConfirmMerge}
        open={previewOpen}
        preview={previewData}
        suggestion={suggestion}
      />
    </>
  );
}
