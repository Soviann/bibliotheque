import { render, screen } from "@testing-library/react";
import { MemoryRouter } from "react-router-dom";
import Breadcrumb from "../../../components/Breadcrumb";

describe("Breadcrumb", () => {
  it("renders parent link and current page label", () => {
    render(
      <MemoryRouter>
        <Breadcrumb
          items={[
            { href: "/tools", label: "Outils" },
            { label: "Import Excel" },
          ]}
        />
      </MemoryRouter>,
    );

    const link = screen.getByRole("link", { name: "Outils" });
    expect(link).toHaveAttribute("href", "/tools");
    expect(screen.getByText("Import Excel")).toBeInTheDocument();
  });

  it("renders separator between items", () => {
    render(
      <MemoryRouter>
        <Breadcrumb
          items={[{ href: "/tools", label: "Outils" }, { label: "Purge" }]}
        />
      </MemoryRouter>,
    );

    expect(screen.getByText("/")).toBeInTheDocument();
  });

  it("renders aria navigation landmark", () => {
    render(
      <MemoryRouter>
        <Breadcrumb
          items={[{ href: "/tools", label: "Outils" }, { label: "Lookup" }]}
        />
      </MemoryRouter>,
    );

    expect(
      screen.getByRole("navigation", { name: "Fil d'Ariane" }),
    ).toBeInTheDocument();
  });

  it("renders last item as aria-current=page", () => {
    render(
      <MemoryRouter>
        <Breadcrumb
          items={[{ href: "/tools", label: "Outils" }, { label: "Fusion" }]}
        />
      </MemoryRouter>,
    );

    expect(screen.getByText("Fusion")).toHaveAttribute("aria-current", "page");
  });
});
