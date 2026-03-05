import { screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import MergeSeries from "../../../pages/MergeSeries";
import {
  createMockComicSeries,
  createMockHydraCollection,
} from "../../helpers/factories";
import { server } from "../../helpers/server";
import { renderWithProviders } from "../../helpers/test-utils";

describe("MergeSeries", () => {
  beforeEach(() => {
    localStorage.clear();
    localStorage.setItem("jwt_token", "fake-jwt-token");

    server.use(
      http.get("/api/comic_series", () =>
        HttpResponse.json(
          createMockHydraCollection(
            [
              createMockComicSeries({ id: 1, title: "Naruto Tome 1" }),
              createMockComicSeries({ id: 2, title: "Naruto Tome 2" }),
            ],
            "/api/comic_series",
          ),
        ),
      ),
    );
  });

  it("renders with two tabs", () => {
    renderWithProviders(<MergeSeries />);

    expect(screen.getByText("Fusion de series")).toBeInTheDocument();
    expect(screen.getByText("Detection automatique")).toBeInTheDocument();
    expect(screen.getByText("Selection manuelle")).toBeInTheDocument();
  });

  it("auto detect tab has filters and detect button", () => {
    renderWithProviders(<MergeSeries />);

    expect(screen.getByText("Type")).toBeInTheDocument();
    expect(screen.getByText("Lettre")).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: /Detecter les groupes/ }),
    ).toBeInTheDocument();
  });

  it("manual tab has search input", async () => {
    const user = userEvent.setup();
    renderWithProviders(<MergeSeries />);

    await user.click(screen.getByText("Selection manuelle"));

    expect(
      screen.getByPlaceholderText("Rechercher une serie..."),
    ).toBeInTheDocument();
  });
});
