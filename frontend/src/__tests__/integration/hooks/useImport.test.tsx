import { QueryClientProvider } from "@tanstack/react-query";
import { renderHook, waitFor } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import type { ReactNode } from "react";
import { describe, expect, it } from "vitest";
import { useImportBooks, useImportExcel } from "../../../hooks/useImport";
import { server } from "../../helpers/server";
import { createTestQueryClient } from "../../helpers/test-utils";

const API_BASE = "/api";

function createWrapper() {
  const queryClient = createTestQueryClient();
  return function Wrapper({ children }: { children: ReactNode }) {
    return (
      <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
    );
  };
}

describe("useImportExcel", () => {
  it("sends file and returns result", async () => {
    server.use(
      http.post(`${API_BASE}/tools/import/excel`, () =>
        HttpResponse.json({
          sheetDetails: { Mangas: { series: 2, tomes: 10 } },
          totalSeries: 2,
          totalTomes: 10,
        }),
      ),
    );

    const file = new File(["data"], "test.xlsx");
    const { result } = renderHook(() => useImportExcel(), {
      wrapper: createWrapper(),
    });

    result.current.mutate({ dryRun: true, file });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(result.current.data?.totalSeries).toBe(2);
    expect(result.current.data?.totalTomes).toBe(10);
  });
});

describe("useImportBooks", () => {
  it("sends file and returns result", async () => {
    server.use(
      http.post(`${API_BASE}/tools/import/books`, () =>
        HttpResponse.json({
          created: 5,
          enriched: 3,
          groupCount: 8,
        }),
      ),
    );

    const file = new File(["data"], "livres.xlsx");
    const { result } = renderHook(() => useImportBooks(), {
      wrapper: createWrapper(),
    });

    result.current.mutate({ dryRun: false, file });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(result.current.data?.created).toBe(5);
    expect(result.current.data?.enriched).toBe(3);
  });
});
