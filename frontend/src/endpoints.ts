export const endpoints = {
  authors: "/authors",
  batchLookup: {
    preview: "/tools/batch-lookup/preview",
    run: "/tools/batch-lookup/run",
  },
  enrichment: {
    accept: (id: number) => `/enrichment_proposals/${id}/accept`,
    logs: "/enrichment_logs",
    proposals: "/enrichment_proposals",
    reject: (id: number) => `/enrichment_proposals/${id}/reject`,
  },
  comicSeries: {
    collection: "/comic_series",
    detail: (id: number) => `/comic_series/${id}`,
    restore: (id: number) => `/comic_series/${id}/restore`,
    tomes: (seriesId: number) => `/comic_series/${seriesId}/tomes`,
  },
  import: {
    books: "/tools/import/books",
    excel: "/tools/import/excel",
  },
  login: {
    google: "/login/google",
  },
  lookup: {
    covers: "/lookup/covers",
    isbn: "/lookup/isbn",
    title: "/lookup/title",
  },
  notificationPreferences: {
    collection: "/notification_preferences",
    detail: (id: number) => `/notification_preferences/${id}`,
  },
  notifications: {
    collection: "/notifications",
    detail: (id: number) => `/notifications/${id}`,
    readAll: "/notifications/read-all",
    unreadCount: "/notifications/unread-count",
  },
  mergeSeries: {
    detect: "/merge-series/detect",
    execute: "/merge-series/execute",
    preview: "/merge-series/preview",
    suggest: "/merge-series/suggest",
  },
  suggestions: {
    collection: "/series_suggestions",
    detail: (id: number) => `/series_suggestions/${id}`,
  },
  purge: {
    execute: "/tools/purge/execute",
    preview: "/tools/purge/preview",
  },
  tomes: {
    detail: (id: number) => `/tomes/${id}`,
  },
  trash: {
    collection: "/trash",
    permanent: (id: number) => `/trash/${id}/permanent`,
  },
} as const;
