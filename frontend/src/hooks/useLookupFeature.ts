import type { UseQueryResult } from "@tanstack/react-query";
import { useState } from "react";
import { toast } from "sonner";
import { fetchLookupTitle, useLookupIsbn, useLookupTitle, useLookupTitleCandidates } from "./useLookup";
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
  titleCandidates: UseQueryResult<LookupCandidatesResponse>;
}

export function useLookupFeature(
  form: FormData,
  update: <K extends keyof FormData>(key: K, value: FormData[K]) => void,
): LookupFeature {
  const [isApplying, setIsApplying] = useState(false);
  const [lookupIsbn, setLookupIsbn] = useState("");
  const [lookupTitle, setLookupTitle] = useState("");
  const [lookupMode, setLookupMode] = useState<"isbn" | "title">("title");
  const [selectedCandidateTitle, setSelectedCandidateTitle] = useState<string | null>(null);

  const isbnLookup = useLookupIsbn(lookupMode === "isbn" ? lookupIsbn : "", form.type);
  const titleCandidates = useLookupTitleCandidates(lookupMode === "title" ? lookupTitle : "", form.type);
  const targetedLookup = useLookupTitle(selectedCandidateTitle ?? "", form.type);
  const lookupResult = lookupMode === "isbn" ? isbnLookup : targetedLookup;

  const applySeriesFields = (result: LookupResult) => {
    update("coverUrl", result.thumbnail ?? form.coverUrl);
    update("description", result.description ?? form.description);
    update("isOneShot", result.isOneShot || form.isOneShot);
    update("latestPublishedIssue", result.latestPublishedIssue?.toString() ?? form.latestPublishedIssue);
    update("publishedDate", result.publishedDate ?? form.publishedDate);
    update("lookupCompletedAt", new Date().toISOString());
    update("publisher", result.publisher ?? form.publisher);
    update("title", result.title || form.title);

    if (result.authors) {
      const authorNames = result.authors.split(",").map((n) => n.trim()).filter(Boolean);
      update(
        "authors",
        authorNames.map((name, i) => ({ "@id": "", followedForNewSeries: false, id: -(i + 1), name })),
      );
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

    if (lookupMode === "isbn" && result.title) {
      setIsApplying(true);
      try {
        const titleResult = await fetchLookupTitle(result.title, form.type);
        applySeriesFields(titleResult);
        toast.success("Informations récupérées (ISBN → titre)");
      } catch {
        applySeriesFields(result);
        toast.success("Informations récupérées (ISBN uniquement)");
      } finally {
        setIsApplying(false);
      }
    } else {
      applySeriesFields(result);
      setSelectedCandidateTitle(null);
      toast.success("Informations récupérées");
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
    titleCandidates,
  };
}
