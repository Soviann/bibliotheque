import type { ComicStatus, ComicType } from "./enums";

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
  authors: Author[];
  coverImage: string | null;
  coverUrl: string | null;
  createdAt: string;
  description: string | null;
  id: number;
  isOneShot: boolean;
  latestPublishedIssue: number | null;
  latestPublishedIssueComplete: boolean;
  publishedDate: string | null;
  publisher: string | null;
  status: ComicStatus;
  title: string;
  tomes: Tome[];
  type: ComicType;
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
  authors: string[];
  coverUrl: string | null;
  description: string | null;
  isOneShot: boolean;
  latestPublishedIssue: number | null;
  latestPublishedIssueComplete: boolean;
  publisher: string | null;
  sourceSeriesIds: number[];
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

export interface ImportExcelResult {
  sheetDetails: Record<string, { series: number; tomes: number }>;
  totalSeries: number;
  totalTomes: number;
}

export interface ImportBooksResult {
  created: number;
  enriched: number;
  groupCount: number;
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
