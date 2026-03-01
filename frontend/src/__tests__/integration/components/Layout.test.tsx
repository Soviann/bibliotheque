import { screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { Route, Routes } from "react-router-dom";
import Layout from "../../../components/Layout";
import { renderWithProviders } from "../../helpers/test-utils";

describe("Layout", () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it("renders header with app title", () => {
    renderWithProviders(
      <Routes>
        <Route element={<Layout />} path="/">
          <Route element={<div>Home Content</div>} index />
        </Route>
      </Routes>,
    );

    expect(screen.getByText("Bibliothèque")).toBeInTheDocument();
  });

  it("renders children content via Outlet", () => {
    renderWithProviders(
      <Routes>
        <Route element={<Layout />} path="/">
          <Route element={<div>Page Content</div>} index />
        </Route>
      </Routes>,
    );

    expect(screen.getByText("Page Content")).toBeInTheDocument();
  });

  it("renders bottom navigation", () => {
    renderWithProviders(
      <Routes>
        <Route element={<Layout />} path="/">
          <Route element={<div>Content</div>} index />
        </Route>
      </Routes>,
    );

    expect(screen.getByText("Accueil")).toBeInTheDocument();
    expect(screen.getByText("Wishlist")).toBeInTheDocument();
    expect(screen.getByText("Corbeille")).toBeInTheDocument();
  });

  it("has a dark mode toggle button", () => {
    renderWithProviders(
      <Routes>
        <Route element={<Layout />} path="/">
          <Route element={<div>Content</div>} index />
        </Route>
      </Routes>,
    );

    expect(screen.getByTitle("Mode sombre")).toBeInTheDocument();
  });

  it("has a logout button", () => {
    renderWithProviders(
      <Routes>
        <Route element={<Layout />} path="/">
          <Route element={<div>Content</div>} index />
        </Route>
      </Routes>,
    );

    expect(screen.getByTitle("Déconnexion")).toBeInTheDocument();
  });

  it("toggles dark mode when button is clicked", async () => {
    const user = userEvent.setup();

    renderWithProviders(
      <Routes>
        <Route element={<Layout />} path="/">
          <Route element={<div>Content</div>} index />
        </Route>
      </Routes>,
    );

    // Initially "Mode sombre" (light mode)
    await user.click(screen.getByTitle("Mode sombre"));

    // After toggle, should show "Mode clair" (dark mode)
    expect(screen.getByTitle("Mode clair")).toBeInTheDocument();
  });
});
