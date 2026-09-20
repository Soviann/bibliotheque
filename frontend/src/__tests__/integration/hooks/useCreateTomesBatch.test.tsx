import { QueryClientProvider } from "@tanstack/react-query";
import { act, renderHook, waitFor } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import type { ReactNode } from "react";
import { MemoryRouter } from "react-router-dom";
import { useCreateTomesBatch } from "../../../hooks/useCreateTomesBatch";
import { queryKeys } from "../../../queryKeys";
import { createMockComicSeries, createMockTome } from "../../helpers/factories";
import { server } from "../../helpers/server";
import { createTestQueryClient } from "../../helpers/test-utils";

function createWrapper(queryClient = createTestQueryClient()) {
  return function Wrapper({ children }: { children: ReactNode }) {
    return (
      <QueryClientProvider client={queryClient}>
        <MemoryRouter>{children}</MemoryRouter>
      </QueryClientProvider>
    );
  };
}

describe("useCreateTomesBatch", () => {
  it("creates multiple tomes via batch API", async () => {
    const createdTomes = [
      createMockTome({ id: 10, number: 1 }),
      createMockTome({ id: 11, number: 2 }),
    ];

    let capturedPayload: unknown = null;

    server.use(
      http.post("/api/comic_series/5/tomes/batch", async ({ request }) => {
        capturedPayload = await request.json();
        return HttpResponse.json(createdTomes, { status: 201 });
      }),
    );

    const { result } = renderHook(() => useCreateTomesBatch(5), {
      wrapper: createWrapper(),
    });

    act(() => {
      result.current.mutate({
        tomes: [
          {
            bought: true,
            isHorsSerie: false,
            isbn: null,
            number: 1,
            onNas: false,
            read: false,
            title: null,
            tomeEnd: null,
          },
          {
            bought: false,
            isHorsSerie: false,
            isbn: null,
            number: 2,
            onNas: false,
            read: false,
            title: null,
            tomeEnd: null,
          },
        ],
      });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data).toEqual(createdTomes);
    expect(capturedPayload).toEqual({
      tomes: [
        {
          bought: true,
          isHorsSerie: false,
          isbn: null,
          number: 1,
          onNas: false,
          read: false,
          title: null,
          tomeEnd: null,
        },
        {
          bought: false,
          isHorsSerie: false,
          isbn: null,
          number: 2,
          onNas: false,
          read: false,
          title: null,
          tomeEnd: null,
        },
      ],
    });
  });

  it("performs optimistic update on series detail query cache", async () => {
    const qc = createTestQueryClient();
    const existingSeries = createMockComicSeries({
      id: 5,
      tomes: [createMockTome({ id: 1, number: 1 })],
    });
    qc.setQueryData(queryKeys.comics.detail(5), existingSeries);

    server.use(
      http.post("/api/comic_series/5/tomes/batch", async () => {
        // Delay response to check optimistic cache
        await new Promise((resolve) => setTimeout(resolve, 50));
        return HttpResponse.json([
          createMockTome({ id: 2, number: 2 }),
        ], { status: 201 });
      }),
    );

    const { result } = renderHook(() => useCreateTomesBatch(5), {
      wrapper: createWrapper(qc),
    });

    act(() => {
      result.current.mutate({
        tomes: [
          {
            bought: true,
            isHorsSerie: false,
            isbn: null,
            number: 2,
            onNas: false,
            read: false,
            title: null,
            tomeEnd: null,
          },
        ],
      });
    });

    // Optimistic cache check
    await waitFor(() => {
      const cached = qc.getQueryData<typeof existingSeries>(
        queryKeys.comics.detail(5),
      );
      expect(cached?.tomes).toHaveLength(2);
    });

    const cached = qc.getQueryData<typeof existingSeries>(
      queryKeys.comics.detail(5),
    );
    expect(cached?.tomes?.[1]?.number).toBe(2);
    expect(cached?.tomes?.[1]?._syncPending).toBe(true);

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
  });

  it("rolls back optimistic update on API error", async () => {
    const qc = createTestQueryClient();
    const existingSeries = createMockComicSeries({
      id: 5,
      tomes: [createMockTome({ id: 1, number: 1 })],
    });
    qc.setQueryData(queryKeys.comics.detail(5), existingSeries);

    server.use(
      http.post("/api/comic_series/5/tomes/batch", () =>
        HttpResponse.json({ error: "Server error" }, { status: 500 }),
      ),
    );

    const { result } = renderHook(() => useCreateTomesBatch(5), {
      wrapper: createWrapper(qc),
    });

    act(() => {
      result.current.mutate({
        tomes: [
          {
            bought: false,
            isHorsSerie: false,
            isbn: null,
            number: 2,
            onNas: false,
            read: false,
            title: null,
            tomeEnd: null,
          },
        ],
      });
    });

    await waitFor(() => expect(result.current.isError).toBe(true));

    const rolledBack = qc.getQueryData<typeof existingSeries>(
      queryKeys.comics.detail(5),
    );
    expect(rolledBack?.tomes).toHaveLength(1);
    expect(rolledBack?.tomes?.[0]?.id).toBe(1);
  });
});
