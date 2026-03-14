import type { Author, ComicSeries, LookupCandidatesResponse, Tome, LookupResult } from "../../types/api";
import { ComicStatus, ComicType } from "../../types/enums";

let nextId = 1;

export function createMockAuthor(overrides: Partial<Author> = {}): Author {
  const id = overrides.id ?? nextId++;
  return {
    "@id": `/api/authors/${id}`,
    id,
    name: `Author ${id}`,
    ...overrides,
  };
}

export function createMockTome(overrides: Partial<Tome> = {}): Tome {
  const id = overrides.id ?? nextId++;
  return {
    "@id": `/api/tomes/${id}`,
    bought: false,
    createdAt: "2025-01-01T00:00:00+00:00",
    downloaded: false,
    id,
    isbn: null,
    number: id,
    onNas: false,
    read: false,
    title: null,
    tomeEnd: null,
    updatedAt: "2025-01-01T00:00:00+00:00",
    ...overrides,
  };
}

export function createMockComicSeries(
  overrides: Partial<ComicSeries> = {},
): ComicSeries {
  const id = overrides.id ?? nextId++;
  return {
    "@id": `/api/comic_series/${id}`,
    authors: [],
    coverImage: null,
    coverUrl: null,
    createdAt: "2025-01-01T00:00:00+00:00",
    defaultTomeBought: false,
    defaultTomeDownloaded: false,
    defaultTomeRead: false,
    description: null,
    id,
    isOneShot: false,
    latestPublishedIssue: null,
    latestPublishedIssueComplete: false,
    latestPublishedIssueUpdatedAt: null,
    publishedDate: null,
    publisher: null,
    status: ComicStatus.BUYING,
    title: `Series ${id}`,
    tomes: [],
    type: ComicType.BD,
    updatedAt: "2025-01-01T00:00:00+00:00",
    ...overrides,
  };
}

export function createMockLookupResult(
  overrides: Partial<LookupResult> = {},
): LookupResult {
  return {
    apiMessages: {},
    authors: null,
    description: null,
    isbn: null,
    isOneShot: null,
    latestPublishedIssue: null,
    publishedDate: null,
    publisher: null,
    sources: [],
    thumbnail: null,
    title: null,
    tomeEnd: null,
    tomeNumber: null,
    ...overrides,
  };
}

export function wrapAsCandidatesResponse(result: LookupResult): LookupCandidatesResponse {
  const { apiMessages, sources, ...candidate } = result;
  return {
    apiMessages,
    results: [candidate],
    sources,
  };
}

export function createMockHydraCollection<T>(
  members: T[],
  basePath = "/api/comic_series",
) {
  return {
    "@context": "/api/contexts/ComicSeries",
    "@id": basePath,
    "@type": "Collection" as const,
    member: members,
    totalItems: members.length,
  };
}

/** Remet le compteur d'ID pour l'isolation entre tests. */
export function resetFactoryIds(): void {
  nextId = 1;
}
