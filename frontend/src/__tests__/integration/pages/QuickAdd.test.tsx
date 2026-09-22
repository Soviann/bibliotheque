import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { MemoryRouter, Route, Routes } from "react-router-dom";
import { describe, expect, it, vi } from "vitest";
import QuickAdd from "../../../pages/QuickAdd";
import { server } from "../../helpers/server";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";

function renderQuickAdd() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={["/quick-add"]}>
        <Routes>
          <Route element={<QuickAdd />} path="/quick-add" />
          <Route element={<div>Home</div>} path="/" />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe("QuickAdd Page", () => {
  it("renders header, reticle viewfinder, and session counter", () => {
    renderQuickAdd();

    expect(screen.getByText("Scanner & Ingestion")).toBeInTheDocument();
    expect(screen.getByText("Ajout détaillé")).toBeInTheDocument();
    expect(screen.getByText("Mode Rafale : OFF")).toBeInTheDocument();
    expect(
      screen.getByText("Alignez le code-barres ISBN"),
    ).toBeInTheDocument();
    expect(screen.getByTestId("session-counter")).toHaveTextContent(
      "0 tome scanné cette session",
    );
    expect(screen.getByText("Terminer")).toBeInTheDocument();
  });

  it("toggles batch mode when clicking Mode Rafale button", async () => {
    const user = userEvent.setup();
    renderQuickAdd();

    const batchBtn = screen.getByRole("button", {
      name: /Mode Rafale : OFF/,
    });
    await user.click(batchBtn);

    expect(
      screen.getByRole("button", { name: /Mode Rafale : ON/ }),
    ).toBeInTheDocument();
  });

  it("switches between scan and search tabs", async () => {
    const user = userEvent.setup();
    renderQuickAdd();

    const searchTab = screen.getByRole("button", { name: /Rechercher/ });
    await user.click(searchTab);

    // Search input should now be visible
    expect(
      screen.getByPlaceholderText(/Titre de la série/i),
    ).toBeInTheDocument();

    const scanTab = screen.getByRole("button", { name: /Scanner/ });
    await user.click(scanTab);

    expect(
      screen.getByText("Alignez le code-barres ISBN"),
    ).toBeInTheDocument();
  });

  it("increments session counter and displays AddedStack when an item is added", async () => {
    const user = userEvent.setup();

    server.use(
      http.get("/api/lookup/title", () =>
        HttpResponse.json({
          apiMessages: {},
          results: [
            {
              authors: "Kentarō Miura",
              publisher: "Glénat",
              thumbnail: "https://example.com/berserk.jpg",
              title: "Berserk",
              tomeNumber: 41,
            },
          ],
          sources: ["bedetheque"],
        }),
      ),
    );

    renderQuickAdd();

    // Switch to search tab
    await user.click(screen.getByRole("button", { name: /Rechercher/ }));

    const input = screen.getByPlaceholderText(/Titre de la série/i);
    await user.type(input, "Berserk");
    await user.click(screen.getByRole("button", { name: "" })); // Submit search button

    await waitFor(() => {
      expect(screen.getByText("Berserk")).toBeInTheDocument();
    });

    // Click result to add it
    await user.click(screen.getByText("Berserk"));

    // Counter increments to 1
    await waitFor(() => {
      expect(screen.getByTestId("session-counter")).toHaveTextContent(
        "1 tome scanné cette session",
      );
    });

    // AddedStack count is visible
    expect(screen.getByText(/1 tome ajouté/i)).toBeInTheDocument();
  });

  it("navigates to home when clicking Terminer", async () => {
    const user = userEvent.setup();
    renderQuickAdd();

    await user.click(screen.getByText("Terminer"));

    await waitFor(() => {
      expect(screen.getByText("Home")).toBeInTheDocument();
    });
  });
});
