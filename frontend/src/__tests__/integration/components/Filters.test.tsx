import { screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import Filters from "../../../components/Filters";
import { renderWithProviders } from "../../helpers/test-utils";

function mockMatchMedia(mobile: boolean) {
  Object.defineProperty(window, "matchMedia", {
    configurable: true,
    value: vi.fn().mockImplementation((query: string) => ({
      addEventListener: vi.fn(),
      dispatchEvent: vi.fn(),
      matches: mobile && query === "(max-width: 639px)",
      media: query,
      onchange: null,
      removeEventListener: vi.fn(),
    })),
    writable: true,
  });
}

describe("Filters", () => {
  const defaultProps = {
    onSortChange: vi.fn(),
    onStatusChange: vi.fn(),
    onTypeChange: vi.fn(),
    sort: "title-asc" as const,
    status: "",
    type: "",
  };

  beforeEach(() => {
    defaultProps.onSortChange = vi.fn();
    defaultProps.onStatusChange = vi.fn();
    defaultProps.onTypeChange = vi.fn();
  });

  describe("Desktop (>= 640px)", () => {
    beforeEach(() => mockMatchMedia(false));

    it("renders type filter with default label", () => {
      renderWithProviders(<Filters {...defaultProps} />);

      expect(screen.getByText("Tous les types")).toBeInTheDocument();
    });

    it("renders status filter with default label", () => {
      renderWithProviders(<Filters {...defaultProps} />);

      expect(screen.getByText("Tous les statuts")).toBeInTheDocument();
    });

    it("calls onTypeChange when type filter is changed", async () => {
      const user = userEvent.setup();
      renderWithProviders(<Filters {...defaultProps} />);

      await user.click(screen.getByText("Tous les types"));
      await user.click(screen.getByText("Manga"));

      expect(defaultProps.onTypeChange).toHaveBeenCalledWith("manga");
    });

    it("calls onStatusChange when status filter is changed", async () => {
      const user = userEvent.setup();
      renderWithProviders(<Filters {...defaultProps} />);

      await user.click(screen.getByText("Tous les statuts"));
      await user.click(screen.getByText("Terminé"));

      expect(defaultProps.onStatusChange).toHaveBeenCalledWith("finished");
    });

    it("shows selected type label", () => {
      renderWithProviders(<Filters {...defaultProps} type="manga" />);

      expect(screen.getByText("Manga")).toBeInTheDocument();
    });

    it("shows selected status label", () => {
      renderWithProviders(<Filters {...defaultProps} status="buying" />);

      expect(screen.getByText("En cours d'achat")).toBeInTheDocument();
    });

    it("calls onTypeChange with empty string when selecting 'Tous les types'", async () => {
      const user = userEvent.setup();
      renderWithProviders(<Filters {...defaultProps} type="manga" />);

      await user.click(screen.getByText("Manga"));
      await user.click(screen.getByText("Tous les types"));

      expect(defaultProps.onTypeChange).toHaveBeenCalledWith("");
    });

    it("renders sort selector with default label", () => {
      renderWithProviders(<Filters {...defaultProps} />);

      expect(screen.getByText("Titre A→Z")).toBeInTheDocument();
    });

    it("calls onSortChange when sort option is changed", async () => {
      const user = userEvent.setup();
      renderWithProviders(<Filters {...defaultProps} />);

      await user.click(screen.getByText("Titre A→Z"));
      await user.click(screen.getByText("Plus récent"));

      expect(defaultProps.onSortChange).toHaveBeenCalledWith("createdAt-desc");
    });

    it("shows selected sort label", () => {
      renderWithProviders(<Filters {...defaultProps} sort="tomes-desc" />);

      expect(screen.getByText("Plus de tomes")).toBeInTheDocument();
    });

    it("does not render mobile filter button", () => {
      renderWithProviders(<Filters {...defaultProps} />);

      expect(screen.queryByTestId("filters-button")).not.toBeInTheDocument();
    });
  });

  describe("Mobile (< 640px)", () => {
    beforeEach(() => mockMatchMedia(true));

    it("renders icon button instead of Listbox dropdowns", () => {
      renderWithProviders(<Filters {...defaultProps} />);

      expect(screen.getByTestId("filters-button")).toBeInTheDocument();
      expect(screen.queryByText("Tous les types")).not.toBeInTheDocument();
      expect(screen.queryByText("Tous les statuts")).not.toBeInTheDocument();
    });

    it("has dynamic aria-label reflecting active filter count", () => {
      renderWithProviders(<Filters {...defaultProps} status="buying" type="manga" />);

      expect(screen.getByTestId("filters-button")).toHaveAttribute(
        "aria-label",
        "Filtres (2 actifs)",
      );
    });

    it("has plain aria-label when no filters active", () => {
      renderWithProviders(<Filters {...defaultProps} />);

      expect(screen.getByTestId("filters-button")).toHaveAttribute(
        "aria-label",
        "Filtres",
      );
    });

    it("has aria-label with 1 active filter", () => {
      renderWithProviders(<Filters {...defaultProps} type="manga" />);

      expect(screen.getByTestId("filters-button")).toHaveAttribute(
        "aria-label",
        "Filtres (1 actif)",
      );
    });

    it("shows indicator dot when filters are active", () => {
      renderWithProviders(<Filters {...defaultProps} status="buying" type="manga" />);

      expect(screen.getByTestId("filters-indicator")).toBeInTheDocument();
    });

    it("shows no indicator when no filters active", () => {
      renderWithProviders(<Filters {...defaultProps} />);

      expect(screen.queryByTestId("filters-indicator")).not.toBeInTheDocument();
    });

    it("shows indicator with only type active", () => {
      renderWithProviders(<Filters {...defaultProps} type="manga" />);

      expect(screen.getByTestId("filters-indicator")).toBeInTheDocument();
    });

    it("opens drawer on button click with 3 select elements", async () => {
      const user = userEvent.setup();
      renderWithProviders(<Filters {...defaultProps} />);

      await user.click(screen.getByTestId("filters-button"));

      expect(screen.getByText("Type")).toBeInTheDocument();
      expect(screen.getByText("Statut")).toBeInTheDocument();
      expect(screen.getByText("Tri")).toBeInTheDocument();

      const selects = screen.getAllByRole("combobox");
      expect(selects).toHaveLength(3);
    });

    it("calls onTypeChange when selecting a type in drawer", async () => {
      const user = userEvent.setup();
      renderWithProviders(<Filters {...defaultProps} />);

      await user.click(screen.getByTestId("filters-button"));

      const typeSelect = screen.getAllByRole("combobox")[0];
      await user.selectOptions(typeSelect, "manga");

      expect(defaultProps.onTypeChange).toHaveBeenCalledWith("manga");
    });

    it("calls onStatusChange when selecting a status in drawer", async () => {
      const user = userEvent.setup();
      renderWithProviders(<Filters {...defaultProps} />);

      await user.click(screen.getByTestId("filters-button"));

      const statusSelect = screen.getAllByRole("combobox")[1];
      await user.selectOptions(statusSelect, "finished");

      expect(defaultProps.onStatusChange).toHaveBeenCalledWith("finished");
    });

    it("calls onSortChange when selecting a sort in drawer", async () => {
      const user = userEvent.setup();
      renderWithProviders(<Filters {...defaultProps} />);

      await user.click(screen.getByTestId("filters-button"));

      const sortSelect = screen.getAllByRole("combobox")[2];
      await user.selectOptions(sortSelect, "createdAt-desc");

      expect(defaultProps.onSortChange).toHaveBeenCalledWith("createdAt-desc");
    });

    it("closes drawer when clicking close button", async () => {
      const user = userEvent.setup();
      renderWithProviders(<Filters {...defaultProps} />);

      await user.click(screen.getByTestId("filters-button"));
      expect(screen.getByText("Type")).toBeInTheDocument();

      await user.click(screen.getByLabelText("Fermer"));

      expect(screen.queryByText("Type")).not.toBeInTheDocument();
    });
  });
});
