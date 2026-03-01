import { screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { Route, Routes } from "react-router-dom";
import { toast } from "sonner";
import ComicDetail from "../../../pages/ComicDetail";
import {
  createMockComicSeries,
  createMockTome,
} from "../../helpers/factories";
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
    Object.defineProperty(navigator, "onLine", {
      configurable: true,
      value: true,
      writable: true,
    });
  });

  it("renders checkboxes for tome boolean fields", async () => {
    const tomes = [
      createMockTome({ bought: true, downloaded: false, id: 10, number: 1, onNas: false, read: false }),
    ];

    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({ id: 1, isOneShot: false, title: "Test", tomes }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Tomes (1)")).toBeInTheDocument();
    });

    const row = screen.getByText("1").closest("tr")!;
    const checkboxes = within(row).getAllByRole("checkbox");
    expect(checkboxes).toHaveLength(4); // bought, downloaded, read, onNas

    // bought is checked, others are not
    expect(checkboxes[0]).toBeChecked();      // bought
    expect(checkboxes[1]).not.toBeChecked();   // downloaded
    expect(checkboxes[2]).not.toBeChecked();   // read
    expect(checkboxes[3]).not.toBeChecked();   // onNas
  });

  it("sends PATCH request when toggling a tome checkbox", async () => {
    const user = userEvent.setup();
    let putBody: Record<string, unknown> | null = null;

    const tomes = [
      createMockTome({ bought: false, id: 10, number: 1 }),
    ];

    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({ id: 1, isOneShot: false, title: "Test", tomes }),
        ),
      ),
      http.patch("/api/tomes/10", async ({ request }) => {
        putBody = (await request.json()) as Record<string, unknown>;
        return HttpResponse.json(createMockTome({ bought: true, id: 10, number: 1 }));
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

    const tomes = [
      createMockTome({ id: 10, number: 1, read: false }),
    ];

    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({ id: 1, isOneShot: false, title: "Test", tomes }),
        ),
      ),
      http.patch("/api/tomes/10", async () => {
        // Delay the response to verify optimistic update
        await new Promise((resolve) => setTimeout(resolve, 200));
        return HttpResponse.json(createMockTome({ id: 10, number: 1, read: true }));
      }),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Tomes (1)")).toBeInTheDocument();
    });

    const row = screen.getByText("1").closest("tr")!;
    const readCheckbox = within(row).getAllByRole("checkbox")[2]; // read is 3rd checkbox

    expect(readCheckbox).not.toBeChecked();

    // Click — should optimistically update immediately
    await user.click(readCheckbox);

    // Checkbox should be checked immediately (optimistic)
    expect(readCheckbox).toBeChecked();
  });

  it("reverts optimistic update and shows error toast on API failure", async () => {
    const user = userEvent.setup();

    const tomes = [
      createMockTome({ bought: false, id: 10, number: 1 }),
    ];

    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({ id: 1, isOneShot: false, title: "Test", tomes }),
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

  it("shows success toast after toggling a tome", async () => {
    const user = userEvent.setup();

    const tomes = [
      createMockTome({ id: 10, number: 3, read: false }),
    ];

    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({ id: 1, isOneShot: false, title: "Test", tomes }),
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
    const readCheckbox = within(row).getAllByRole("checkbox")[2];

    await user.click(readCheckbox);

    await waitFor(() => {
      expect(toast.success).toHaveBeenCalledWith(
        expect.stringContaining("Tome 3"),
        expect.objectContaining({ duration: 1500 }),
      );
    });
  });

  it("checkboxes have accessible labels", async () => {
    const tomes = [
      createMockTome({ id: 10, number: 3 }),
    ];

    server.use(
      http.get("/api/comic_series/1", () =>
        HttpResponse.json(
          createMockComicSeries({ id: 1, isOneShot: false, title: "Test", tomes }),
        ),
      ),
    );

    renderComicDetail();

    await waitFor(() => {
      expect(screen.getByText("Tomes (1)")).toBeInTheDocument();
    });

    // Each checkbox should have an accessible label
    expect(screen.getByRole("checkbox", { name: /tome 3.*acheté/i })).toBeInTheDocument();
    expect(screen.getByRole("checkbox", { name: /tome 3.*téléchargé/i })).toBeInTheDocument();
    expect(screen.getByRole("checkbox", { name: /tome 3.*lu/i })).toBeInTheDocument();
    expect(screen.getByRole("checkbox", { name: /tome 3.*nas/i })).toBeInTheDocument();
  });
});
