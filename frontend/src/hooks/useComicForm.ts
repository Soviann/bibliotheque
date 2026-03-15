import { useEffect, useState } from "react";
import { useNavigate, useParams, useSearchParams } from "react-router-dom";
import { toast } from "sonner";
import { useAuthors } from "./useAuthors";
import { useOnlineStatus } from "./useOnlineStatus";
import { useComic } from "./useComic";
import { useCreateComic } from "./useCreateComic";
import { fetchLookupIsbn, fetchLookupTitle, useLookupIsbn, useLookupTitle, useLookupTitleCandidates } from "./useLookup";
import { useSyncFailures } from "./useSyncFailures";
import { useUpdateComic } from "./useUpdateComic";
import { apiFetch } from "../services/api";
import type { Author, ComicSeries } from "../types/api";
import { ComicStatus, ComicType } from "../types/enums";

export interface TomeFormData {
  bought: boolean;
  downloaded: boolean;
  id?: number;
  isHorsSerie: boolean;
  isbn: string;
  number: number;
  onNas: boolean;
  read: boolean;
  title: string;
  tomeEnd: string;
}

export interface FormData {
  authors: Author[];
  coverUrl: string;
  defaultTomeBought: boolean;
  defaultTomeDownloaded: boolean;
  defaultTomeRead: boolean;
  description: string;
  isOneShot: boolean;
  latestPublishedIssue: string;
  latestPublishedIssueComplete: boolean;
  publishedDate: string;
  publisher: string;
  status: string;
  title: string;
  tomes: TomeFormData[];
  type: string;
}

export function compareTomes(a: TomeFormData, b: TomeFormData): number {
  if (a.isHorsSerie !== b.isHorsSerie) return a.isHorsSerie ? 1 : -1;
  return a.number - b.number;
}

function emptyTome(number: number, isHorsSerie = false): TomeFormData {
  return { bought: false, downloaded: false, isHorsSerie, isbn: "", number, onNas: false, read: false, title: "", tomeEnd: "" };
}

function buildInitialForm(comic?: ComicSeries): FormData {
  if (comic) {
    return {
      authors: comic.authors,
      coverUrl: comic.coverUrl ?? "",
      defaultTomeBought: comic.defaultTomeBought,
      defaultTomeDownloaded: comic.defaultTomeDownloaded,
      defaultTomeRead: comic.defaultTomeRead,
      description: comic.description ?? "",
      isOneShot: comic.isOneShot,
      latestPublishedIssue: comic.latestPublishedIssue?.toString() ?? "",
      latestPublishedIssueComplete: comic.latestPublishedIssueComplete,
      publishedDate: comic.publishedDate ?? "",
      publisher: comic.publisher ?? "",
      status: comic.status,
      title: comic.title,
      tomes: comic.tomes.map((t) => ({
        bought: t.bought,
        downloaded: t.downloaded,
        id: t.id,
        isHorsSerie: t.isHorsSerie,
        isbn: t.isbn ?? "",
        number: t.number,
        onNas: t.onNas,
        read: t.read,
        title: t.title ?? "",
        tomeEnd: t.tomeEnd?.toString() ?? "",
      })),
      type: comic.type,
    };
  }
  return {
    authors: [],
    coverUrl: "",
    defaultTomeBought: false,
    defaultTomeDownloaded: false,
    defaultTomeRead: false,
    description: "",
    isOneShot: false,
    latestPublishedIssue: "",
    latestPublishedIssueComplete: false,
    publishedDate: "",
    publisher: "",
    status: ComicStatus.BUYING,
    title: "",
    tomes: [emptyTome(1)],
    type: ComicType.BD,
  };
}

const maxBatchSize = 100;

