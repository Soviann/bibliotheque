import { render, screen } from "@testing-library/react";
import { MemoryRouter } from "react-router-dom";
import { afterEach, beforeEach, describe, expect, it } from "vitest";
import AuthGuard from "../../components/AuthGuard";

describe("AuthGuard", () => {
  beforeEach(() => {
    localStorage.clear();
  });

  afterEach(() => {
    localStorage.clear();
  });

  it("renders children when authenticated", () => {
    localStorage.setItem("jwt_token", "valid-token");

    render(
      <MemoryRouter>
        <AuthGuard>
          <p>Protected content</p>
        </AuthGuard>
      </MemoryRouter>,
    );

    expect(screen.getByText("Protected content")).toBeInTheDocument();
  });

  it("redirects to /login when not authenticated", () => {
    render(
      <MemoryRouter initialEntries={["/protected"]}>
        <AuthGuard>
          <p>Protected content</p>
        </AuthGuard>
      </MemoryRouter>,
    );

    expect(screen.queryByText("Protected content")).not.toBeInTheDocument();
  });
});
