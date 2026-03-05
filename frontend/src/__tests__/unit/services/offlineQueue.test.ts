import "fake-indexeddb/auto";
import { beforeEach, describe, expect, it } from "vitest";
import {
  _resetDb,
  addSyncFailure,
  clearQueue,
  dequeue,
  enqueue,
  getAll,
  getPendingCount,
  getSyncFailures,
  getUnresolvedFailureCount,
  removeById,
  removeSyncFailure,
  resolveSyncFailure,
  updateStatus,
} from "../../../services/offlineQueue";
import type { QueueItem } from "../../../services/offlineQueue";

describe("offlineQueue", () => {
  beforeEach(async () => {
    await _resetDb();
    // Delete the database to start fresh
    await new Promise<void>((resolve, reject) => {
      const req = indexedDB.deleteDatabase("bibliotheque-offline");
      req.onsuccess = () => resolve();
      req.onerror = () => reject(req.error);
    });
  });

  describe("enqueue", () => {
    it("stores a mutation in the queue and returns an id", async () => {
      const id = await enqueue({
        operation: "create",
        payload: { title: "Test Comic" },
        resourceType: "comic_series",
      });

      expect(id).toBeGreaterThan(0);
    });

    it("sets default status to pending and retryCount to 0", async () => {
      await enqueue({
        operation: "create",
        payload: { title: "Test" },
        resourceType: "comic_series",
      });

      const items = await getAll();
      expect(items).toHaveLength(1);
      expect(items[0].status).toBe("pending");
      expect(items[0].retryCount).toBe(0);
    });

    it("sets a timestamp on the queued item", async () => {
      const before = Date.now();
      await enqueue({
        operation: "update",
        payload: { title: "Updated" },
        resourceId: "123",
        resourceType: "comic_series",
      });
      const after = Date.now();

      const items = await getAll();
      expect(items[0].timestamp).toBeGreaterThanOrEqual(before);
      expect(items[0].timestamp).toBeLessThanOrEqual(after);
    });

    it("can store multiple items", async () => {
      await enqueue({
        operation: "create",
        payload: { title: "First" },
        resourceType: "comic_series",
      });
      await enqueue({
        operation: "create",
        payload: { title: "Second" },
        resourceType: "tome",
      });

      const items = await getAll();
      expect(items).toHaveLength(2);
    });
  });

  describe("getAll", () => {
    it("returns empty array when queue is empty", async () => {
      const items = await getAll();
      expect(items).toEqual([]);
    });

    it("returns items ordered by timestamp", async () => {
      await enqueue({
        operation: "create",
        payload: { title: "First" },
        resourceType: "comic_series",
      });
      await enqueue({
        operation: "create",
        payload: { title: "Second" },
        resourceType: "comic_series",
      });

      const items = await getAll();
      expect(items[0].timestamp).toBeLessThanOrEqual(items[1].timestamp);
    });
  });

  describe("dequeue", () => {
    it("returns undefined when queue is empty", async () => {
      const item = await dequeue();
      expect(item).toBeUndefined();
    });

    it("returns and removes the oldest item", async () => {
      await enqueue({
        operation: "create",
        payload: { title: "First" },
        resourceType: "comic_series",
      });
      await enqueue({
        operation: "create",
        payload: { title: "Second" },
        resourceType: "comic_series",
      });

      const item = await dequeue();
      expect((item as QueueItem).payload.title).toBe("First");

      const remaining = await getAll();
      expect(remaining).toHaveLength(1);
      expect(remaining[0].payload.title).toBe("Second");
    });
  });

  describe("removeById", () => {
    it("removes a specific item by id", async () => {
      const id = await enqueue({
        operation: "create",
        payload: { title: "To Remove" },
        resourceType: "comic_series",
      });

      await removeById(id);

      const items = await getAll();
      expect(items).toHaveLength(0);
    });

    it("does not throw when removing non-existent id", async () => {
      await expect(removeById(999)).resolves.toBeUndefined();
    });
  });

  describe("updateStatus", () => {
    it("updates status of an item", async () => {
      const id = await enqueue({
        operation: "create",
        payload: { title: "Test" },
        resourceType: "comic_series",
      });

      await updateStatus(id, "syncing");

      const items = await getAll();
      expect(items[0].status).toBe("syncing");
    });

    it("increments retryCount when status is set to failed", async () => {
      const id = await enqueue({
        operation: "create",
        payload: { title: "Test" },
        resourceType: "comic_series",
      });

      await updateStatus(id, "failed");
      let items = await getAll();
      expect(items[0].retryCount).toBe(1);

      await updateStatus(id, "failed");
      items = await getAll();
      expect(items[0].retryCount).toBe(2);
    });

    it("does not increment retryCount for non-failed statuses", async () => {
      const id = await enqueue({
        operation: "create",
        payload: { title: "Test" },
        resourceType: "comic_series",
      });

      await updateStatus(id, "syncing");

      const items = await getAll();
      expect(items[0].retryCount).toBe(0);
    });

    it("silently does nothing for non-existent id", async () => {
      await expect(updateStatus(999, "failed")).resolves.toBeUndefined();
    });
  });

  describe("clearQueue", () => {
    it("removes all items from the queue", async () => {
      await enqueue({
        operation: "create",
        payload: { title: "First" },
        resourceType: "comic_series",
      });
      await enqueue({
        operation: "create",
        payload: { title: "Second" },
        resourceType: "tome",
      });

      await clearQueue();

      const items = await getAll();
      expect(items).toEqual([]);
    });
  });

  describe("getPendingCount", () => {
    it("returns 0 for empty queue", async () => {
      const count = await getPendingCount();
      expect(count).toBe(0);
    });

    it("counts pending and failed items", async () => {
      const id1 = await enqueue({
        operation: "create",
        payload: { title: "Pending" },
        resourceType: "comic_series",
      });
      const id2 = await enqueue({
        operation: "create",
        payload: { title: "Failed" },
        resourceType: "comic_series",
      });
      await enqueue({
        operation: "create",
        payload: { title: "Syncing" },
        resourceType: "comic_series",
      });

      await updateStatus(id2, "failed");
      // Make the third item "syncing" — it should NOT be counted
      const items = await getAll();
      const syncingId = items.find((i) => i.payload.title === "Syncing")?.id;
      if (syncingId) await updateStatus(syncingId, "syncing");

      const count = await getPendingCount();
      // pending (id1) + failed (id2) = 2, syncing is excluded
      expect(count).toBe(2);
    });
  });

  describe("enqueue with new optional fields", () => {
    it("stores parentResourceType and parentResourceId", async () => {
      await enqueue({
        operation: "create",
        parentResourceId: "5",
        parentResourceType: "comic_series",
        payload: { number: 1 },
        resourceType: "tome",
      });

      const items = await getAll();
      expect(items).toHaveLength(1);
      expect(items[0].parentResourceType).toBe("comic_series");
      expect(items[0].parentResourceId).toBe("5");
    });

    it("stores httpMethod and contentType", async () => {
      await enqueue({
        contentType: "application/merge-patch+json",
        httpMethod: "PATCH",
        operation: "update",
        payload: { bought: true },
        resourceId: "10",
        resourceType: "tome",
      });

      const items = await getAll();
      expect(items[0].httpMethod).toBe("PATCH");
      expect(items[0].contentType).toBe("application/merge-patch+json");
    });
  });

  describe("syncFailures", () => {
    it("adds and retrieves sync failures", async () => {
      await addSyncFailure({
        error: "Validation failed",
        httpStatus: 422,
        operation: "create",
        payload: { title: "Bad" },
        resourceType: "comic_series",
      });

      const failures = await getSyncFailures();
      expect(failures).toHaveLength(1);
      expect(failures[0].error).toBe("Validation failed");
      expect(failures[0].httpStatus).toBe(422);
      expect(failures[0].resolved).toBe(false);
    });

    it("resolves a sync failure", async () => {
      const id = await addSyncFailure({
        error: "Not found",
        httpStatus: 404,
        operation: "update",
        payload: {},
        resourceId: "99",
        resourceType: "comic_series",
      });

      await resolveSyncFailure(id);

      const failures = await getSyncFailures();
      expect(failures).toHaveLength(0);
    });

    it("removes a sync failure", async () => {
      const id = await addSyncFailure({
        error: "Error",
        httpStatus: 400,
        operation: "delete",
        payload: {},
        resourceType: "tome",
      });

      await removeSyncFailure(id);

      const failures = await getSyncFailures();
      expect(failures).toHaveLength(0);
    });

    it("returns unresolved failure count", async () => {
      const id1 = await addSyncFailure({
        error: "Error 1",
        httpStatus: 422,
        operation: "create",
        payload: {},
        resourceType: "comic_series",
      });
      await addSyncFailure({
        error: "Error 2",
        httpStatus: 400,
        operation: "update",
        payload: {},
        resourceType: "tome",
      });

      await resolveSyncFailure(id1);

      const count = await getUnresolvedFailureCount();
      expect(count).toBe(1);
    });

    it("silently does nothing when resolving non-existent failure", async () => {
      await expect(resolveSyncFailure(999)).resolves.toBeUndefined();
    });
  });
});
