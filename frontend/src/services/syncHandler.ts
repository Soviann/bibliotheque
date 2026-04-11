import {
  addSyncFailure,
  getAll,
  removeById,
  updatePayload,
  updateStatus,
} from "./offlineQueue";
import type { QueueItem } from "./offlineQueue";

type PostMessageFn = (message: Record<string, unknown>) => void;

function buildUrl(item: QueueItem, tempIdMap: Map<string, string>): string {
  const {
    operation,
    parentResourceId,
    parentResourceType,
    resourceId,
    resourceType,
  } = item;

  // Sous-ressource : POST /api/comic_series/{parentId}/tomes
  if (operation === "create" && parentResourceType && parentResourceId) {
    const resolvedParentId =
      tempIdMap.get(parentResourceId) ?? parentResourceId;
    const parentBase =
      parentResourceType === "comic_series" ? "comic_series" : "tomes";
    const childBase = resourceType === "tome" ? "tomes" : "comic_series";
    return `/api/${parentBase}/${resolvedParentId}/${childBase}`;
  }

  const base = `/api/${resourceType === "comic_series" ? "comic_series" : "tomes"}`;

  if (operation === "create") return base;

  const resolvedId = resourceId
    ? (tempIdMap.get(resourceId) ?? resourceId)
    : undefined;
  if (!resolvedId) throw new Error(`resourceId required for ${operation}`);
  return `${base}/${resolvedId}`;
}

function buildMethod(item: QueueItem): string {
  if (item.httpMethod) return item.httpMethod;
  switch (item.operation) {
    case "create":
      return "POST";
    case "delete":
      return "DELETE";
    case "update":
      return "PATCH";
  }
}

function buildContentType(item: QueueItem): string {
  if (item.contentType) return item.contentType;
  return item.operation === "update"
    ? "application/merge-patch+json"
    : "application/ld+json";
}

export async function processSyncQueue(
  token: string,
  postMessage: PostMessageFn,
): Promise<void> {
  const items = await getAll();

  if (items.length === 0) {
    postMessage({ count: 0, type: "sync-complete" });
    return;
  }

  postMessage({ type: "sync-start" });

  let syncedCount = 0;
  const tempIdMap = new Map<string, string>();

  for (const item of items) {
    if (!item.id) continue;

    await updateStatus(item.id, "syncing");

    // Créer les auteurs en attente (ajoutés hors ligne) avant d'envoyer la série
    if (Array.isArray(item.payload._pendingAuthors)) {
      const pendingAuthors = item.payload._pendingAuthors as string[];
      const existingIris = (item.payload.authors as string[]) ?? [];
      const newIris: string[] = [];

      for (const name of pendingAuthors) {
        const res = await fetch("/api/authors", {
          body: JSON.stringify({ name }),
          headers: {
            Accept: "application/ld+json",
            Authorization: `Bearer ${token}`,
            "Content-Type": "application/ld+json",
          },
          method: "POST",
        });
        if (res.ok) {
          const author = (await res.json()) as { "@id": string };
          newIris.push(author["@id"]);
        }
      }

      item.payload.authors = [...existingIris, ...newIris];
      delete item.payload._pendingAuthors;
      await updatePayload(item.id, item.payload);
    }

    const url = buildUrl(item, tempIdMap);
    const method = buildMethod(item);
    const headers: Record<string, string> = {
      Accept: "application/ld+json",
      Authorization: `Bearer ${token}`,
    };

    if (method !== "DELETE") {
      headers["Content-Type"] = buildContentType(item);
    }

    const response = await fetch(url, {
      body: method !== "DELETE" ? JSON.stringify(item.payload) : undefined,
      headers,
      method,
    });

    if (response.ok) {
      // Stocker le mapping temp ID → real ID pour les créations
      if (item.operation === "create" && item.resourceId) {
        try {
          const responseData = (await response.clone().json()) as {
            id?: number;
          };
          if (responseData.id) {
            tempIdMap.set(item.resourceId, String(responseData.id));
          }
        } catch {
          // Ignorer les erreurs de parsing JSON (ex: 204 No Content)
        }
      }

      await removeById(item.id);
      syncedCount++;
    } else if (response.status >= 400 && response.status < 500) {
      const errorBody = await response.json().catch(() => ({}));
      const detail =
        (errorBody as { detail?: string }).detail ??
        `Erreur ${response.status}`;
      await removeById(item.id);

      const failure = await addSyncFailure({
        error: detail,
        httpStatus: response.status,
        operation: item.operation,
        parentResourceId: item.parentResourceId,
        payload: item.payload,
        resourceId: item.resourceId,
        resourceType: item.resourceType,
      });

      postMessage({
        failure: {
          error: detail,
          httpStatus: response.status,
          id: failure,
          operation: item.operation,
          resourceType: item.resourceType,
        },
        type: "sync-failure",
      });
    } else {
      await updateStatus(item.id, "pending");
      throw new Error(`Server error ${response.status} — retry later`);
    }
  }

  postMessage({ count: syncedCount, type: "sync-complete" });
}
