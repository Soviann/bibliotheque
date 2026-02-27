import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen } from "@testing-library/react";
import { MemoryRouter } from "react-router-dom";
import { beforeEach, describe, expect, it } from "vitest";
import Layout from "../../components/Layout";

describe("Layout", () => {
  beforeEach(() => {
    localStorage.setItem("jwt_token", "test-token");
  });

  function renderLayout() {
    const queryClient = new QueryClient({
      defaultOptions: { queries: { retry: false } },
    });

    return render(
      <QueryClientProvider client={queryClient}>
        <MemoryRouter>
          <Layout />
        </MemoryRouter>
      </QueryClientProvider>,
    );
  }

  it("renders the app title", () => {
    renderLayout();
    expect(screen.getByText("Bibliothèque")).toBeInTheDocument();
  });

  it("renders navigation links", () => {
    renderLayout();

    expect(screen.getAllByText("Accueil")).toHaveLength(2); // desktop + mobile
    expect(screen.getAllByText("Wishlist")).toHaveLength(2);
    expect(screen.getAllByText("Ajouter")).toHaveLength(2);
    expect(screen.getAllByText("Recherche")).toHaveLength(2);
    expect(screen.getAllByText("Corbeille")).toHaveLength(2);
  });

  it("renders the logout button", () => {
    renderLayout();

    expect(screen.getByText("Déconnexion")).toBeInTheDocument();
  });
});
