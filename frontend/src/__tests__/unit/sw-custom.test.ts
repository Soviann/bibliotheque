import { describe, expect, it, vi, beforeEach } from "vitest";

// Mock workbox modules before importing sw-custom
vi.mock("workbox-core", () => ({
  clientsClaim: vi.fn(),
}));

vi.mock("workbox-precaching", () => ({
  cleanupOutdatedCaches: vi.fn(),
  precacheAndRoute: vi.fn(),
}));

vi.mock("workbox-routing", () => ({
  registerRoute: vi.fn(),
}));

vi.mock("workbox-strategies", () => ({
  CacheFirst: vi.fn(),
  NetworkFirst: vi.fn(),
}));

vi.mock("workbox-expiration", () => ({
  ExpirationPlugin: vi.fn(),
}));

const mockProcessSyncQueue = vi.fn();
vi.mock("../../services/syncHandler", () => ({
  processSyncQueue: mockProcessSyncQueue,
}));

const mockIdbGet = vi.fn();
vi.mock("idb-keyval", () => ({
  get: (...args: unknown[]) => mockIdbGet(...args),
}));

// Mock ServiceWorkerGlobalScope
const mockClients = {
  matchAll: vi.fn(),
};

const mockRegistration = {
  showNotification: vi.fn(),
};

const syncListeners: Map<string, (event: unknown) => void> = new Map();

Object.assign(globalThis, {
  self: {
    __WB_MANIFEST: [],
    addEventListener: (type: string, handler: (event: unknown) => void) => {
      syncListeners.set(type, handler);
    },
    clients: mockClients,
    registration: mockRegistration,
  },
});

// Polyfill MessageChannel for Node
if (typeof MessageChannel === "undefined") {
  class MockPort {
    onmessage: ((event: { data: unknown }) => void) | null = null;
    postMessage(data: unknown) {
      this.onmessage?.({ data });
    }
  }
  class MockMessageChannel {
    port1 = new MockPort();
    port2 = new MockPort();
  }
  Object.assign(globalThis, { MessageChannel: MockMessageChannel });
}

