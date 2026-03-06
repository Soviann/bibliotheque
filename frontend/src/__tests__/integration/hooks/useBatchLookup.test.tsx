import { renderHook, waitFor } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import { describe, expect, it } from "vitest";
import { useBatchLookupPreview } from "../../../hooks/useBatchLookup";
import { server } from "../../helpers/server";
import { createTestQueryClient, renderWithProviders } from "../../helpers/test-utils";
import { QueryClientProvider } from "@tanstack/react-query";
import type { ReactNode } from "react";

function createWrapper() {
  const queryClient = createTestQueryClient();
  return {
    queryClient,
    wrapper: ({ children }: { children: ReactNode }) => (
      <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
    ),
  };
}

describe("useBatchLookupPreview", () => {
  it("fetches preview count", async () => {
    server.use(
      http.get("/api/tools/batch-lookup/preview", () =>
        HttpResponse.json({ count: 42 }),
      ),
    );

    const { wrapper } = createWrapper();
    const { result } = renderHook(() => useBatchLookupPreview(), { wrapper });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data?.count).toBe(42);
  });

  it("passes type and force params", async () => {
    let capturedUrl = "";

    server.use(
      http.get("/api/tools/batch-lookup/preview", ({ request }) => {
        capturedUrl = request.url;
        return HttpResponse.json({ count: 5 });
      }),
    );

    const { wrapper } = createWrapper();
    const { result } = renderHook(
      () => useBatchLookupPreview("manga", true),
      { wrapper },
    );

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(capturedUrl).toContain("type=manga");
    expect(capturedUrl).toContain("force=true");
  });
});
