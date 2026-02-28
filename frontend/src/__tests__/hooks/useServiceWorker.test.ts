import { renderHook } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

// Mock api module
vi.mock("../../services/api", () => ({
  getToken: () => "test-jwt-token",
}));

// Mock virtual:pwa-register
vi.mock("virtual:pwa-register", () => ({
  registerSW: vi.fn(),
}));

describe("useServiceWorker", () => {
  let messageListeners: Array<(event: MessageEvent) => void> = [];

  beforeEach(() => {
    messageListeners = [];
    vi.stubGlobal("navigator", {
      ...navigator,
      serviceWorker: {
        addEventListener: vi.fn((_event: string, handler: (event: MessageEvent) => void) => {
          if (_event === "message") messageListeners.push(handler);
        }),
        removeEventListener: vi.fn(),
      },
    });
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it("responds to get-token message with JWT token via MessageChannel", async () => {
    const { useServiceWorker } = await import("../../hooks/useServiceWorker");

    renderHook(() => useServiceWorker());

    // Wait for async register() to complete
    await vi.waitFor(() => {
      expect(messageListeners.length).toBeGreaterThan(0);
    });

    // Simulate SW sending a get-token message via MessageChannel
    const channel = new MessageChannel();
    const postMessageSpy = vi.spyOn(channel.port1, "postMessage");

    const event = new MessageEvent("message", {
      data: { type: "get-token" },
      ports: [channel.port1],
    });

    messageListeners[0](event);

    expect(postMessageSpy).toHaveBeenCalledWith({ token: "test-jwt-token" });
  });

  it("ignores messages without get-token type", async () => {
    const { useServiceWorker } = await import("../../hooks/useServiceWorker");

    renderHook(() => useServiceWorker());

    await vi.waitFor(() => {
      expect(messageListeners.length).toBeGreaterThan(0);
    });

    const channel = new MessageChannel();
    const postMessageSpy = vi.spyOn(channel.port1, "postMessage");

    const event = new MessageEvent("message", {
      data: { type: "other-message" },
      ports: [channel.port1],
    });

    messageListeners[0](event);

    expect(postMessageSpy).not.toHaveBeenCalled();
  });

  it("ignores get-token messages without ports", async () => {
    const { useServiceWorker } = await import("../../hooks/useServiceWorker");

    renderHook(() => useServiceWorker());

    await vi.waitFor(() => {
      expect(messageListeners.length).toBeGreaterThan(0);
    });

    // No ports — should not crash
    const event = new MessageEvent("message", {
      data: { type: "get-token" },
    });

    expect(() => messageListeners[0](event)).not.toThrow();
  });
});
