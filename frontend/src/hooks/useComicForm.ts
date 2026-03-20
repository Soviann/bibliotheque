import { useEffect, useState } from "react";
import { useNavigate, useParams, useSearchParams } from "react-router-dom";
import { toast } from "sonner";
import { useAuthorManagement } from "./useAuthorManagement";
import { useLookupFeature } from "./useLookupFeature";
import { useOnlineStatus } from "./useOnlineStatus";
import { useComic } from "./useComic";
import { useCreateComic } from "./useCreateComic";
import { useSyncFailures } from "./useSyncFailures";
import { useTomeManagement } from "./useTomeManagement";
import { useUpdateComic } from "./useUpdateComic";
import { endpoints } from "../endpoints";
import { apiFetch, getErrorMessage } from "../services/api";
import type { Author, ComicSeries, CreateComicPayload, TomePayload, UpdateComicPayload } from "../types/api";
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
  amazonUrl: string;
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

function buildInitialForm(comic?: ComicSeries): FormData {
  if (comic) {
    return {
      amazonUrl: comic.amazonUrl ?? "",
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
      tomes: (comic.tomes ?? []).map((t) => ({
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
    amazonUrl: "",
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
    tomes: [{ bought: false, downloaded: false, isHorsSerie: false, isbn: "", number: 1, onNas: false, read: false, title: "", tomeEnd: "" }],
    type: ComicType.BD,
  };
}

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

  // Cover search state
  const [coverSearchOpen, setCoverSearchOpen] = useState(false);

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

  // Sub-hooks
  const lookup = useLookupFeature(form, update);
  const tomeManager = useTomeManagement(form, update);
  const authorManager = useAuthorManagement(form, update);

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
          const created = await apiFetch<Author>(endpoints.authors, {
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

    const tomes: TomePayload[] | undefined = form.isOneShot
      ? undefined
      : [...form.tomes].sort(compareTomes).map((t) => ({
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

    const basePayload: CreateComicPayload = {
      ...(pendingAuthors.length > 0 ? { _pendingAuthors: pendingAuthors } : {}),
      amazonUrl: form.amazonUrl || null,
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
      status: form.status as ComicStatus,
      title: form.title,
      tomes,
      type: form.type as ComicType,
    };

    if (isEdit && id) {
      const updatePayload: UpdateComicPayload = { id: Number(id), ...basePayload };
      updateComic.mutate(updatePayload, {
        onSuccess: (data) => {
          if (!data) return;
          if (syncFailure?.id) void resolveSyncFailure(syncFailure.id);
          toast.success("Série mise à jour");
          navigate(`/comic/${id}`, { replace: true, viewTransition: true });
        },
        onError: (err) => toast.error(getErrorMessage(err)),
      });
    } else {
      createComic.mutate(basePayload, {
        onSuccess: (created) => {
          if (!created) return;
          if (syncFailure?.id) void resolveSyncFailure(syncFailure.id);
          toast.success("Série créée");
          navigate(`/comic/${created.id}`, { replace: true, viewTransition: true });
        },
        onError: (err) => toast.error(getErrorMessage(err)),
      });
    }

    if (!navigator.onLine) {
      navigate("/", { viewTransition: true });
    }
  };

  const isSaving = createComic.isPending || updateComic.isPending;

  return {
    // Author management
    addAuthor: authorManager.addAuthor,
    authorOptions: authorManager.authorOptions,
    authorSearch: authorManager.authorSearch,
    removeAuthor: authorManager.removeAuthor,
    setAuthorSearch: authorManager.setAuthorSearch,
    // Cover search
    coverSearchOpen,
    setCoverSearchOpen,
    // Form state
    form,
    handleSubmit,
    initialized,
    isEdit,
    isOnline,
    isSaving,
    resolveSyncFailure,
    syncFailure,
    update,
    // Lookup
    applyLookup: lookup.applyLookup,
    clearCandidate: lookup.clearCandidate,
    isApplying: lookup.isApplying,
    lookupIsbn: lookup.lookupIsbn,
    lookupMode: lookup.lookupMode,
    lookupResult: lookup.lookupResult,
    lookupTitle: lookup.lookupTitle,
    selectCandidate: lookup.selectCandidate,
    selectedCandidateTitle: lookup.selectedCandidateTitle,
    setLookupIsbn: lookup.setLookupIsbn,
    setLookupMode: lookup.setLookupMode,
    setLookupTitle: lookup.setLookupTitle,
    titleCandidates: lookup.titleCandidates,
    // Tome management
    tomeManager,
  };
}
