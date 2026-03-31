import { useEffect, useMemo, useReducer, useState } from "react";
import type { MergePreview, MergePreviewTome, MergeSuggestion } from "../types/api";

export interface MergeFormState {
  amazonUrl: string;
  authors: string;
  coverUrl: string;
  defaultTomeBought: boolean;
  defaultTomeOnNas: boolean;
  defaultTomeRead: boolean;
  description: string;
  isOneShot: boolean;
  latestPublishedIssue: string;
  latestPublishedIssueComplete: boolean;
  notInterestedBuy: boolean;
  notInterestedNas: boolean;
  publishedDate: string;
  publisher: string;
  status: string;
  title: string;
  tomes: MergePreviewTome[];
  type: string;
}

type MergeFormAction =
  | { type: "INIT"; preview: MergePreview }
  | { type: "APPLY_SUGGESTION"; preview: MergePreview; suggestion: MergeSuggestion }
  | { type: "SET_FIELD"; field: keyof MergeFormState; value: MergeFormState[keyof MergeFormState] }
  | { type: "UPDATE_TOME"; index: number; patch: Partial<MergePreviewTome> }
  | { type: "REMOVE_TOME"; index: number }
  | { type: "ADD_TOME" };

export type { MergeFormAction };

function initState(preview: MergePreview): MergeFormState {
  return {
    amazonUrl: preview.amazonUrl ?? "",
    authors: preview.authors.join(", "),
    coverUrl: preview.coverUrl ?? "",
    defaultTomeBought: preview.defaultTomeBought,
    defaultTomeOnNas: preview.defaultTomeOnNas,
    defaultTomeRead: preview.defaultTomeRead,
    description: preview.description ?? "",
    isOneShot: preview.isOneShot,
    latestPublishedIssue: preview.latestPublishedIssue?.toString() ?? "",
    latestPublishedIssueComplete: preview.latestPublishedIssueComplete,
    notInterestedBuy: preview.notInterestedBuy,
    notInterestedNas: preview.notInterestedNas,
    publishedDate: preview.publishedDate ?? "",
    publisher: preview.publisher ?? "",
    status: preview.status,
    title: preview.title,
    tomes: preview.tomes.map((t) => ({ ...t })),
    type: preview.type,
  };
}

function reducer(state: MergeFormState, action: MergeFormAction): MergeFormState {
  switch (action.type) {
    case "INIT":
      return initState(action.preview);

    case "APPLY_SUGGESTION": {
      const tomeNumberMap = new Map(
        action.suggestion.entries.map((e) => [e.id, e.tomeNumber]),
      );
      const newTomes = action.preview.tomes.map((tome, index) => {
        const seriesId = action.preview.sourceSeriesIds[index];
        const suggestedNumber = seriesId !== undefined ? tomeNumberMap.get(seriesId) : undefined;
        return { ...tome, number: suggestedNumber ?? tome.number };
      });
      newTomes.sort((a, b) => a.number - b.number);
      return { ...state, title: action.suggestion.title, tomes: newTomes };
    }

    case "SET_FIELD":
      return { ...state, [action.field]: action.value };

    case "UPDATE_TOME":
      return {
        ...state,
        tomes: state.tomes.map((t, i) =>
          i === action.index ? { ...t, ...action.patch } : t,
        ),
      };

    case "REMOVE_TOME":
      return {
        ...state,
        tomes: state.tomes.filter((_, i) => i !== action.index),
      };

    case "ADD_TOME": {
      const maxNumber = state.tomes.reduce((max, t) => Math.max(max, t.number, t.tomeEnd ?? 0), 0);
      return {
        ...state,
        tomes: [
          ...state.tomes,
          {
            bought: false,
            isbn: null,
            number: maxNumber + 1,
            onNas: false,
            read: false,
            title: null,
            tomeEnd: null,
          },
        ],
      };
    }
  }
}

const emptyState: MergeFormState = {
  amazonUrl: "",
  authors: "",
  coverUrl: "",
  defaultTomeBought: false,
  defaultTomeOnNas: false,
  defaultTomeRead: false,
  description: "",
  isOneShot: false,
  latestPublishedIssue: "",
  latestPublishedIssueComplete: false,
  notInterestedBuy: false,
  notInterestedNas: false,
  publishedDate: "",
  publisher: "",
  status: "buying",
  title: "",
  tomes: [],
  type: "bd",
};

export function useMergePreviewForm(
  preview: MergePreview | null,
  suggestion?: MergeSuggestion | null,
) {
  const [state, dispatch] = useReducer(reducer, emptyState);
  const [suggestionApplied, setSuggestionApplied] = useState(false);

  // Sync state when preview changes
  useEffect(() => {
    if (preview) {
      dispatch({ type: "INIT", preview });
      setSuggestionApplied(false);
    }
  }, [preview]);

  // Apply AI suggestions when they arrive
  useEffect(() => {
    if (!suggestion || suggestionApplied || !preview) return;
    dispatch({ type: "APPLY_SUGGESTION", preview, suggestion });
    setSuggestionApplied(true);
  }, [suggestion, suggestionApplied, preview]);

  const duplicateNumbers = useMemo(() => {
    const counts = new Map<number, number>();
    for (const t of state.tomes) {
      counts.set(t.number, (counts.get(t.number) ?? 0) + 1);
    }
    const dupes = new Set<number>();
    for (const [num, count] of counts) {
      if (count > 1) dupes.add(num);
    }
    return dupes;
  }, [state.tomes]);

  const hasDuplicates = duplicateNumbers.size > 0;

  const buildConfirmPayload = (basePreview: MergePreview): MergePreview => {
    const authors = state.authors
      .split(",")
      .map((a) => a.trim())
      .filter(Boolean);
    return {
      ...basePreview,
      amazonUrl: state.amazonUrl || null,
      authors,
      coverUrl: state.coverUrl || null,
      defaultTomeBought: state.defaultTomeBought,
      defaultTomeOnNas: state.defaultTomeOnNas,
      defaultTomeRead: state.defaultTomeRead,
      description: state.description || null,
      isOneShot: state.isOneShot,
      latestPublishedIssue: state.latestPublishedIssue ? Number(state.latestPublishedIssue) : null,
      latestPublishedIssueComplete: state.latestPublishedIssueComplete,
      notInterestedBuy: state.notInterestedBuy,
      notInterestedNas: state.notInterestedNas,
      publishedDate: state.publishedDate || null,
      publisher: state.publisher || null,
      status: state.status,
      title: state.title || basePreview.title,
      tomes: state.tomes,
      type: state.type,
    };
  };

  return {
    buildConfirmPayload,
    dispatch,
    duplicateNumbers,
    hasDuplicates,
    state,
  };
}
