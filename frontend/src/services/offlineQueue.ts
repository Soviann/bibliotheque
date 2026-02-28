import { type DBSchema, type IDBPDatabase, openDB } from "idb";

export type OperationType = "create" | "delete" | "update";
export type ResourceType = "comic_series" | "tome";
export type QueueItemStatus = "failed" | "pending" | "syncing";

export interface QueueItem {
  id?: number;
  operation: OperationType;
  payload: Record<string, unknown>;
  resourceId?: string;
  resourceType: ResourceType;
  retryCount: number;
  status: QueueItemStatus;
  timestamp: number;
}

interface OfflineDB extends DBSchema {
  offlineQueue: {
    key: number;
    value: QueueItem;
    indexes: { "by-status": QueueItemStatus; "by-timestamp": number };
  };
}

const DB_NAME = "bibliotheque-offline";
const DB_VERSION = 1;

let dbPromise: Promise<IDBPDatabase<OfflineDB>> | null = null;

function getDb(): Promise<IDBPDatabase<OfflineDB>> {
  if (!dbPromise) {
    dbPromise = openDB<OfflineDB>(DB_NAME, DB_VERSION, {
      upgrade(db) {
        const store = db.createObjectStore("offlineQueue", {
          autoIncrement: true,
          keyPath: "id",
        });
        store.createIndex("by-status", "status");
        store.createIndex("by-timestamp", "timestamp");
      },
    });
  }
  return dbPromise;
}

export async function enqueue(
  item: Pick<QueueItem, "operation" | "payload" | "resourceId" | "resourceType">,
): Promise<number> {
  const db = await getDb();
  return db.add("offlineQueue", {
    ...item,
    retryCount: 0,
    status: "pending",
    timestamp: Date.now(),
  });
}

export async function getAll(): Promise<QueueItem[]> {
  const db = await getDb();
  return db.getAllFromIndex("offlineQueue", "by-timestamp");
}

export async function dequeue(): Promise<QueueItem | undefined> {
  const db = await getDb();
  const tx = db.transaction("offlineQueue", "readwrite");
  const index = tx.store.index("by-timestamp");
  const cursor = await index.openCursor();

  if (!cursor) {
    await tx.done;
    return undefined;
  }

  const item = cursor.value;
  await cursor.delete();
  await tx.done;
  return item;
}

export async function updateStatus(id: number, status: QueueItemStatus): Promise<void> {
  const db = await getDb();
  const item = await db.get("offlineQueue", id);
  if (!item) return;

  item.status = status;
  if (status === "failed") {
    item.retryCount += 1;
  }
  await db.put("offlineQueue", item);
}

export async function removeById(id: number): Promise<void> {
  const db = await getDb();
  await db.delete("offlineQueue", id);
}

export async function clearQueue(): Promise<void> {
  const db = await getDb();
  await db.clear("offlineQueue");
}

export async function getPendingCount(): Promise<number> {
  const db = await getDb();
  const all = await db.getAll("offlineQueue");
  return all.filter((item) => item.status === "pending" || item.status === "failed").length;
}

export async function _resetDb(): Promise<void> {
  if (dbPromise) {
    const db = await dbPromise;
    db.close();
    dbPromise = null;
  }
}
