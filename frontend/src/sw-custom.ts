/// <reference lib="webworker" />
import { clientsClaim } from "workbox-core";
import { ExpirationPlugin } from "workbox-expiration";
import { cleanupOutdatedCaches, precacheAndRoute } from "workbox-precaching";
import { registerRoute } from "workbox-routing";
import { CacheFirst, NetworkFirst } from "workbox-strategies";
import { processSyncQueue } from "./services/syncHandler";

declare const self: ServiceWorkerGlobalScope;

// Workbox precaching (injected by VitePWA)
precacheAndRoute(self.__WB_MANIFEST);
cleanupOutdatedCaches();
clientsClaim();

// Runtime caching — API
registerRoute(
  /^\/api\//,
  new NetworkFirst({
    cacheName: "api-cache",
    networkTimeoutSeconds: 3,
    plugins: [new ExpirationPlugin({ maxAgeSeconds: 7 * 24 * 60 * 60, maxEntries: 200 })],
  }),
);

// Runtime caching — local covers
registerRoute(
  /\/uploads\/covers\//,
  new CacheFirst({
    cacheName: "cover-cache",
    plugins: [new ExpirationPlugin({ maxAgeSeconds: 30 * 24 * 60 * 60, maxEntries: 500 })],
  }),
);

// Runtime caching — external covers (Google Books)
registerRoute(
  /^https:\/\/books\.google\.com\//,
  new CacheFirst({
    cacheName: "external-cover-cache",
    plugins: [new ExpirationPlugin({ maxAgeSeconds: 30 * 24 * 60 * 60, maxEntries: 500 })],
  }),
);

// Background Sync handler
self.addEventListener("sync", (event: SyncEvent) => {
  if (event.tag === "offline-sync") {
    event.waitUntil(handleOfflineSync());
  }
});

async function handleOfflineSync(): Promise<void> {
  const clients = await self.clients.matchAll({ type: "window" });
  if (clients.length === 0) return;

  const client = clients[0];
  const token = await getTokenFromClient(client);
  if (!token) return;

  await processSyncQueue(token, (message) => {
    for (const c of clients) {
      c.postMessage(message);
    }

    // Notification pour les erreurs de sync
    if (message.type === "sync-failure" && Notification.permission === "granted") {
      const failure = message.failure as { error?: string } | undefined;
      void self.registration.showNotification("Erreur de synchronisation", {
        body: failure?.error ?? "Une opération hors ligne a échoué",
        icon: "/app-icon.png",
      });
    }
  });
}

function getTokenFromClient(client: Client): Promise<string | null> {
  return new Promise((resolve) => {
    const channel = new MessageChannel();
    channel.port1.onmessage = (event) => {
      resolve(event.data?.token ?? null);
    };
    client.postMessage({ type: "get-token" }, [channel.port2]);

    setTimeout(() => resolve(null), 3000);
  });
}
