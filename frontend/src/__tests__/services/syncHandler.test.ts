import "fake-indexeddb/auto";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { enqueue, getAll, _resetDb } from "../../services/offlineQueue";
import { processSyncQueue } from "../../services/syncHandler";

describe("processSyncQueue", () => {
  const mockPostMessage = vi.fn();

  beforeEach(async () => {
    await _resetDb();
    const dbs = await indexedDB.databases();
    for (const db of dbs) {
      if (db.name) indexedDB.deleteDatabase(db.name);
    }
    vi.stubGlobal("fetch", vi.fn());
    mockPostMessage.mockClear();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it("processes queue FIFO and clears on success", async () => {
    vi.mocked(fetch)
      .mockResolvedValueOnce(new Response(JSON.stringify({ id: 1 }), { status: 201 }))
      .mockResolvedValueOnce(new Response(JSON.stringify({ id: 2 }), { status: 200 }));

    await enqueue({ operation: "create", payload: { title: "A" }, resourceType: "comic_series" });
    await enqueue({ operation: "update", payload: { title: "B" }, resourceId: "1", resourceType: "comic_series" });

    await processSyncQueue("fake-jwt-token", mockPostMessage);

    const remaining = await getAll();
    expect(remaining).toHaveLength(0);
    expect(mockPostMessage).toHaveBeenCalledWith({ count: 2, type: "sync-complete" });
  });

  it("sends correct HTTP method and path for each operation type", async () => {
    vi.mocked(fetch)
      .mockResolvedValueOnce(new Response(JSON.stringify({}), { status: 201 }))
      .mockResolvedValueOnce(new Response(JSON.stringify({}), { status: 200 }))
      .mockResolvedValueOnce(new Response(null, { status: 204 }));

    await enqueue({ operation: "create", payload: { title: "New" }, resourceType: "comic_series" });
    await enqueue({ operation: "update", payload: { title: "Edit" }, resourceId: "5", resourceType: "comic_series" });
    await enqueue({ operation: "delete", payload: {}, resourceId: "3", resourceType: "comic_series" });

    await processSyncQueue("token", mockPostMessage);

    expect(fetch).toHaveBeenNthCalledWith(1, "/api/comic_series", expect.objectContaining({ method: "POST" }));
    expect(fetch).toHaveBeenNthCalledWith(2, "/api/comic_series/5", expect.objectContaining({ method: "PUT" }));
    expect(fetch).toHaveBeenNthCalledWith(3, "/api/comic_series/3", expect.objectContaining({ method: "DELETE" }));
  });

  it("throws on server error to trigger Background Sync retry", async () => {
    vi.mocked(fetch).mockResolvedValueOnce(new Response(null, { status: 500 }));

    await enqueue({ operation: "create", payload: { title: "Fail" }, resourceType: "comic_series" });

    await expect(processSyncQueue("token", mockPostMessage)).rejects.toThrow();

    const remaining = await getAll();
    expect(remaining).toHaveLength(1);
  });

  it("skips 4xx errors (client errors) and posts error message", async () => {
    vi.mocked(fetch)
      .mockResolvedValueOnce(new Response(JSON.stringify({ detail: "Validation failed" }), { status: 422 }))
      .mockResolvedValueOnce(new Response(JSON.stringify({}), { status: 201 }));

    await enqueue({ operation: "create", payload: { title: "" }, resourceType: "comic_series" });
    await enqueue({ operation: "create", payload: { title: "Good" }, resourceType: "comic_series" });

    await processSyncQueue("token", mockPostMessage);

    const remaining = await getAll();
    expect(remaining).toHaveLength(0);
    expect(mockPostMessage).toHaveBeenCalledWith(
      expect.objectContaining({ type: "sync-error" }),
    );
  });

  it("removes the correct item by id, not the oldest", async () => {
    // First item fails (4xx), second succeeds — both should be removed correctly
    vi.mocked(fetch)
      .mockResolvedValueOnce(new Response(JSON.stringify({ detail: "Bad" }), { status: 400 }))
      .mockResolvedValueOnce(new Response(JSON.stringify({ id: 2 }), { status: 201 }));

    await enqueue({ operation: "create", payload: { title: "Bad" }, resourceType: "comic_series" });
    await enqueue({ operation: "create", payload: { title: "Good" }, resourceType: "comic_series" });

    await processSyncQueue("token", mockPostMessage);

    const remaining = await getAll();
    expect(remaining).toHaveLength(0);
  });

  it("creates pending authors before sending comic payload", async () => {
    // Author creation response, then comic creation response
    vi.mocked(fetch)
      .mockResolvedValueOnce(new Response(JSON.stringify({ "@id": "/api/authors/99", id: 99, name: "New Author" }), { status: 201 }))
      .mockResolvedValueOnce(new Response(JSON.stringify({ id: 1 }), { status: 201 }));

    await enqueue({
      operation: "create",
      payload: {
        _pendingAuthors: ["New Author"],
        authors: ["/api/authors/1"],
        title: "Test Comic",
      },
      resourceType: "comic_series",
    });

    await processSyncQueue("token", mockPostMessage);

    // First call: create author
    expect(fetch).toHaveBeenNthCalledWith(1, "/api/authors", expect.objectContaining({ method: "POST" }));
    // Second call: create comic with merged author IRIs
    const comicCall = vi.mocked(fetch).mock.calls[1];
    const body = JSON.parse(comicCall[1]!.body as string);
    expect(body.authors).toEqual(["/api/authors/1", "/api/authors/99"]);
    expect(body._pendingAuthors).toBeUndefined();

    expect(await getAll()).toHaveLength(0);
  });

  it("does nothing when queue is empty", async () => {
    await processSyncQueue("token", mockPostMessage);
    expect(fetch).not.toHaveBeenCalled();
    expect(mockPostMessage).toHaveBeenCalledWith({ count: 0, type: "sync-complete" });
  });
});
