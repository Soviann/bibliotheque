import { QueryClientProvider } from "@tanstack/react-query";
import { renderHook, waitFor } from "@testing-library/react";
import type { ReactNode } from "react";
import { MemoryRouter } from "react-router-dom";
import { toast } from "sonner";
import { useServiceWorker } from "../../../hooks/useServiceWorker";
import { createTestQueryClient } from "../../helpers/test-utils";

vi.mock("sonner", async () => {
  const actual = await vi.importActual("sonner");
  return {
    ...actual,
    toast: Object.assign(vi.fn(), {
      error: vi.fn(),
      info: vi.fn(),
      success: vi.fn(),
    }),
  };
});

// Mock virtual:pwa-register with a vi.fn so we can override per-test
const mockRegisterSW = vi.fn(() => () => {});
vi.mock("virtual:pwa-register", () => ({
  registerSW: mockRegisterSW,
}));

function createWrapper() {
  const queryClient = createTestQueryClient();
  return function Wrapper({ children }: { children: ReactNode }) {
    return (
      <QueryClientProvider client={queryClient}>
        <MemoryRouter>{children}</MemoryRouter>
      </QueryClientProvider>
    );
  };
}

describe("useServiceWorker", () => {
  let addEventListenerSpy: ReturnType<typeof vi.fn>;
  let removeEventListenerSpy: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    localStorage.clear();

    addEventListenerSpy = vi.fn();
    removeEventListenerSpy = vi.fn();

    Object.defineProperty(navigator, "serviceWorker", {
      configurable: true,
      value: {
        addEventListener: addEventListenerSpy,
        removeEventListener: removeEventListenerSpy,
      },
      writable: true,
    });
  });

  it("registers service worker and sets up message listener", async () => {
    renderHook(() => useServiceWorker(), {
      wrapper: createWrapper(),
    });

    await waitFor(() => {
      expect(addEventListenerSpy).toHaveBeenCalledWith(
        "message",
        expect.any(Function),
      );
    });
  });

  it("removes message listener on unmount", async () => {
    const { unmount } = renderHook(() => useServiceWorker(), {
      wrapper: createWrapper(),
    });

    await waitFor(() => {
      expect(addEventListenerSpy).toHaveBeenCalled();
    });

    unmount();

    expect(removeEventListenerSpy).toHaveBeenCalledWith(
      "message",
      expect.any(Function),
    );
  });

  it("does not register listener when serviceWorker is absent", async () => {
    Object.defineProperty(navigator, "serviceWorker", {
      configurable: true,
      value: undefined,
      writable: true,
    });

    renderHook(() => useServiceWorker(), {
      wrapper: createWrapper(),
    });

    // Wait a tick for async operations
    await new Promise((r) => setTimeout(r, 50));

    // addEventListenerSpy should not be called since navigator.serviceWorker is undefined
    expect(addEventListenerSpy).not.toHaveBeenCalled();
  });

  it("responds to get-token messages from service worker", async () => {
    localStorage.setItem("jwt_token", "my-token");

    renderHook(() => useServiceWorker(), {
      wrapper: createWrapper(),
    });

    await waitFor(() => {
      expect(addEventListenerSpy).toHaveBeenCalled();
    });

    // Get the message handler
    const messageHandler = addEventListenerSpy.mock.calls.find(
      (call: [string, unknown]) => call[0] === "message",
    )?.[1] as (event: MessageEvent) => void;

    const mockPort = { postMessage: vi.fn() };
    messageHandler({
      data: { type: "get-token" },
      ports: [mockPort],
    } as unknown as MessageEvent);

    expect(mockPort.postMessage).toHaveBeenCalledWith({ token: "my-token" });
  });

  it("silently skips get-token when event.ports[0] is undefined", async () => {
    renderHook(() => useServiceWorker(), {
      wrapper: createWrapper(),
    });

    await waitFor(() => {
      expect(addEventListenerSpy).toHaveBeenCalled();
    });

    const messageHandler = addEventListenerSpy.mock.calls.find(
      (call: [string, unknown]) => call[0] === "message",
    )?.[1] as (event: MessageEvent) => void;

    // Should not throw when ports is empty
    expect(() => {
      messageHandler({
        data: { type: "get-token" },
        ports: [],
      } as unknown as MessageEvent);
    }).not.toThrow();
  });

  it("calls toast when onNeedRefresh is triggered", async () => {
    mockRegisterSW.mockImplementationOnce((options?: { onNeedRefresh?: () => void }) => {
      options?.onNeedRefresh?.();
      return () => {};
    });

    renderHook(() => useServiceWorker(), {
      wrapper: createWrapper(),
    });

    await waitFor(() => {
      expect(toast).toHaveBeenCalledWith(
        "Nouvelle version disponible",
        expect.objectContaining({
          action: expect.objectContaining({ label: "Recharger" }),
          duration: Infinity,
        }),
      );
    });
  });

  it("does not post message for non-matching message type", async () => {
    renderHook(() => useServiceWorker(), {
      wrapper: createWrapper(),
    });

    await waitFor(() => {
      expect(addEventListenerSpy).toHaveBeenCalled();
    });

    const messageHandler = addEventListenerSpy.mock.calls.find(
      (call: [string, unknown]) => call[0] === "message",
    )?.[1] as (event: MessageEvent) => void;

    const mockPort = { postMessage: vi.fn() };
    messageHandler({
      data: { type: "other" },
      ports: [mockPort],
    } as unknown as MessageEvent);

    expect(mockPort.postMessage).not.toHaveBeenCalled();
  });

  it("responds with null token when no token is stored", async () => {
    // localStorage is cleared in beforeEach, so no token exists

    renderHook(() => useServiceWorker(), {
      wrapper: createWrapper(),
    });

    await waitFor(() => {
      expect(addEventListenerSpy).toHaveBeenCalled();
    });

    const messageHandler = addEventListenerSpy.mock.calls.find(
      (call: [string, unknown]) => call[0] === "message",
    )?.[1] as (event: MessageEvent) => void;

    const mockPort = { postMessage: vi.fn() };
    messageHandler({
      data: { type: "get-token" },
      ports: [mockPort],
    } as unknown as MessageEvent);

    expect(mockPort.postMessage).toHaveBeenCalledWith({ token: null });
  });
});
