import type { Author, ComicSeries, LookupCandidatesResponse, Tome, LookupResult } from "../../types/api";
import { ComicStatus, ComicType } from "../../types/enums";

let nextId = 1;

export function createMockAuthor(overrides: Partial<Author> = {}): Author {
  const id = overrides.id ?? nextId++;
  return {
    "@id": `/api/authors/${id}`,
    followedForNewSeries: false,
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
    id,
    isHorsSerie: false,
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
    amazonUrl: null,
    authors: [],
    boughtCount: 0,
    coveredCount: 0,
    coverImage: null,
    coverUrl: null,
    createdAt: "2025-01-01T00:00:00+00:00",
    defaultTomeBought: false,
    defaultTomeOnNas: false,
    defaultTomeRead: false,
    description: null,
    onNasCount: 0,
    id,
    isOneShot: false,
    latestPublishedIssue: null,
    latestPublishedIssueComplete: false,
    latestPublishedIssueUpdatedAt: null,
    maxTomeNumber: null,
    notInterestedBuy: false,
    notInterestedNas: false,
    publishedDate: null,
    publisher: null,
    readCount: 0,
    status: ComicStatus.BUYING,
    title: `Series ${id}`,
    tomesCount: 0,
    type: ComicType.BD,
    unboughtTomes: [],
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
