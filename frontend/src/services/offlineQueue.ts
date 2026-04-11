import { type DBSchema, type IDBPDatabase, openDB } from "idb";

export type OperationType = "create" | "delete" | "update";
export type ResourceType = "comic_series" | "tome";
export type QueueItemStatus = "failed" | "pending" | "syncing";

export interface QueueItem {
  contentType?: string;
  httpMethod?: string;
  id?: number;
  operation: OperationType;
  parentResourceId?: string;
  parentResourceType?: ResourceType;
  payload: Record<string, unknown>;
  resourceId?: string;
  resourceType: ResourceType;
  retryCount: number;
  status: QueueItemStatus;
  timestamp: number;
}

export interface SyncFailure {
  error: string;
  httpStatus: number;
  id?: number;
  operation: OperationType;
  parentResourceId?: string;
  payload: Record<string, unknown>;
  resolved: boolean;
  resourceId?: string;
  resourceType: ResourceType;
  timestamp: number;
}

interface OfflineDB extends DBSchema {
  offlineQueue: {
    key: number;
    value: QueueItem;
    indexes: { "by-status": QueueItemStatus; "by-timestamp": number };
  };
  syncFailures: {
    key: number;
    value: SyncFailure;
    indexes: { "by-resolved": number };
  };
}

const DB_NAME = "bibliotheque-offline";
const DB_VERSION = 2;

let dbPromise: Promise<IDBPDatabase<OfflineDB>> | null = null;

function getDb(): Promise<IDBPDatabase<OfflineDB>> {
  if (!dbPromise) {
    dbPromise = openDB<OfflineDB>(DB_NAME, DB_VERSION, {
      upgrade(db, oldVersion) {
        if (oldVersion < 1) {
          const store = db.createObjectStore("offlineQueue", {
            autoIncrement: true,
            keyPath: "id",
          });
          store.createIndex("by-status", "status");
          store.createIndex("by-timestamp", "timestamp");
        }
        if (oldVersion < 2) {
          const failureStore = db.createObjectStore("syncFailures", {
            autoIncrement: true,
            keyPath: "id",
          });
          failureStore.createIndex("by-resolved", "resolved");
        }
      },
    });
  }
  return dbPromise;
}

export async function enqueue(
  item: Pick<
    QueueItem,
    "operation" | "payload" | "resourceId" | "resourceType"
  > &
    Partial<
      Pick<
        QueueItem,
        "contentType" | "httpMethod" | "parentResourceId" | "parentResourceType"
      >
    >,
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

export async function updatePayload(
  id: number,
  payload: Record<string, unknown>,
): Promise<void> {
  const db = await getDb();
  const item = await db.get("offlineQueue", id);
  if (!item) return;

  item.payload = payload;
  await db.put("offlineQueue", item);
}

export async function updateStatus(
  id: number,
  status: QueueItemStatus,
): Promise<void> {
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
  const pending = await db.countFromIndex(
    "offlineQueue",
    "by-status",
    "pending",
  );
  const failed = await db.countFromIndex("offlineQueue", "by-status", "failed");
  return pending + failed;
}

export async function addSyncFailure(
  failure: Omit<SyncFailure, "id" | "resolved" | "timestamp">,
): Promise<number> {
  const db = await getDb();
  return db.add("syncFailures", {
    ...failure,
    resolved: false,
    timestamp: Date.now(),
  });
}

export async function getSyncFailures(): Promise<SyncFailure[]> {
  const db = await getDb();
  const all = await db.getAll("syncFailures");
  return all.filter((f) => !f.resolved);
}

export async function resolveSyncFailure(id: number): Promise<void> {
  const db = await getDb();
  const failure = await db.get("syncFailures", id);
  if (!failure) return;
  failure.resolved = true;
  await db.put("syncFailures", failure);
}

export async function removeSyncFailure(id: number): Promise<void> {
  const db = await getDb();
  await db.delete("syncFailures", id);
}

export async function getUnresolvedFailureCount(): Promise<number> {
  const db = await getDb();
  const all = await db.getAll("syncFailures");
  return all.filter((f) => !f.resolved).length;
}

export async function _resetDb(): Promise<void> {
  if (dbPromise) {
    const db = await dbPromise;
    db.close();
    dbPromise = null;
  }
}
