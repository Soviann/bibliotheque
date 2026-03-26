import type {
  ComicStatus,
  ComicType,
  EnrichmentConfidence,
  ProposalStatus,
  SuggestionStatus,
} from "./enums";

export interface HydraCollection<T> {
  "@context": string;
  "@id": string;
  "@type": "Collection";
  member: T[];
  totalItems: number;
  view?: {
    "@id": string;
    "@type": "PartialCollectionView";
    first?: string;
    last?: string;
    next?: string;
    previous?: string;
  };
}

export interface Author {
  "@id": string;
  followedForNewSeries: boolean;
  id: number;
  name: string;
}

export interface Tome {
  "@id": string;
  _syncPending?: boolean;
  bought: boolean;
  createdAt: string;
  downloaded: boolean;
  id: number;
  isHorsSerie: boolean;
  isbn: string | null;
  number: number;
  onNas: boolean;
  read: boolean;
  title: string | null;
  tomeEnd: number | null;
  updatedAt: string;
}

export interface ComicSeries {
  "@id": string;
  _syncPending?: boolean;
  amazonUrl: string | null;
  authors: Author[];
  boughtCount: number;
  coveredCount: number;
  coverImage: string | null;
  coverUrl: string | null;
  createdAt: string;
  defaultTomeBought: boolean;
  defaultTomeDownloaded: boolean;
  defaultTomeRead: boolean;
  description: string | null;
  downloadedCount: number;
  id: number;
  isOneShot: boolean;
  maxTomeNumber: number | null;
  notInterestedBuy: boolean;
  notInterestedNas: boolean;
  latestPublishedIssue: number | null;
  latestPublishedIssueComplete: boolean;
  latestPublishedIssueUpdatedAt: string | null;
  publishedDate: string | null;
  publisher: string | null;
  readCount: number;
  status: ComicStatus;
  title: string;
  tomes?: Tome[];
  tomesCount: number;
  type: ComicType;
  unboughtTomeNumbers: number[];
  updatedAt: string;
}

export interface PurgeableSeries {
  deletedAt: string;
  id: number;
  title: string;
}

export interface MergeGroup {
  entries: MergeGroupEntry[];
  suggestedTitle: string;
}

export interface MergeGroupEntry {
  originalTitle: string;
  seriesId: number;
  suggestedTomeNumber: number | null;
}

export interface MergePreview {
  amazonUrl: string | null;
  authors: string[];
  coverUrl: string | null;
  defaultTomeBought: boolean;
  defaultTomeDownloaded: boolean;
  defaultTomeRead: boolean;
  description: string | null;
  isOneShot: boolean;
  latestPublishedIssue: number | null;
  latestPublishedIssueComplete: boolean;
  notInterestedBuy: boolean;
  notInterestedNas: boolean;
  publishedDate: string | null;
  publisher: string | null;
  sourceSeriesIds: number[];
  status: string;
  title: string;
  tomes: MergePreviewTome[];
  type: string;
}

export interface MergePreviewTome {
  bought: boolean;
  downloaded: boolean;
  isbn: string | null;
  number: number;
  onNas: boolean;
  read: boolean;
  title: string | null;
  tomeEnd: number | null;
}

export interface MergeSuggestion {
  entries: { id: number; tomeNumber: number | null }[];
  title: string;
}

export interface ImportExcelResult {
  sheetDetails: Record<
    string,
    { created: number; tomes: number; updated: number }
  >;
  totalCreated: number;
  totalTomes: number;
  totalUpdated: number;
}

export interface ImportBooksResult {
  created: number;
  enriched: number;
  groupCount: number;
}

export interface CoverSearchResult {
  height: number;
  thumbnail: string;
  title: string;
  url: string;
  width: number;
}

export interface BatchLookupProgress {
  current: number;
  seriesTitle: string;
  status: "failed" | "skipped" | "updated";
  total: number;
  updatedFields: string[];
}

export interface BatchLookupSummary {
  failed: number;
  processed: number;
  skipped: number;
  updated: number;
}

export interface CreateComicPayload {
  _pendingAuthors?: string[];
  amazonUrl: string | null;
  authors: string[];
  coverUrl: string | null;
  defaultTomeBought: boolean;
  defaultTomeDownloaded: boolean;
  defaultTomeRead: boolean;
  description: string | null;
  isOneShot: boolean;
  latestPublishedIssue: number | null;
  latestPublishedIssueComplete: boolean;
  lookupCompletedAt?: string;
  publishedDate: string | null;
  publisher: string | null;
  status: ComicStatus;
  title: string;
  tomes?: TomePayload[];
  type: ComicType;
}

export interface UpdateComicPayload extends Partial<CreateComicPayload> {
  id: number;
}

export interface TomePayload {
  "@id"?: string;
  bought: boolean;
  downloaded: boolean;
  isHorsSerie: boolean;
  isbn: string | null;
  number: number;
  onNas: boolean;
  read: boolean;
  title: string | null;
  tomeEnd: number | null;
}

export interface CreateTomePayload {
  bought: boolean;
  downloaded: boolean;
  isHorsSerie: boolean;
  isbn: string | null;
  number: number;
  onNas: boolean;
  read: boolean;
  title: string | null;
  tomeEnd: number | null;
}

export interface LookupResult {
  apiMessages: Record<string, { message: string; status: string }>;
  authors: string | null;
  description: string | null;
  isbn: string | null;
  isOneShot: boolean | null;
  latestPublishedIssue: number | null;
  publishedDate: string | null;
  publisher: string | null;
  sources: string[];
  thumbnail: string | null;
  title: string | null;
  tomeEnd: number | null;
  tomeNumber: number | null;
}

export interface LookupCandidatesResponse {
  apiMessages: Record<string, { message: string; status: string }>;
  results: LookupCandidate[];
  sources: string[];
}

export interface LookupCandidate {
  authors: string | null;
  description: string | null;
  isbn: string | null;
  isOneShot: boolean | null;
  latestPublishedIssue: number | null;
  publishedDate: string | null;
  publisher: string | null;
  thumbnail: string | null;
  title: string | null;
  tomeEnd: number | null;
  tomeNumber: number | null;
}

export interface EnrichmentProposal {
  "@id": string;
  comicSeries: { "@id": string; id: number; title: string };
  confidence: EnrichmentConfidence;
  createdAt: string;
  currentValue: unknown;
  field: string;
  id: number;
  proposedValue: unknown;
  reviewedAt: string | null;
  source: string;
  status: ProposalStatus;
}

export interface SeriesSuggestion {
  "@id": string;
  authors: string[];
  createdAt: string;
  id: number;
  reason: string;
  sourceSeries: { id: number; title: string } | null;
  status: SuggestionStatus;
  title: string;
  type: ComicType;
}

