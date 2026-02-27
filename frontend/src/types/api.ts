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
  bought: boolean;
  createdAt: string;
  downloaded: boolean;
  id: number;
  isbn: string | null;
  number: number;
  onNas: boolean;
  read: boolean;
  title: string | null;
  updatedAt: string;
}

export interface ComicSeries {
  "@id": string;
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

export interface LookupResult {
  apiMessages: Array<{ level: string; message: string; source: string }>;
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
}
