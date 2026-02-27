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

  it("renders bottom navigation links", () => {
    renderLayout();

    expect(screen.getByText("Accueil")).toBeInTheDocument();
    expect(screen.getByText("Wishlist")).toBeInTheDocument();
    expect(screen.getByText("Ajouter")).toBeInTheDocument();
    expect(screen.getByText("Corbeille")).toBeInTheDocument();
  });

  it("renders the logout button", () => {
    renderLayout();

    expect(screen.getByTitle("Déconnexion")).toBeInTheDocument();
  });

  it("renders the dark mode toggle", () => {
    renderLayout();

    expect(screen.getByTitle("Mode sombre")).toBeInTheDocument();
  });
});
