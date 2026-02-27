import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { MemoryRouter } from "react-router-dom";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import Login from "../../pages/Login";

describe("Login", () => {
  beforeEach(() => {
    localStorage.clear();
    vi.stubGlobal("fetch", vi.fn());
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  function renderLogin() {
    const queryClient = new QueryClient({
      defaultOptions: { mutations: { retry: false }, queries: { retry: false } },
    });

    return render(
      <QueryClientProvider client={queryClient}>
        <MemoryRouter>
          <Login />
        </MemoryRouter>
      </QueryClientProvider>,
    );
  }

  it("renders email and password fields", () => {
    renderLogin();

    expect(screen.getByLabelText("Email")).toBeInTheDocument();
    expect(screen.getByLabelText("Mot de passe")).toBeInTheDocument();
  });

  it("renders submit button", () => {
    renderLogin();

    expect(
      screen.getByRole("button", { name: "Se connecter" }),
    ).toBeInTheDocument();
  });

  it("renders app title", () => {
    renderLogin();

    expect(screen.getByText("Bibliothèque")).toBeInTheDocument();
  });

  it("submits the form with credentials", async () => {
    vi.mocked(fetch).mockResolvedValue(
      new Response(JSON.stringify({ token: "jwt-123" }), { status: 200 }),
    );

    const user = userEvent.setup();
    renderLogin();

    await user.type(screen.getByLabelText("Email"), "user@test.com");
    await user.type(screen.getByLabelText("Mot de passe"), "password");
    await user.click(screen.getByRole("button", { name: "Se connecter" }));

    expect(fetch).toHaveBeenCalledWith(
      "/api/login",
      expect.objectContaining({
        body: JSON.stringify({
          email: "user@test.com",
          password: "password",
        }),
        method: "POST",
      }),
    );
  });

  it("displays error on failed login", async () => {
    vi.mocked(fetch).mockResolvedValue(
      new Response(JSON.stringify({}), { status: 401 }),
    );

    const user = userEvent.setup();
    renderLogin();

    await user.type(screen.getByLabelText("Email"), "bad@test.com");
    await user.type(screen.getByLabelText("Mot de passe"), "wrong");
    await user.click(screen.getByRole("button", { name: "Se connecter" }));

    expect(
      await screen.findByText("Identifiants invalides"),
    ).toBeInTheDocument();
  });
});
