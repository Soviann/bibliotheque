import { QueryClientProvider } from "@tanstack/react-query";
import { act, renderHook, waitFor } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import type { ReactNode } from "react";
import { MemoryRouter } from "react-router-dom";
import {
  useDetectMergeGroups,
  useExecuteMerge,
  useMergePreview,
  useMergeSuggest,
} from "../../../hooks/useMergeSeries";
import type { MergePreview } from "../../../types/api";
import { createMockHydraCollection } from "../../helpers/factories";
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

describe("useMergeSeries", () => {
  beforeEach(() => {
    localStorage.clear();
  });

  describe("useDetectMergeGroups", () => {
    it("returns detected merge groups", async () => {
      server.use(
        http.post("/api/merge-series/detect", () => {
          return HttpResponse.json([
            {
              entries: [
                {
                  originalTitle: "Astérix - T1",
                  seriesId: 1,
                  suggestedTomeNumber: 1,
                },
                {
                  originalTitle: "Astérix - T2",
                  seriesId: 2,
                  suggestedTomeNumber: 2,
                },
              ],
              suggestedTitle: "Astérix",
            },
          ]);
        }),
      );

      const { result } = renderHook(() => useDetectMergeGroups(), {
        wrapper: createWrapper(),
      });

      await act(async () => {
        result.current.mutate({});
      });

      await waitFor(() => expect(result.current.isSuccess).toBe(true));

      expect(result.current.data).toHaveLength(1);
      expect(result.current.data![0].suggestedTitle).toBe("Astérix");
      expect(result.current.data![0].entries).toHaveLength(2);
      expect(result.current.data![0].entries[0].seriesId).toBe(1);
      expect(result.current.data![0].entries[1].suggestedTomeNumber).toBe(2);
    });
  });

  describe("useMergePreview", () => {
    it("returns merge preview for given series IDs", async () => {
      server.use(
        http.post("/api/merge-series/preview", () => {
          return HttpResponse.json({
            authors: ["Goscinny", "Uderzo"],
            coverUrl: "https://example.com/cover.jpg",
            description: "Les aventures d'Astérix",
            isOneShot: false,
            latestPublishedIssue: 40,
            latestPublishedIssueComplete: true,
            publisher: "Hachette",
            sourceSeriesIds: [1, 2],
            title: "Astérix",
            tomes: [
              {
                bought: true,
                downloaded: false,
                isbn: "978-2-01-210-1",
                number: 1,
                onNas: false,
                read: true,
                title: "Astérix le Gaulois",
                tomeEnd: null,
              },
              {
                bought: true,
                downloaded: false,
                isbn: null,
                number: 2,
                onNas: false,
                read: false,
                title: "La Serpe d'or",
                tomeEnd: null,
              },
            ],
            type: "BD",
          });
        }),
      );

      const { result } = renderHook(() => useMergePreview(), {
        wrapper: createWrapper(),
      });

      await act(async () => {
        result.current.mutate([1, 2]);
      });

      await waitFor(() => expect(result.current.isSuccess).toBe(true));

      expect(result.current.data!.title).toBe("Astérix");
      expect(result.current.data!.authors).toEqual(["Goscinny", "Uderzo"]);
      expect(result.current.data!.tomes).toHaveLength(2);
      expect(result.current.data!.sourceSeriesIds).toEqual([1, 2]);
    });
  });

  describe("useMergeSuggest", () => {
    it("returns AI suggestions for series IDs", async () => {
      server.use(
        http.post("/api/merge-series/suggest", () => {
          return HttpResponse.json({
            entries: [
              { id: 1, tomeNumber: 8 },
              { id: 2, tomeNumber: 1 },
            ],
            title: "Astérix",
          });
        }),
      );

      const { result } = renderHook(() => useMergeSuggest(), {
        wrapper: createWrapper(),
      });

      await act(async () => {
        result.current.mutate([1, 2]);
      });

      await waitFor(() => expect(result.current.isSuccess).toBe(true));

      expect(result.current.data!.title).toBe("Astérix");
      expect(result.current.data!.entries).toHaveLength(2);
      expect(result.current.data!.entries[0].tomeNumber).toBe(8);
    });
  });

  describe("useExecuteMerge", () => {
    it("executes merge and invalidates comics query", async () => {
      server.use(
        http.post("/api/merge-series/execute", () => {
          return HttpResponse.json({ id: 42, title: "Astérix", type: "BD" });
        }),
      );

      const queryClient = createTestQueryClient();
      queryClient.setQueryData(["comics"], createMockHydraCollection([]));

      const preview: MergePreview = {
        amazonUrl: null,
        authors: ["Goscinny"],
        coverUrl: null,
        defaultTomeBought: false,
        defaultTomeDownloaded: false,
        defaultTomeRead: false,
        description: null,
        isOneShot: false,
        latestPublishedIssue: null,
        latestPublishedIssueComplete: false,
        notInterestedBuy: false,
        notInterestedNas: false,
        publishedDate: null,
        publisher: null,
        sourceSeriesIds: [1, 2],
        status: "buying",
        title: "Astérix",
        tomes: [],
        type: "BD",
      };

      const { result } = renderHook(() => useExecuteMerge(), {
        wrapper: createWrapper(queryClient),
      });

      await act(async () => {
        result.current.mutate(preview);
      });

      await waitFor(() => expect(result.current.isSuccess).toBe(true));

      expect(result.current.data).toEqual({
        id: 42,
        title: "Astérix",
        type: "BD",
      });
      expect(queryClient.getQueryState(["comics"])?.isInvalidated).toBe(true);
    });
  });
});
