import "fake-indexeddb/auto";
import { beforeEach, describe, expect, it } from "vitest";
import {
  clearQueue,
  dequeue,
  enqueue,
  getAll,
  getPendingCount,
  updateStatus,
  _resetDb,
} from "../../services/offlineQueue";

beforeEach(async () => {
  await _resetDb();
  const dbs = await indexedDB.databases();
  for (const db of dbs) {
    if (db.name) indexedDB.deleteDatabase(db.name);
  }
});

describe("offlineQueue", () => {
  it("enqueue adds an operation and getAll retrieves it", async () => {
    await enqueue({
      operation: "create",
      payload: { title: "Test" },
      resourceType: "comic_series",
    });

    const items = await getAll();
    expect(items).toHaveLength(1);
    expect(items[0]).toMatchObject({
      operation: "create",
      payload: { title: "Test" },
      resourceType: "comic_series",
      retryCount: 0,
      status: "pending",
    });
    expect(items[0].id).toBeDefined();
    expect(items[0].timestamp).toBeGreaterThan(0);
  });

  it("enqueue multiple items preserves FIFO order", async () => {
    await enqueue({ operation: "create", payload: { title: "First" }, resourceType: "comic_series" });
    await enqueue({ operation: "update", payload: { title: "Second" }, resourceId: "1", resourceType: "comic_series" });

    const items = await getAll();
    expect(items).toHaveLength(2);
    expect(items[0].payload.title).toBe("First");
    expect(items[1].payload.title).toBe("Second");
  });

  it("dequeue removes and returns the oldest item", async () => {
    await enqueue({ operation: "create", payload: { title: "First" }, resourceType: "comic_series" });
    await enqueue({ operation: "create", payload: { title: "Second" }, resourceType: "comic_series" });

    const item = await dequeue();
    expect(item?.payload.title).toBe("First");

    const remaining = await getAll();
    expect(remaining).toHaveLength(1);
    expect(remaining[0].payload.title).toBe("Second");
  });

  it("dequeue returns undefined when queue is empty", async () => {
    const item = await dequeue();
    expect(item).toBeUndefined();
  });

  it("updateStatus changes the status of an item", async () => {
    await enqueue({ operation: "create", payload: {}, resourceType: "comic_series" });
    const items = await getAll();
    const id = items[0].id!;

    await updateStatus(id, "syncing");
    const updated = await getAll();
    expect(updated[0].status).toBe("syncing");
  });

  it("updateStatus increments retryCount when setting to failed", async () => {
    await enqueue({ operation: "create", payload: {}, resourceType: "comic_series" });
    const items = await getAll();
    const id = items[0].id!;

    await updateStatus(id, "failed");
    const updated = await getAll();
    expect(updated[0].retryCount).toBe(1);
    expect(updated[0].status).toBe("failed");
  });

  it("clearQueue removes all items", async () => {
    await enqueue({ operation: "create", payload: {}, resourceType: "comic_series" });
    await enqueue({ operation: "update", payload: {}, resourceId: "1", resourceType: "comic_series" });

    await clearQueue();
    const items = await getAll();
    expect(items).toHaveLength(0);
  });

  it("getPendingCount returns count of pending and failed items", async () => {
    await enqueue({ operation: "create", payload: {}, resourceType: "comic_series" });
    await enqueue({ operation: "create", payload: {}, resourceType: "comic_series" });

    expect(await getPendingCount()).toBe(2);

    const items = await getAll();
    await updateStatus(items[0].id!, "syncing");
    expect(await getPendingCount()).toBe(1);
  });
});
