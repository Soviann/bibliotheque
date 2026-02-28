import { dequeue, getAll, updateStatus } from "./offlineQueue";
import type { OperationType, ResourceType } from "./offlineQueue";

type PostMessageFn = (message: Record<string, unknown>) => void;

function buildUrl(resourceType: ResourceType, operation: OperationType, resourceId?: string): string {
  const base = `/api/${resourceType === "comic_series" ? "comic_series" : "tomes"}`;

  if (operation === "create") return base;
  if (!resourceId) throw new Error(`resourceId required for ${operation}`);
  return `${base}/${resourceId}`;
}

function buildMethod(operation: OperationType): string {
  switch (operation) {
    case "create": return "POST";
    case "delete": return "DELETE";
    case "update": return "PUT";
  }
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

  for (const item of items) {
    if (!item.id) continue;

    await updateStatus(item.id, "syncing");

    const url = buildUrl(item.resourceType, item.operation, item.resourceId);
    const method = buildMethod(item.operation);
    const headers: Record<string, string> = {
      Accept: "application/ld+json",
      Authorization: `Bearer ${token}`,
    };

    if (method !== "DELETE") {
      headers["Content-Type"] = "application/ld+json";
    }

    const response = await fetch(url, {
      body: method !== "DELETE" ? JSON.stringify(item.payload) : undefined,
      headers,
      method,
    });

    if (response.ok) {
      await dequeue();
      syncedCount++;
    } else if (response.status >= 400 && response.status < 500) {
      const errorBody = await response.json().catch(() => ({}));
      const detail = (errorBody as { detail?: string }).detail ?? `Erreur ${response.status}`;
      await dequeue();
      postMessage({ error: detail, type: "sync-error" });
    } else {
      await updateStatus(item.id, "pending");
      throw new Error(`Server error ${response.status} — retry later`);
    }
  }

  postMessage({ count: syncedCount, type: "sync-complete" });
}
