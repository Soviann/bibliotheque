import type { UseQueryResult } from "@tanstack/react-query";
import { useState } from "react";
import { toast } from "sonner";
import {
  fetchLookupTitle,
  useLookupIsbn,
  useLookupTitle,
  useLookupTitleCandidates,
} from "./useLookup";
import type { FormData } from "./useComicForm";
import type { LookupCandidatesResponse, LookupResult } from "../types/api";

export interface LookupFeature {
  applyLookup: () => Promise<void>;
  clearCandidate: () => void;
  isApplying: boolean;
  lookupIsbn: string;
  lookupMode: "isbn" | "title";
  lookupResult: UseQueryResult<LookupResult>;
  lookupTitle: string;
  selectCandidate: (title: string) => void;
  selectedCandidateTitle: string | null;
  setLookupIsbn: (v: string) => void;
  setLookupMode: (v: "isbn" | "title") => void;
  setLookupTitle: (v: string) => void;
  submitTitleSearch: () => void;
  titleCandidates: UseQueryResult<LookupCandidatesResponse>;
}

export function useLookupFeature(
  form: FormData,
  update: <K extends keyof FormData>(key: K, value: FormData[K]) => void,
): LookupFeature {
  const [isApplying, setIsApplying] = useState(false);
  const [lookupIsbn, setLookupIsbn] = useState("");
  const [lookupTitle, setLookupTitle] = useState("");
  const [submittedTitle, setSubmittedTitle] = useState("");
  const [lookupMode, setLookupMode] = useState<"isbn" | "title">("title");
  const [selectedCandidateTitle, setSelectedCandidateTitle] = useState<
    string | null
  >(null);

  const isbnLookup = useLookupIsbn(
    lookupMode === "isbn" ? lookupIsbn : "",
    form.type,
  );
  const titleCandidates = useLookupTitleCandidates(
    lookupMode === "title" ? submittedTitle : "",
    form.type,
  );
  const targetedLookup = useLookupTitle(
    selectedCandidateTitle ?? "",
    form.type,
  );
  const lookupResult = lookupMode === "isbn" ? isbnLookup : targetedLookup;

  const applySeriesFields = (result: LookupResult) => {
    update("coverUrl", result.thumbnail ?? form.coverUrl);
    update("description", result.description ?? form.description);
    update("isOneShot", result.isOneShot || form.isOneShot);
    update(
      "latestPublishedIssue",
      result.latestPublishedIssue?.toString() ?? form.latestPublishedIssue,
    );
    update("publishedDate", result.publishedDate ?? form.publishedDate);
    update("lookupCompletedAt", new Date().toISOString());
    update("publisher", result.publisher ?? form.publisher);
    update("title", result.title || form.title);

    if (result.authors) {
      const authorNames = result.authors
        .split(",")
        .map((n) => n.trim())
        .filter(Boolean);
      update(
        "authors",
        authorNames.map((name, i) => ({
          "@id": "",
          followedForNewSeries: false,
          id: -(i + 1),
          name,
        })),
      );
    }
  };

  /**
   * Applique uniquement les champs de niveau série pour une série multi-tomes.
   * Un ISBN identifie un tome précis : son titre, sa couverture, sa description
   * et sa date sont propres au tome et ne doivent jamais écraser la fiche série.
   */
  const applySeriesLevelFields = (
    isbnResult: LookupResult,
    seriesResult: LookupResult,
  ) => {
    update("isOneShot", false);

    const latestPublishedIssue =
      seriesResult.latestPublishedIssue ?? isbnResult.latestPublishedIssue;
    if (latestPublishedIssue !== null) {
      update("latestPublishedIssue", latestPublishedIssue.toString());
    }

    update("lookupCompletedAt", new Date().toISOString());
  };

  const submitTitleSearch = () => {
    const trimmed = lookupTitle.trim();
    if (trimmed.length >= 2) {
      setSubmittedTitle(trimmed);
    }
  };

  const selectCandidate = (title: string) => {
    setSelectedCandidateTitle(title);
  };

  const clearCandidate = () => {
    setSelectedCandidateTitle(null);
  };

  const applyLookup = async () => {
    const result = lookupResult.data;
    if (!result) return;

    if (lookupMode !== "isbn") {
      applySeriesFields(result);
      setSelectedCandidateTitle(null);
      toast.success("Informations récupérées");
      return;
    }

    // Un ISBN identifie un album précis :
    //  - one-shot   → l'album EST la série → on applique tout (titre inclus)
    //  - multi-tomes → l'album est un tome → on n'écrase jamais la fiche série
    //  - indéterminé → on n'applique rien (le titre du tome corromprait la série)
    setIsApplying(true);
    try {
      let isOneShot = result.isOneShot;
      let seriesResult = result;

      // BNF/Google Books ne renseignent pas isOneShot : on lève le doute via un
      // lookup par titre (qui résout généralement le statut one-shot).
      if (isOneShot === null && result.title) {
        try {
          seriesResult = await fetchLookupTitle(result.title, form.type);
          isOneShot = seriesResult.isOneShot;
        } catch {
          // Statut toujours indéterminé : traité ci-dessous.
        }
      }

      if (isOneShot === true) {
        applySeriesFields(seriesResult);
        toast.success("Informations récupérées (one-shot)");
      } else if (isOneShot === false) {
        applySeriesLevelFields(result, seriesResult);
        toast.warning(
          "Série multi-tomes : titre conservé. Utilisez la recherche par titre pour compléter la fiche.",
        );
      } else {
        toast.error(
          "Impossible de déterminer la série depuis cet ISBN. Utilisez la recherche par titre.",
        );
      }
    } finally {
      setIsApplying(false);
    }
  };

  return {
    applyLookup,
    clearCandidate,
    isApplying,
    lookupIsbn,
    lookupMode,
    lookupResult,
    lookupTitle,
    selectCandidate,
    selectedCandidateTitle,
    setLookupIsbn,
    setLookupMode,
    setLookupTitle,
    submitTitleSearch,
    titleCandidates,
  };
}
