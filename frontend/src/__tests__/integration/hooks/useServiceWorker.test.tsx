import { QueryClientProvider } from "@tanstack/react-query";
import { renderHook, waitFor } from "@testing-library/react";
import type { ReactNode } from "react";
import { MemoryRouter } from "react-router-dom";
import { useServiceWorker } from "../../../hooks/useServiceWorker";
import { createTestQueryClient } from "../../helpers/test-utils";

// The hook imports from "virtual:pwa-register" which is mocked in vitest config
// via src/__mocks__/virtual-pwa-register.ts

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
});
