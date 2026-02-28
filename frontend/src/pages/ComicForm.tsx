import {
  Combobox,
  ComboboxInput,
  ComboboxOption,
  ComboboxOptions,
  Listbox,
  ListboxButton,
  ListboxOption,
  ListboxOptions,
} from "@headlessui/react";
import { ArrowLeft, Check, ChevronDown, Loader2, Plus, Search, Trash2, X } from "lucide-react";
import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { toast } from "sonner";
import BarcodeScanner from "../components/BarcodeScanner";
import { useAuthors } from "../hooks/useAuthors";
import { useOnlineStatus } from "../hooks/useOnlineStatus";
import { apiFetch } from "../services/api";
import { useComic } from "../hooks/useComic";
import { useCreateComic } from "../hooks/useCreateComic";
import { fetchLookupIsbn, fetchLookupTitle, useLookupIsbn, useLookupTitle } from "../hooks/useLookup";
import { useUpdateComic } from "../hooks/useUpdateComic";
import type { Author, ComicSeries } from "../types/api";
import {
  ComicStatus,
  ComicStatusLabel,
  ComicType,
  ComicTypeLabel,
} from "../types/enums";

interface TomeFormData {
  bought: boolean;
  downloaded: boolean;
  id?: number;
  isbn: string;
  number: number;
  onNas: boolean;
  read: boolean;
  title: string;
}

interface FormData {
  authors: Author[];
  coverUrl: string;
  description: string;
  isOneShot: boolean;
  latestPublishedIssue: string;
  publisher: string;
  status: string;
  title: string;
  tomes: TomeFormData[];
  type: string;
}

function emptyTome(number: number): TomeFormData {
  return { bought: false, downloaded: false, isbn: "", number, onNas: false, read: false, title: "" };
}

function buildInitialForm(comic?: ComicSeries): FormData {
  if (comic) {
    return {
      authors: comic.authors,
      coverUrl: comic.coverUrl ?? "",
      description: comic.description ?? "",
      isOneShot: comic.isOneShot,
      latestPublishedIssue: comic.latestPublishedIssue?.toString() ?? "",
      publisher: comic.publisher ?? "",
      status: comic.status,
      title: comic.title,
      tomes: comic.tomes.map((t) => ({
        bought: t.bought,
        downloaded: t.downloaded,
        id: t.id,
        isbn: t.isbn ?? "",
        number: t.number,
        onNas: t.onNas,
        read: t.read,
        title: t.title ?? "",
      })),
      type: comic.type,
    };
  }
  return {
    authors: [],
    coverUrl: "",
    description: "",
    isOneShot: false,
    latestPublishedIssue: "",
    publisher: "",
    status: ComicStatus.BUYING,
    title: "",
    tomes: [emptyTome(1)],
    type: ComicType.BD,
  };
}

function FormListbox({
  label,
  onChange,
  options,
  value,
}: {
  label: string;
  onChange: (v: string) => void;
  options: { label: string; value: string }[];
  value: string;
}) {
  const selected = options.find((o) => o.value === value) ?? options[0];

  return (
    <div>
      <span className="mb-1 block text-sm font-medium text-text-secondary">{label}</span>
      <Listbox onChange={onChange} value={value}>
        <div className="relative">
          <ListboxButton className="flex w-full items-center justify-between gap-2 rounded-lg border border-surface-border bg-surface-primary px-3 py-2 text-sm text-text-primary transition hover:border-primary-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
            <span>{selected.label}</span>
            <ChevronDown className="h-4 w-4 text-text-muted" />
          </ListboxButton>
          <ListboxOptions className="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-surface-border bg-surface-primary py-1 shadow-lg focus:outline-none">
            {options.map((option) => (
              <ListboxOption
                className="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm text-text-primary data-[focus]:bg-primary-50 dark:data-[focus]:bg-primary-950/30"
                key={option.value}
                value={option.value}
              >
                <Check
                  className={`h-4 w-4 shrink-0 ${option.value === value ? "text-primary-600" : "invisible"}`}
                />
                {option.label}
              </ListboxOption>
            ))}
          </ListboxOptions>
        </div>
      </Listbox>
    </div>
  );
}

