import { http, HttpResponse } from "msw";
import {
  createMockAuthor,
  createMockComicSeries,
  createMockHydraCollection,
  createMockTome,
} from "./factories";

const API_BASE = "/api";

/** Handlers par défaut — réponses vides/minimales. Surcharger dans chaque test si besoin. */
export const handlers = [
  // ComicSeries collection
  http.get(`${API_BASE}/comic_series`, () =>
    HttpResponse.json(createMockHydraCollection([])),
  ),

  // ComicSeries single
  http.get(`${API_BASE}/comic_series/:id`, ({ params }) =>
    HttpResponse.json(
      createMockComicSeries({ id: Number(params.id), title: "Test Series" }),
    ),
  ),

  // Create comic
  http.post(`${API_BASE}/comic_series`, async ({ request }) => {
    const body = (await request.json()) as Record<string, unknown>;
    return HttpResponse.json(
      createMockComicSeries({ title: (body.title as string) ?? "New Series" }),
      { status: 201 },
    );
  }),

  // Update comic (PATCH)
  http.patch(`${API_BASE}/comic_series/:id`, ({ params }) =>
    HttpResponse.json(
      createMockComicSeries({ id: Number(params.id), title: "Updated Series" }),
    ),
  ),

  // Delete comic (soft)
  http.delete(`${API_BASE}/comic_series/:id`, () =>
    new HttpResponse(null, { status: 204 }),
  ),

  // Trash collection
  http.get(`${API_BASE}/trash`, () =>
    HttpResponse.json(createMockHydraCollection([], "/api/trash")),
  ),

  // Restore
  http.put(`${API_BASE}/comic_series/:id/restore`, ({ params }) =>
    HttpResponse.json(createMockComicSeries({ id: Number(params.id) })),
  ),

  // Permanent delete
  http.delete(`${API_BASE}/trash/:id/permanent`, () =>
    new HttpResponse(null, { status: 204 }),
  ),

  // Tomes sub-resource
  http.get(`${API_BASE}/comic_series/:id/tomes`, () =>
    HttpResponse.json(createMockHydraCollection([], "/api/tomes")),
  ),

  // Update tome (partial)
  http.patch(`${API_BASE}/tomes/:id`, ({ params }) =>
    HttpResponse.json(
      createMockTome({ id: Number(params.id) }),
    ),
  ),

  // Authors
  http.get(`${API_BASE}/authors`, () =>
    HttpResponse.json(
      createMockHydraCollection([createMockAuthor()], "/api/authors"),
    ),
  ),

  // Lookup ISBN
  http.get(`${API_BASE}/lookup/isbn`, () =>
    HttpResponse.json({
      apiMessages: [],
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
    }),
  ),

  // Lookup title
  http.get(`${API_BASE}/lookup/title`, () =>
    HttpResponse.json({
      apiMessages: [],
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
    }),
  ),

  // Google login
  http.post(`${API_BASE}/login/google`, () =>
    HttpResponse.json({ token: "fake-jwt-token" }),
  ),
];