describe("sw-custom", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    syncListeners.clear();
  });

  it("registers workbox routes on import", async () => {
    const { registerRoute } = await import("workbox-routing");

    // Force re-evaluation
    vi.resetModules();
    // Re-mock after reset
    vi.doMock("workbox-core", () => ({ clientsClaim: vi.fn() }));
    vi.doMock("workbox-precaching", () => ({
      cleanupOutdatedCaches: vi.fn(),
      precacheAndRoute: vi.fn(),
    }));
    vi.doMock("workbox-routing", () => ({ registerRoute: vi.fn() }));
    vi.doMock("workbox-strategies", () => ({
      CacheFirst: vi.fn(),
      NetworkFirst: vi.fn(),
    }));
    vi.doMock("workbox-expiration", () => ({ ExpirationPlugin: vi.fn() }));
    vi.doMock("../../services/syncHandler", () => ({
      processSyncQueue: vi.fn(),
    }));
    vi.doMock("idb-keyval", () => ({ get: vi.fn() }));

    const routingModule = await import("workbox-routing");
    await import("../../sw-custom");

    // 3 routes: /api/, /uploads/covers/, Google Books
    expect(routingModule.registerRoute).toHaveBeenCalledTimes(3);
  });

  it("registers a sync event listener", async () => {
    vi.resetModules();
    syncListeners.clear();

    vi.doMock("workbox-core", () => ({ clientsClaim: vi.fn() }));
    vi.doMock("workbox-precaching", () => ({
      cleanupOutdatedCaches: vi.fn(),
      precacheAndRoute: vi.fn(),
    }));
    vi.doMock("workbox-routing", () => ({ registerRoute: vi.fn() }));
    vi.doMock("workbox-strategies", () => ({
      CacheFirst: vi.fn(),
      NetworkFirst: vi.fn(),
    }));
    vi.doMock("workbox-expiration", () => ({ ExpirationPlugin: vi.fn() }));
    vi.doMock("../../services/syncHandler", () => ({
      processSyncQueue: vi.fn(),
    }));
    vi.doMock("idb-keyval", () => ({ get: vi.fn() }));

    await import("../../sw-custom");

    expect(syncListeners.has("sync")).toBe(true);
  });

  it("sync handler calls processSyncQueue with token from IndexedDB", async () => {
    vi.resetModules();
    syncListeners.clear();

    vi.doMock("workbox-core", () => ({ clientsClaim: vi.fn() }));
    vi.doMock("workbox-precaching", () => ({
      cleanupOutdatedCaches: vi.fn(),
      precacheAndRoute: vi.fn(),
    }));
    vi.doMock("workbox-routing", () => ({ registerRoute: vi.fn() }));
    vi.doMock("workbox-strategies", () => ({
      CacheFirst: vi.fn(),
      NetworkFirst: vi.fn(),
    }));
    vi.doMock("workbox-expiration", () => ({ ExpirationPlugin: vi.fn() }));

    const localMockSync = vi.fn().mockResolvedValue(undefined);
    vi.doMock("../../services/syncHandler", () => ({
      processSyncQueue: localMockSync,
    }));

    const localMockGet = vi.fn().mockResolvedValue("test-jwt-token");
    vi.doMock("idb-keyval", () => ({
      get: localMockGet,
    }));

    mockClients.matchAll.mockResolvedValue([]);

    await import("../../sw-custom");

    const syncHandler = syncListeners.get("sync");
    expect(syncHandler).toBeDefined();

    let waitUntilPromise: Promise<void> | undefined;
    const event = {
      tag: "offline-sync",
      waitUntil: (p: Promise<void>) => {
        waitUntilPromise = p;
      },
    };

    syncHandler!(event);
    await waitUntilPromise;

    expect(localMockGet).toHaveBeenCalledWith("jwt_token_sw");
    expect(localMockSync).toHaveBeenCalledWith("test-jwt-token", expect.any(Function));
  });

  it("sync handler does nothing without token", async () => {
    vi.resetModules();
    syncListeners.clear();

    vi.doMock("workbox-core", () => ({ clientsClaim: vi.fn() }));
    vi.doMock("workbox-precaching", () => ({
      cleanupOutdatedCaches: vi.fn(),
      precacheAndRoute: vi.fn(),
    }));
    vi.doMock("workbox-routing", () => ({ registerRoute: vi.fn() }));
    vi.doMock("workbox-strategies", () => ({
      CacheFirst: vi.fn(),
      NetworkFirst: vi.fn(),
    }));
    vi.doMock("workbox-expiration", () => ({ ExpirationPlugin: vi.fn() }));

    const localMockSync = vi.fn();
    vi.doMock("../../services/syncHandler", () => ({
      processSyncQueue: localMockSync,
    }));

    const localMockGet = vi.fn().mockResolvedValue(null);
    vi.doMock("idb-keyval", () => ({
      get: localMockGet,
    }));

    mockClients.matchAll.mockResolvedValue([]);

    await import("../../sw-custom");

    const syncHandler = syncListeners.get("sync");

    let waitUntilPromise: Promise<void> | undefined;
    const event = {
      tag: "offline-sync",
      waitUntil: (p: Promise<void>) => {
        waitUntilPromise = p;
      },
    };

    syncHandler!(event);
    await waitUntilPromise;

    expect(localMockSync).not.toHaveBeenCalled();
  });

  it("sync handler ignores non-offline-sync tags", async () => {
    vi.resetModules();
    syncListeners.clear();

    vi.doMock("workbox-core", () => ({ clientsClaim: vi.fn() }));
    vi.doMock("workbox-precaching", () => ({
      cleanupOutdatedCaches: vi.fn(),
      precacheAndRoute: vi.fn(),
    }));
    vi.doMock("workbox-routing", () => ({ registerRoute: vi.fn() }));
    vi.doMock("workbox-strategies", () => ({
      CacheFirst: vi.fn(),
      NetworkFirst: vi.fn(),
    }));
    vi.doMock("workbox-expiration", () => ({ ExpirationPlugin: vi.fn() }));

    const localMockSync = vi.fn();
    vi.doMock("../../services/syncHandler", () => ({
      processSyncQueue: localMockSync,
    }));
    vi.doMock("idb-keyval", () => ({
      get: vi.fn().mockResolvedValue("token"),
    }));

    await import("../../sw-custom");

    const syncHandler = syncListeners.get("sync");
    const waitUntil = vi.fn();

    syncHandler!({ tag: "other-tag", waitUntil });

    // waitUntil should NOT be called for non-matching tags
    expect(waitUntil).not.toHaveBeenCalled();
  });
});
