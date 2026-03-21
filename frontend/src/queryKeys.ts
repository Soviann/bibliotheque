export const queryKeys = {
  authors: {
    search: (search: string) => ["authors", search] as const,
  },
  batchLookup: {
    preview: (type: string, force: boolean) => ["batch-lookup-preview", type, force] as const,
    previewPrefix: ["batch-lookup-preview"] as const,
  },
  enrichment: {
    logs: (seriesId: number) => ["enrichment-logs", seriesId] as const,
    proposals: (status?: string) => ["enrichment-proposals", status] as const,
    proposalsPrefix: ["enrichment-proposals"] as const,
  },
  comics: {
    all: ["comics"] as const,
    detail: (id: number | undefined) => ["comic", id] as const,
    detailPrefix: ["comic"] as const,
  },
  lookup: {
    covers: (query: string, type?: string) => ["lookup", "covers", query, type] as const,
    isbn: (isbn: string, type?: string) => ["lookup", "isbn", isbn, type] as const,
    title: (title: string, type?: string) => ["lookup", "title", title, type] as const,
    titleCandidates: (title: string, type?: string, limit?: number) =>
      ["lookup", "title-candidates", title, type, limit] as const,
  },
  offline: {
    queueCount: ["offline-queue-count"] as const,
    syncFailures: ["syncFailures"] as const,
  },
  purge: {
    preview: (days: number) => ["purge-preview", days] as const,
    previewPrefix: ["purge-preview"] as const,
  },
  trash: {
    all: ["trash"] as const,
  },
} as const;