export function useComicForm() {
  const { id } = useParams<{ id: string }>();
  const [searchParams] = useSearchParams();
  const syncFailureId = searchParams.get("syncFailureId");
  const isEdit = Boolean(id);
  const navigate = useNavigate();
  const isOnline = useOnlineStatus();
  const { data: comic } = useComic(id ? Number(id) : undefined);
  const createComic = useCreateComic();
  const updateComic = useUpdateComic();
  const { failures, resolveSyncFailure } = useSyncFailures();
  const syncFailure = syncFailureId ? failures.find((f) => f.id === Number(syncFailureId)) : undefined;

  const [form, setForm] = useState<FormData>(buildInitialForm());
  const [initialized, setInitialized] = useState(!isEdit);

  // Lookup state
  const [isApplying, setIsApplying] = useState(false);
  const [lookupIsbn, setLookupIsbn] = useState("");
  const [lookupTitle, setLookupTitle] = useState("");
  const [lookupMode, setLookupMode] = useState<"isbn" | "title">("title");
  const [selectedCandidateTitle, setSelectedCandidateTitle] = useState<string | null>(null);
  const [tomeLookupLoading, setTomeLookupLoading] = useState<number | null>(null);

  // Cover search state
  const [coverSearchOpen, setCoverSearchOpen] = useState(false);

  // Batch add state
  const [batchFrom, setBatchFrom] = useState(1);
  const [batchTo, setBatchTo] = useState(1);

  const isbnLookup = useLookupIsbn(lookupMode === "isbn" ? lookupIsbn : "", form.type);
  const titleCandidates = useLookupTitleCandidates(lookupMode === "title" ? lookupTitle : "", form.type);
  const targetedLookup = useLookupTitle(selectedCandidateTitle ?? "", form.type);
  const lookupResult = lookupMode === "isbn" ? isbnLookup : targetedLookup;

  // Author autocomplete
  const [authorSearch, setAuthorSearch] = useState("");
  const { data: authorResults } = useAuthors(authorSearch);
  const authorOptions = authorResults?.member ?? [];

  // Initialize form with comic data on edit
  useEffect(() => {
    if (isEdit && comic && !initialized) {
      setForm(buildInitialForm(comic));
      setInitialized(true);
    }
  }, [comic, isEdit, initialized]);

  const update = <K extends keyof FormData>(key: K, value: FormData[K]) => {
    setForm((prev) => ({ ...prev, [key]: value }));
  };

  const applySeriesFields = (result: import("../types/api").LookupResult) => {
    setForm((prev) => ({
      ...prev,
      coverUrl: result.thumbnail ?? prev.coverUrl,
      description: result.description ?? prev.description,
      isOneShot: result.isOneShot || prev.isOneShot,
      latestPublishedIssue: result.latestPublishedIssue?.toString() ?? prev.latestPublishedIssue,
      publishedDate: result.publishedDate ?? prev.publishedDate,
      publisher: result.publisher ?? prev.publisher,
      title: result.title || prev.title,
    }));

    if (result.authors) {
      const authorNames = result.authors.split(",").map((n) => n.trim()).filter(Boolean);
      update(
        "authors",
        authorNames.map((name, i) => ({ "@id": "", id: -(i + 1), name })),
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

  const addTome = () => {
    const regularTomes = form.tomes.filter((t) => !t.isHorsSerie);
    const nextNum = regularTomes.length > 0 ? Math.max(...regularTomes.map((t) => t.number)) + 1 : 1;
    update("tomes", [...form.tomes, emptyTome(nextNum)]);
  };

  const batchSize = batchTo - batchFrom + 1;

  const addBatchTomes = () => {
    if (batchFrom < 1 || batchFrom > batchTo || batchSize > maxBatchSize) return;
    const existingNumbers = new Set(form.tomes.map((t) => t.number));
    const newTomes: TomeFormData[] = [];
    for (let n = batchFrom; n <= batchTo; n++) {
      if (!existingNumbers.has(n)) {
        newTomes.push(emptyTome(n));
      }
    }
    if (newTomes.length > 0) {
      update("tomes", [...form.tomes, ...newTomes].sort(compareTomes));
    }
  };

  const removeTome = (index: number) => {
    update(
      "tomes",
      form.tomes.filter((_, i) => i !== index),
    );
  };

  const updateTome = <K extends keyof TomeFormData>(index: number, key: K, value: TomeFormData[K]) => {
    update(
      "tomes",
      form.tomes.map((t, i) => (i === index ? { ...t, [key]: value } : t)),
    );
  };

  const lookupTomeIsbn = async (index: number) => {
    const isbn = form.tomes[index]?.isbn;
    if (!isbn || isbn.length < 10) return;

    setTomeLookupLoading(index);
    try {
      const result = await fetchLookupIsbn(isbn, form.type);
      update(
        "tomes",
        form.tomes.map((t, i) =>
          i === index
            ? { ...t, isbn: result.isbn ?? t.isbn, title: result.title ?? t.title, tomeEnd: result.tomeEnd?.toString() ?? t.tomeEnd }
            : t,
        ),
      );
      toast.success(`Tome ${form.tomes[index].number} : informations récupérées`);
    } catch {
      toast.error("Échec de la recherche ISBN");
    } finally {
      setTomeLookupLoading(null);
    }
  };

  const addAuthor = (author: Author) => {
    if (!form.authors.some((a) => a.name === author.name)) {
      update("authors", [...form.authors, author]);
    }
    setAuthorSearch("");
  };

  const removeAuthor = (index: number) => {
    update(
      "authors",
      form.authors.filter((_, i) => i !== index),
    );
  };

  const handleSubmit = async (e?: React.FormEvent) => {
    e?.preventDefault();

    const authorIris: string[] = [];
    const pendingAuthors: string[] = [];
    for (const a of form.authors) {
      if (a.id > 0) {
        authorIris.push(a["@id"]);
      } else if (!navigator.onLine) {
        pendingAuthors.push(a.name);
      } else {
        try {
          const created = await apiFetch<Author>("/authors", {
            body: JSON.stringify({ name: a.name }),
            method: "POST",
          });
          authorIris.push(created["@id"]);
        } catch {
          toast.error(`Erreur lors de la création de l'auteur « ${a.name} »`);
          return;
        }
      }
    }

    const payload: Record<string, unknown> = {
      ...(pendingAuthors.length > 0 ? { _pendingAuthors: pendingAuthors } : {}),
      authors: authorIris,
      coverUrl: form.coverUrl || null,
      defaultTomeBought: form.defaultTomeBought,
      defaultTomeDownloaded: form.defaultTomeDownloaded,
      defaultTomeRead: form.defaultTomeRead,
      description: form.description || null,
      isOneShot: form.isOneShot,
      latestPublishedIssue: form.latestPublishedIssue ? Number(form.latestPublishedIssue) : null,
      latestPublishedIssueComplete: form.latestPublishedIssueComplete,
      publishedDate: form.publishedDate || null,
      publisher: form.publisher || null,
      status: form.status,
      title: form.title,
      type: form.type,
    };

    if (!form.isOneShot) {
      payload.tomes = [...form.tomes]
        .sort(compareTomes)
        .map((t) => ({
          ...(t.id ? { "@id": `/api/tomes/${t.id}` } : {}),
          bought: t.bought,
          downloaded: t.downloaded,
          isHorsSerie: t.isHorsSerie,
          isbn: t.isbn || null,
          number: t.number,
          onNas: t.onNas,
          read: t.read,
          title: t.title || null,
          tomeEnd: t.tomeEnd ? Number(t.tomeEnd) : null,
        }));
    }

    if (isEdit && id) {
      updateComic.mutate(
        { id: Number(id), ...payload } as Partial<ComicSeries> & { id: number },
        {
          onSuccess: (data) => {
            if (!data) return;
            if (syncFailure?.id) void resolveSyncFailure(syncFailure.id);
            toast.success("Série mise à jour");
            navigate(`/comic/${id}`, { viewTransition: true });
          },
          onError: (err) => toast.error(err.message),
        },
      );
    } else {
      createComic.mutate(payload as Partial<ComicSeries>, {
        onSuccess: (created) => {
          if (!created) return;
          if (syncFailure?.id) void resolveSyncFailure(syncFailure.id);
          toast.success("Série créée");
          navigate(`/comic/${created.id}`, { viewTransition: true });
        },
        onError: (err) => toast.error(err.message),
      });
    }

    if (!navigator.onLine) {
      navigate("/", { viewTransition: true });
    }
  };

  const isSaving = createComic.isPending || updateComic.isPending;

  return {
    addAuthor,
    addBatchTomes,
    addTome,
    applyLookup,
    authorOptions,
    authorSearch,
    batchFrom,
    batchSize,
    batchTo,
    clearCandidate,
    coverSearchOpen,
    form,
    handleSubmit,
    initialized,
    isApplying,
    isEdit,
    isOnline,
    isSaving,
    lookupIsbn,
    lookupMode,
    lookupResult,
    lookupTitle,
    lookupTomeIsbn,
    maxBatchSize,
    navigate,
    removeAuthor,
    removeTome,
    resolveSyncFailure,
    selectCandidate,
    selectedCandidateTitle,
    setAuthorSearch,
    setBatchFrom,
    setBatchTo,
    setCoverSearchOpen,
    setLookupIsbn,
    setLookupMode,
    setLookupTitle,
    syncFailure,
    titleCandidates,
    tomeLookupLoading,
    update,
    updateTome,
  };
}
