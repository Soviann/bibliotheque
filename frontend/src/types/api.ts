import type { ComicStatus, ComicType } from "./enums";

export interface HydraCollection<T> {
  "@context": string;
  "@id": string;
  "@type": "hydra:Collection";
  "hydra:member": T[];
  "hydra:totalItems": number;
  "hydra:view"?: {
    "@id": string;
    "@type": "hydra:PartialCollectionView";
    "hydra:first"?: string;
    "hydra:last"?: string;
    "hydra:next"?: string;
    "hydra:previous"?: string;
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
  authors: string[];
  coverUrl: string | null;
  description: string | null;
  isOneShot: boolean;
  publisher: string | null;
  sources: string[];
  title: string;
  totalVolumes: number | null;
  type: string | null;
}
