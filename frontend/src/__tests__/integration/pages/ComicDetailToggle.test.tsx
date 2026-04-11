import { screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { Route, Routes } from "react-router-dom";
import { toast } from "sonner";
import ComicDetail from "../../../pages/ComicDetail";
import { createMockComicSeries, createMockTome } from "../../helpers/factories";
import { server } from "../../helpers/server";
import { renderWithProviders } from "../../helpers/test-utils";

vi.mock("sonner", async () => {
  const actual = await vi.importActual("sonner");
  return {
    ...actual,
    toast: Object.assign(vi.fn(), {
      error: vi.fn(),
      success: vi.fn(),
    }),
  };
});

function renderComicDetail(id: number = 1) {
  return renderWithProviders(
    <Routes>
      <Route element={<ComicDetail />} path="/comic/:id" />
    </Routes>,
    { initialEntries: [`/comic/${id}`] },
  );
}

describe("ComicDetail — inline tome toggle", () => {
  beforeEach(() => {
    localStorage.clear();
    localStorage.setItem("jwt_token", "fake-jwt-token");
    localStorage.setItem("tome-view-mode", "table");
    Object.defineProperty(navigator, "onLine", {
      configurable: true,
      value: true,
      writable: true,
    });
  });

  it("renders checkboxes for tome boolean fields", async () => {
    const tomes = [
      createMockTome({
        bought: true,
        id: 10,
        number: 1,
        onNas: false,
        read: false,
      }),
    ];

    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({
            id: 1,
            isOneShot: false,
            title: "Test",
            tomes,
          }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Tomes (1)")).toBeInTheDocument();
    });

    const row = screen.getByText("1").closest("tr")!;
    const checkboxes = within(row).getAllByRole("checkbox");
    expect(checkboxes).toHaveLength(3); // bought, read, onNas

    // bought is checked, others are not
    expect(checkboxes[0]).toBeChecked(); // bought
    expect(checkboxes[1]).not.toBeChecked(); // read
    expect(checkboxes[2]).not.toBeChecked(); // onNas
  });

  it("sends PATCH request when toggling a tome checkbox", async () => {
    const user = userEvent.setup();
    let putBody: Record<string, unknown> | null = null;

    const tomes = [createMockTome({ bought: false, id: 10, number: 1 })];

    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({
            id: 1,
            isOneShot: false,
            title: "Test",
            tomes,
          }),
        ),
      ),
      http.patch("/api/tomes/10", async ({ request }) => {
        putBody = (await request.json()) as Record<string, unknown>;
        return HttpResponse.json(
          createMockTome({ bought: true, id: 10, number: 1 }),
        );
      }),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Tomes (1)")).toBeInTheDocument();
    });

    const row = screen.getByText("1").closest("tr")!;
    const checkboxes = within(row).getAllByRole("checkbox");

    // Click "bought" checkbox
    await user.click(checkboxes[0]);

    await waitFor(() => {
      expect(putBody).toEqual(expect.objectContaining({ bought: true }));
    });
  });

  it("optimistically updates checkbox state before API response", async () => {
    const user = userEvent.setup();

    const tomes = [createMockTome({ id: 10, number: 1, read: false })];

    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({
            id: 1,
            isOneShot: false,
            title: "Test",
            tomes,
          }),
        ),
      ),
      http.patch("/api/tomes/10", async () => {
        // Delay the response to verify optimistic update
        await new Promise((resolve) => setTimeout(resolve, 200));
        return HttpResponse.json(
          createMockTome({ id: 10, number: 1, read: true }),
        );
      }),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Tomes (1)")).toBeInTheDocument();
    });

    const row = screen.getByText("1").closest("tr")!;
    const readCheckbox = within(row).getAllByRole("checkbox")[1]; // read is 2nd checkbox

    expect(readCheckbox).not.toBeChecked();

    // Click — should optimistically update immediately
    await user.click(readCheckbox);

    // Checkbox should be checked immediately (optimistic)
    expect(readCheckbox).toBeChecked();
  });

  it("reverts optimistic update and shows error toast on API failure", async () => {
    const user = userEvent.setup();

    const tomes = [createMockTome({ bought: false, id: 10, number: 1 })];

    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({
            id: 1,
            isOneShot: false,
            title: "Test",
            tomes,
          }),
        ),
      ),
      http.patch("/api/tomes/10", () =>
        HttpResponse.json({ detail: "Server Error" }, { status: 500 }),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Tomes (1)")).toBeInTheDocument();
    });

    const row = screen.getByText("1").closest("tr")!;
    const boughtCheckbox = within(row).getAllByRole("checkbox")[0];

    expect(boughtCheckbox).not.toBeChecked();

    await user.click(boughtCheckbox);

    // After error, should revert to unchecked
    await waitFor(() => {
      expect(boughtCheckbox).not.toBeChecked();
    });

    expect(toast.error).toHaveBeenCalled();
  });

  it("shows grouped success toast after toggling a tome (debounced)", async () => {
    const user = userEvent.setup();

    const tomes = [createMockTome({ id: 10, number: 3, read: false })];

    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({
            id: 1,
            isOneShot: false,
            title: "Test",
            tomes,
          }),
        ),
      ),
      http.patch("/api/tomes/10", () =>
        HttpResponse.json(createMockTome({ id: 10, number: 3, read: true })),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Tomes (1)")).toBeInTheDocument();
    });

    const row = screen.getByText("3").closest("tr")!;
    const readCheckbox = within(row).getAllByRole("checkbox")[1];

    await user.click(readCheckbox);

    // Wait for debounced toast (1s after last toggle)
    await waitFor(
      () => {
        expect(toast.success).toHaveBeenCalledWith(
          "1 tome mis à jour",
          expect.objectContaining({ duration: 1500 }),
        );
      },
      { timeout: 2000 },
    );
  });

  it("groups multiple rapid toggle toasts into one", async () => {
    const user = userEvent.setup();

    const tomes = [
      createMockTome({ bought: false, id: 10, number: 1, read: false }),
      createMockTome({ bought: false, id: 11, number: 2, read: false }),
      createMockTome({ bought: false, id: 12, number: 3, read: false }),
    ];

    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({
            id: 1,
            isOneShot: false,
            title: "Test",
            tomes,
          }),
        ),
      ),
      http.patch("/api/tomes/:id", ({ params }) =>
        HttpResponse.json(
          createMockTome({ id: Number(params.id), read: true }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Tomes (3)")).toBeInTheDocument();
    });

    // Toggle 3 tomes rapidly
    const rows = screen.getAllByRole("row").slice(1);
    for (const row of rows) {
      const readCheckbox = within(row).getAllByRole("checkbox")[1];
      await user.click(readCheckbox);
    }

    // Wait for the debounced grouped toast (1s after last toggle)
    await waitFor(
      () => {
        expect(toast.success).toHaveBeenCalledWith(
          "3 tomes mis à jour",
          expect.objectContaining({ duration: 1500 }),
        );
      },
      { timeout: 3000 },
    );
  });

  it("renders header checkboxes for bulk toggle", async () => {
    const tomes = [
      createMockTome({
        bought: true,
        id: 10,
        number: 1,
        onNas: false,
        read: false,
      }),
      createMockTome({
        bought: true,
        id: 11,
        number: 2,
        onNas: false,
        read: false,
      }),
    ];

    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({
            id: 1,
            isOneShot: false,
            title: "Test",
            tomes,
          }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Tomes (2)")).toBeInTheDocument();
    });

    expect(
      screen.getByRole("checkbox", { name: /tout cocher acheté/i }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("checkbox", { name: /tout cocher lu/i }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("checkbox", { name: /tout cocher nas/i }),
    ).toBeInTheDocument();
  });

  it("select all: when none checked, click checks all and sends PATCH for each", async () => {
    const user = userEvent.setup();
    const patchedIds: number[] = [];

    const tomes = [
      createMockTome({ bought: false, id: 10, number: 1 }),
      createMockTome({ bought: false, id: 11, number: 2 }),
      createMockTome({ bought: false, id: 12, number: 3 }),
    ];

    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({
            id: 1,
            isOneShot: false,
            title: "Test",
            tomes,
          }),
        ),
      ),
      http.patch("/api/tomes/:id", async ({ params }) => {
        patchedIds.push(Number(params.id));
        return HttpResponse.json(
          createMockTome({ bought: true, id: Number(params.id) }),
        );
      }),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Tomes (3)")).toBeInTheDocument();
    });

    const headerCheckbox = screen.getByRole("checkbox", {
      name: /tout cocher acheté/i,
    });
    await user.click(headerCheckbox);

    // All row checkboxes should be checked optimistically
    const rows = screen.getAllByRole("row").slice(1); // skip header
    for (const row of rows) {
      const checkboxes = within(row).getAllByRole("checkbox");
      expect(checkboxes[0]).toBeChecked(); // bought column
    }

    // PATCH calls for all 3 tomes
    await waitFor(() => {
      expect(patchedIds).toHaveLength(3);
    });
    expect(patchedIds.sort()).toEqual([10, 11, 12]);
  });

  it("deselect all: when all checked, click unchecks all", async () => {
    const user = userEvent.setup();
    const patchBodies: Array<Record<string, unknown>> = [];

    const tomes = [
      createMockTome({ bought: true, id: 10, number: 1 }),
      createMockTome({ bought: true, id: 11, number: 2 }),
    ];

    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({
            id: 1,
            isOneShot: false,
            title: "Test",
            tomes,
          }),
        ),
      ),
      http.patch("/api/tomes/:id", async ({ request }) => {
        patchBodies.push((await request.json()) as Record<string, unknown>);
        return HttpResponse.json(createMockTome({ bought: false, id: 10 }));
      }),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Tomes (2)")).toBeInTheDocument();
    });

    const headerCheckbox = screen.getByRole("checkbox", {
      name: /tout cocher acheté/i,
    });
    expect(headerCheckbox).toBeChecked();

    await user.click(headerCheckbox);

    // All row checkboxes should be unchecked
    const rows = screen.getAllByRole("row").slice(1);
    for (const row of rows) {
      const checkboxes = within(row).getAllByRole("checkbox");
      expect(checkboxes[0]).not.toBeChecked();
    }

    await waitFor(() => {
      expect(patchBodies).toHaveLength(2);
    });
    expect(patchBodies[0]).toEqual(expect.objectContaining({ bought: false }));
  });

  it("header checkbox is indeterminate when some tomes are checked", async () => {
    const tomes = [
      createMockTome({ bought: true, id: 10, number: 1 }),
      createMockTome({ bought: false, id: 11, number: 2 }),
    ];

    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({
            id: 1,
            isOneShot: false,
            title: "Test",
            tomes,
          }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Tomes (2)")).toBeInTheDocument();
    });

    const headerCheckbox = screen.getByRole("checkbox", {
      name: /tout cocher acheté/i,
    }) as HTMLInputElement;
    expect(headerCheckbox.indeterminate).toBe(true);
    expect(headerCheckbox.checked).toBe(false);
  });

  it("only sends PATCH for tomes that actually changed", async () => {
    const user = userEvent.setup();
    const patchedIds: number[] = [];

    const tomes = [
      createMockTome({ onNas: true, id: 10, number: 1 }),
      createMockTome({ onNas: false, id: 11, number: 2 }),
      createMockTome({ onNas: true, id: 12, number: 3 }),
    ];

    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({
            id: 1,
            isOneShot: false,
            title: "Test",
            tomes,
          }),
        ),
      ),
      http.patch("/api/tomes/:id", async ({ params }) => {
        patchedIds.push(Number(params.id));
        return HttpResponse.json(
          createMockTome({ onNas: true, id: Number(params.id) }),
        );
      }),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Tomes (3)")).toBeInTheDocument();
    });

    // Click "select all NAS" — only tome 11 needs changing
    const headerCheckbox = screen.getByRole("checkbox", {
      name: /tout cocher nas/i,
    });
    await user.click(headerCheckbox);

    await waitFor(() => {
      expect(patchedIds).toHaveLength(1);
    });
    expect(patchedIds[0]).toBe(11);
  });

  it("checkboxes have accessible labels", async () => {
    const tomes = [createMockTome({ id: 10, number: 3 })];

    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({
            id: 1,
            isOneShot: false,
            title: "Test",
            tomes,
          }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Tomes (1)")).toBeInTheDocument();
    });

    // Each checkbox should have an accessible label
    expect(
      screen.getByRole("checkbox", { name: /tome 3.*acheté/i }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("checkbox", { name: /tome 3.*lu/i }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("checkbox", { name: /tome 3.*nas/i }),
    ).toBeInTheDocument();
  });
});
