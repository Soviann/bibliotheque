import { screen } from "@testing-library/react";
import { Route, Routes } from "react-router-dom";
import AuthGuard from "../../../components/AuthGuard";
import { renderWithProviders } from "../../helpers/test-utils";

describe("AuthGuard", () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it("renders children when authenticated", () => {
    localStorage.setItem("jwt_token", "fake-jwt-token");

    renderWithProviders(
      <AuthGuard>
        <div>Protected Content</div>
      </AuthGuard>,
    );

    expect(screen.getByText("Protected Content")).toBeInTheDocument();
  });

  it("redirects to /login when not authenticated", () => {
    renderWithProviders(
      <Routes>
        <Route
          element={
            <AuthGuard>
              <div>Protected Content</div>
            </AuthGuard>
          }
          path="/"
        />
        <Route element={<div>Login Page</div>} path="/login" />
      </Routes>,
    );

    expect(screen.queryByText("Protected Content")).not.toBeInTheDocument();
    expect(screen.getByText("Login Page")).toBeInTheDocument();
  });
});
