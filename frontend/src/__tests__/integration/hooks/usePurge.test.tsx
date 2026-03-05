import { renderHook, waitFor } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import { describe, expect, it } from "vitest";
import { usePurgePreview, useExecutePurge } from "../../../hooks/usePurge";
import { server } from "../../helpers/server";
import { createTestQueryClient } from "../../helpers/test-utils";
import { QueryClientProvider } from "@tanstack/react-query";
import type { ReactNode } from "react";

const API_BASE = "/api";

function createWrapper() {
  const queryClient = createTestQueryClient();
  return function Wrapper({ children }: { children: ReactNode }) {
    return (
      <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
    );
  };
}

describe("usePurgePreview", () => {
  it("fetches purgeable series for given days", async () => {
    server.use(
      http.get(`${API_BASE}/tools/purge/preview`, ({ request }) => {
        const url = new URL(request.url);
        expect(url.searchParams.get("days")).toBe("30");
        return HttpResponse.json([
          { deletedAt: "2025-01-01T00:00:00+00:00", id: 1, title: "Naruto" },
        ]);
      }),
    );

    const { result } = renderHook(() => usePurgePreview(30), {
      wrapper: createWrapper(),
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(result.current.data).toHaveLength(1);
    expect(result.current.data![0].title).toBe("Naruto");
  });

  it("does not fetch when days is 0", () => {
    const { result } = renderHook(() => usePurgePreview(0), {
      wrapper: createWrapper(),
    });

    expect(result.current.isFetching).toBe(false);
  });
});

describe("useExecutePurge", () => {
  it("sends seriesIds and returns purge count", async () => {
    server.use(
      http.post(`${API_BASE}/tools/purge/execute`, async ({ request }) => {
        const body = (await request.json()) as { seriesIds: number[] };
        expect(body.seriesIds).toEqual([1, 2]);
        return HttpResponse.json({ purged: 2 });
      }),
    );

    const { result } = renderHook(() => useExecutePurge(), {
      wrapper: createWrapper(),
    });

    result.current.mutate([1, 2]);

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(result.current.data).toEqual({ purged: 2 });
  });
});