const typeOptions = Object.entries(ComicType).map(([, value]) => ({
  label: ComicTypeLabel[value],
  value,
}));

const statusOptions = Object.entries(ComicStatus).map(([, value]) => ({
  label: ComicStatusLabel[value],
  value,
}));

export default function ComicForm() {
  const { id } = useParams<{ id: string }>();
  const isEdit = Boolean(id);
  const navigate = useNavigate();
  const isOnline = useOnlineStatus();
  const { data: comic } = useComic(id ? Number(id) : undefined);
  const createComic = useCreateComic();
  const updateComic = useUpdateComic();

  const [form, setForm] = useState<FormData>(buildInitialForm());
  const [initialized, setInitialized] = useState(!isEdit);

  // Lookup state
  const [isApplying, setIsApplying] = useState(false);
  const [lookupIsbn, setLookupIsbn] = useState("");
  const [lookupTitle, setLookupTitle] = useState("");
  const [lookupMode, setLookupMode] = useState<"isbn" | "title">("title");
  const [tomeLookupLoading, setTomeLookupLoading] = useState<number | null>(null);

  const isbnLookup = useLookupIsbn(lookupMode === "isbn" ? lookupIsbn : "", form.type);
  const titleLookup = useLookupTitle(lookupMode === "title" ? lookupTitle : "", form.type);
  const lookupResult = lookupMode === "isbn" ? isbnLookup : titleLookup;

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

  if (isEdit && !initialized) {
    return <div className="py-12 text-center text-text-muted">Chargement…</div>;
  }

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

  const applyLookup = async () => {
    const result = lookupResult.data;
    if (!result) return;

    if (lookupMode === "isbn" && result.title) {
      // Chaînage ISBN → titre : l'ISBN donne le titre de la série, puis le lookup titre remplit les champs
      setIsApplying(true);
      try {
        const titleResult = await fetchLookupTitle(result.title, form.type);
        applySeriesFields(titleResult);
        toast.success("Informations récupérées (ISBN → titre)");
      } catch {
        // Fallback : appliquer directement le résultat ISBN
        applySeriesFields(result);
        toast.success("Informations récupérées (ISBN uniquement)");
      } finally {
        setIsApplying(false);
      }
    } else {
      applySeriesFields(result);
      toast.success("Informations récupérées");
    }
  };

  const addTome = () => {
    const nextNum = form.tomes.length > 0 ? Math.max(...form.tomes.map((t) => t.number)) + 1 : 1;
    update("tomes", [...form.tomes, emptyTome(nextNum)]);
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
            ? { ...t, isbn: result.isbn ?? t.isbn, title: result.title ?? t.title }
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

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    // Créer les nouveaux auteurs via l'API et récupérer leurs IRI
    const authorIris: string[] = [];
    const pendingAuthors: string[] = [];
    for (const a of form.authors) {
      if (a.id > 0) {
        authorIris.push(a["@id"]);
      } else if (!navigator.onLine) {
        // Hors ligne : stocker les noms pour création au retour en ligne
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
      description: form.description || null,
      isOneShot: form.isOneShot,
      latestPublishedIssue: form.latestPublishedIssue ? Number(form.latestPublishedIssue) : null,
      publisher: form.publisher || null,
      status: form.status,
      title: form.title,
      type: form.type,
    };

    if (!form.isOneShot) {
      payload.tomes = form.tomes.map((t) => ({
        ...(t.id ? { id: t.id } : {}),
        bought: t.bought,
        downloaded: t.downloaded,
        isbn: t.isbn || null,
        number: t.number,
        onNas: t.onNas,
        read: t.read,
        title: t.title || null,
      }));
    }

    if (isEdit && id) {
      updateComic.mutate(
        { id: Number(id), ...payload } as Partial<ComicSeries> & { id: number },
        {
          onSuccess: (data) => {
            if (!data) return; // offline: déjà géré par useOfflineMutation
            toast.success("Série mise à jour");
            navigate(`/comic/${id}`);
          },
          onError: (err) => toast.error(err.message),
        },
      );
    } else {
      createComic.mutate(payload as Partial<ComicSeries>, {
        onSuccess: (created) => {
          if (!created) return; // offline: déjà géré par useOfflineMutation
          toast.success("Série créée");
          navigate(`/comic/${created.id}`);
        },
        onError: (err) => toast.error(err.message),
      });
    }

    if (!navigator.onLine) {
      navigate("/");
    }
  };

  const isSaving = createComic.isPending || updateComic.isPending;

  return (
    <div className="mx-auto max-w-3xl space-y-6 pb-28">
      {/* Header */}
      <div className="flex items-center gap-3">
        <button className="text-text-muted hover:text-text-secondary" onClick={() => navigate(-1)} type="button">
          <ArrowLeft className="h-5 w-5" />
        </button>
        <h1 className="text-xl font-bold text-text-primary">
          {isEdit ? "Modifier la série" : "Nouvelle série"}
        </h1>
      </div>

      {/* Lookup section — visible on create AND edit */}
      {isOnline ? (
        <div className="rounded-lg border border-surface-border bg-surface-tertiary p-4 space-y-3">
          <div className="flex items-center justify-between">
            <h2 className="text-sm font-semibold text-text-secondary">Recherche automatique</h2>
            <div className="flex rounded-lg bg-surface-primary p-0.5 border border-surface-border">
              <button
                className={`rounded-md px-3 py-1 text-sm font-medium transition ${lookupMode === "isbn" ? "bg-primary-600 text-white shadow-sm" : "text-text-muted hover:text-text-secondary"}`}
                onClick={() => setLookupMode("isbn")}
                type="button"
              >
                ISBN
              </button>
              <button
                className={`rounded-md px-3 py-1 text-sm font-medium transition ${lookupMode === "title" ? "bg-primary-600 text-white shadow-sm" : "text-text-muted hover:text-text-secondary"}`}
                onClick={() => setLookupMode("title")}
                type="button"
              >
                Titre
              </button>
            </div>
          </div>

          {lookupMode === "isbn" ? (
            <div className="flex gap-2">
              <input
                className="flex-1 rounded-lg border border-surface-border bg-surface-primary px-3 py-2 text-sm text-text-primary"
                onChange={(e) => setLookupIsbn(e.target.value)}
                placeholder="ISBN (10 ou 13 chiffres)"
                value={lookupIsbn}
              />
              <BarcodeScanner onScan={setLookupIsbn} />
            </div>
          ) : (
            <input
              className="w-full rounded-lg border border-surface-border bg-surface-primary px-3 py-2 text-sm text-text-primary"
              onChange={(e) => setLookupTitle(e.target.value)}
              placeholder="Titre de la série"
              value={lookupTitle}
            />
          )}

          {lookupResult.isFetching && (
            <div className="flex items-center gap-2 text-sm text-text-muted">
              <Loader2 className="h-4 w-4 animate-spin" /> Recherche en cours…
            </div>
          )}

          {lookupResult.data && !lookupResult.isFetching && (
            <div className="flex items-center justify-between gap-3 rounded-lg bg-surface-primary p-3 border border-surface-border">
              <div className="min-w-0 text-sm">
                <p className="truncate font-medium text-text-primary">{lookupResult.data.title}</p>
                <p className="truncate text-text-muted">
                  {lookupResult.data.authors ?? ""}
                  {lookupResult.data.publisher && ` — ${lookupResult.data.publisher}`}
                </p>
              </div>
              <button
                className="flex shrink-0 items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                disabled={isApplying}
                onClick={applyLookup}
                type="button"
              >
                {isApplying && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                Appliquer
              </button>
            </div>
          )}
        </div>
      ) : (
        <div className="rounded-lg border border-surface-border bg-surface-tertiary p-4">
          <p className="text-sm text-text-muted">Recherche indisponible hors ligne</p>
        </div>
      )}

      {/* Form */}
      <form className="space-y-5" onSubmit={handleSubmit}>
        {/* Title */}
        <div>
          <label className="mb-1 block text-sm font-medium text-text-secondary" htmlFor="title">
            Titre *
          </label>
          <input
            className="w-full rounded-lg border border-surface-border bg-surface-primary px-3 py-2 text-sm text-text-primary"
            id="title"
            onChange={(e) => update("title", e.target.value)}
            required
            value={form.title}
          />
        </div>

        {/* Type + Status */}
        <div className="grid grid-cols-2 gap-4">
          <FormListbox
            label="Type *"
            onChange={(v) => update("type", v)}
            options={typeOptions}
            value={form.type}
          />
          <FormListbox
            label="Statut *"
            onChange={(v) => update("status", v)}
            options={statusOptions}
            value={form.status}
          />
        </div>

        {/* One-shot toggle */}
        <label className="flex items-center gap-2">
          <input
            checked={form.isOneShot}
            className="h-4 w-4 rounded border-surface-border text-primary-600"
            onChange={(e) => update("isOneShot", e.target.checked)}
            type="checkbox"
          />
          <span className="text-sm font-medium text-text-secondary">One-shot (pas de tomes)</span>
        </label>

        {/* Publisher */}
        <div>
          <label className="mb-1 block text-sm font-medium text-text-secondary" htmlFor="publisher">
            Éditeur
          </label>
          <input
            className="w-full rounded-lg border border-surface-border bg-surface-primary px-3 py-2 text-sm text-text-primary"
            id="publisher"
            onChange={(e) => update("publisher", e.target.value)}
            value={form.publisher}
          />
        </div>

        {/* Cover URL */}
        <div>
          <label className="mb-1 block text-sm font-medium text-text-secondary" htmlFor="coverUrl">
            URL de couverture
          </label>
          <input
            className="w-full rounded-lg border border-surface-border bg-surface-primary px-3 py-2 text-sm text-text-primary"
            id="coverUrl"
            onChange={(e) => update("coverUrl", e.target.value)}
            placeholder="https://..."
            type="url"
            value={form.coverUrl}
          />
          {form.coverUrl && (
            <img alt="Aperçu" className="mt-2 h-32 rounded-lg shadow" src={form.coverUrl} />
          )}
        </div>

        {/* Authors */}
        <div>
          <label className="mb-1 block text-sm font-medium text-text-secondary">Auteurs</label>
          <div className="flex flex-wrap gap-2 mb-2">
            {form.authors.map((author, i) => (
              <span
                className="flex items-center gap-1 rounded-full bg-primary-100 px-3 py-1 text-sm font-medium text-primary-700 dark:bg-primary-950/30 dark:text-primary-400"
                key={author.id}
              >
                {author.name}
                <button
                  className="ml-1 rounded-full p-0.5 hover:bg-primary-200 dark:hover:bg-primary-900/40"
                  onClick={() => removeAuthor(i)}
                  type="button"
                >
                  <X className="h-3 w-3" />
                </button>
              </span>
            ))}
          </div>
          <Combobox
            onChange={(author: Author | null) => {
              if (author) addAuthor(author);
            }}
            value={null}
          >
            <div className="relative">
              <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
              <ComboboxInput
                className="w-full rounded-lg border border-surface-border bg-surface-primary py-2 pl-10 pr-3 text-sm text-text-primary"
                displayValue={() => authorSearch}
                onChange={(e) => setAuthorSearch(e.target.value)}
                placeholder="Rechercher ou créer un auteur…"
              />
              <ComboboxOptions className="absolute z-10 mt-1 max-h-48 w-full overflow-auto rounded-lg border border-surface-border bg-surface-primary shadow-lg">
                {authorOptions.map((author) => (
                  <ComboboxOption
                    className="cursor-pointer px-3 py-2 text-sm text-text-primary data-[focus]:bg-primary-50 dark:data-[focus]:bg-primary-950/30"
                    key={author.id}
                    value={author}
                  >
                    {author.name}
                  </ComboboxOption>
                ))}
                {authorSearch.length >= 2 && !authorOptions.some((a) => a.name.toLowerCase() === authorSearch.toLowerCase()) && (
                  <ComboboxOption
                    className="cursor-pointer px-3 py-2 text-sm text-primary-700 dark:text-primary-400 data-[focus]:bg-primary-50 dark:data-[focus]:bg-primary-950/30"
                    value={{ "@id": "", id: -Date.now(), name: authorSearch } as Author}
                  >
                    <Plus className="mr-1 inline h-3 w-3" />
                    Créer « {authorSearch} »
                  </ComboboxOption>
                )}
              </ComboboxOptions>
            </div>
          </Combobox>
        </div>

        {/* Description */}
        <div>
          <label className="mb-1 block text-sm font-medium text-text-secondary" htmlFor="description">
            Description
          </label>
          <textarea
            className="w-full rounded-lg border border-surface-border bg-surface-primary px-3 py-2 text-sm text-text-primary"
            id="description"
            onChange={(e) => update("description", e.target.value)}
            rows={3}
            value={form.description}
          />
        </div>

        {/* Latest published issue */}
        <div>
          <label className="mb-1 block text-sm font-medium text-text-secondary" htmlFor="latestPublishedIssue">
            Dernier tome paru
          </label>
          <input
            className="w-32 rounded-lg border border-surface-border bg-surface-primary px-3 py-2 text-sm text-text-primary"
            id="latestPublishedIssue"
            min="0"
            onChange={(e) => update("latestPublishedIssue", e.target.value)}
            type="number"
            value={form.latestPublishedIssue}
          />
        </div>

        {/* Tomes */}
        {!form.isOneShot && (
          <div>
            <div className="mb-2 flex items-center justify-between">
              <h2 className="text-sm font-semibold text-text-secondary">
                Tomes ({form.tomes.length})
              </h2>
              <button
                className="flex items-center gap-1 rounded-lg bg-primary-100 px-3 py-1.5 text-sm font-medium text-primary-700 hover:bg-primary-200 dark:bg-primary-950/30 dark:text-primary-400 dark:hover:bg-primary-900/40"
                onClick={addTome}
                type="button"
              >
                <Plus className="h-4 w-4" /> Ajouter
              </button>
            </div>
            <div className="overflow-x-auto rounded-lg border border-surface-border">
              <table className="w-full text-sm">
                <thead className="bg-surface-tertiary">
                  <tr>
                    <th className="px-3 py-2 text-left font-medium text-text-secondary">#</th>
                    <th className="px-3 py-2 text-left font-medium text-text-secondary">Titre</th>
                    <th className="px-3 py-2 text-left font-medium text-text-secondary">ISBN</th>
                    <th className="px-3 py-2 text-center font-medium text-text-secondary">Acheté</th>
                    <th className="px-3 py-2 text-center font-medium text-text-secondary">DL</th>
                    <th className="px-3 py-2 text-center font-medium text-text-secondary">Lu</th>
                    <th className="px-3 py-2 text-center font-medium text-text-secondary">NAS</th>
                    <th className="px-3 py-2" />
                  </tr>
                </thead>
                <tbody className="divide-y divide-surface-border">
                  {form.tomes.map((tome, i) => (
                    <tr key={i}>
                      <td className="px-3 py-1.5">
                        <input
                          className="w-14 rounded border border-surface-border bg-surface-primary px-2 py-1 text-center text-sm text-text-primary"
                          min="0"
                          onChange={(e) => updateTome(i, "number", Number(e.target.value))}
                          type="number"
                          value={tome.number}
                        />
                      </td>
                      <td className="px-3 py-1.5">
                        <input
                          className="w-full min-w-[100px] rounded border border-surface-border bg-surface-primary px-2 py-1 text-sm text-text-primary"
                          onChange={(e) => updateTome(i, "title", e.target.value)}
                          placeholder="Titre"
                          value={tome.title}
                        />
                      </td>
                      <td className="px-3 py-1.5">
                        <div className="flex items-center gap-1">
                          <input
                            className="w-full min-w-[120px] rounded border border-surface-border bg-surface-primary px-2 py-1 text-sm text-text-primary"
                            onChange={(e) => updateTome(i, "isbn", e.target.value)}
                            placeholder="ISBN"
                            value={tome.isbn}
                          />
                          <button
                            className="shrink-0 rounded p-1 text-text-muted hover:bg-surface-tertiary hover:text-primary-600 disabled:opacity-50"
                            disabled={tome.isbn.length < 10 || tomeLookupLoading === i}
                            onClick={() => lookupTomeIsbn(i)}
                            title="Rechercher par ISBN"
                            type="button"
                          >
                            {tomeLookupLoading === i
                              ? <Loader2 className="h-4 w-4 animate-spin" />
                              : <Search className="h-4 w-4" />}
                          </button>
                        </div>
                      </td>
                      <td className="px-3 py-1.5 text-center">
                        <input
                          checked={tome.bought}
                          className="h-4 w-4 rounded border-surface-border text-primary-600"
                          onChange={(e) => updateTome(i, "bought", e.target.checked)}
                          type="checkbox"
                        />
                      </td>
                      <td className="px-3 py-1.5 text-center">
                        <input
                          checked={tome.downloaded}
                          className="h-4 w-4 rounded border-surface-border text-primary-600"
                          onChange={(e) => updateTome(i, "downloaded", e.target.checked)}
                          type="checkbox"
                        />
                      </td>
                      <td className="px-3 py-1.5 text-center">
                        <input
                          checked={tome.read}
                          className="h-4 w-4 rounded border-surface-border text-primary-600"
                          onChange={(e) => updateTome(i, "read", e.target.checked)}
                          type="checkbox"
                        />
                      </td>
                      <td className="px-3 py-1.5 text-center">
                        <input
                          checked={tome.onNas}
                          className="h-4 w-4 rounded border-surface-border text-primary-600"
                          onChange={(e) => updateTome(i, "onNas", e.target.checked)}
                          type="checkbox"
                        />
                      </td>
                      <td className="px-3 py-1.5">
                        <button
                          className="rounded p-1 text-red-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/30"
                          onClick={() => removeTome(i)}
                          type="button"
                        >
                          <Trash2 className="h-4 w-4" />
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </form>

      {/* Sticky save/cancel bar */}
      <div className="fixed bottom-14 left-0 right-0 z-40 flex justify-center gap-3 border-t border-surface-border bg-surface-primary px-4 py-3">
        <button
          className="rounded-lg px-5 py-2.5 text-base font-medium text-text-secondary hover:bg-surface-tertiary"
          onClick={() => navigate(-1)}
          type="button"
        >
          Annuler
        </button>
        <button
          className="flex items-center gap-2 rounded-lg bg-primary-600 px-5 py-2.5 text-base font-medium text-white hover:bg-primary-700 disabled:opacity-50"
          disabled={isSaving || !form.title}
          onClick={handleSubmit}
          type="button"
        >
          {isSaving && <Loader2 className="h-5 w-5 animate-spin" />}
          {isEdit ? "Enregistrer" : "Créer"}
        </button>
      </div>
    </div>
  );
}
