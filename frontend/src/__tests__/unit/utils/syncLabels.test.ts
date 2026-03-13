import {
  fieldLabels,
  formatSyncValue,
  operationLabels,
  resourceLabels,
} from "../../../utils/syncLabels";

describe("syncLabels", () => {
  describe("operationLabels", () => {
    it("maps operations to French labels", () => {
      expect(operationLabels.create).toBe("Création");
      expect(operationLabels.delete).toBe("Suppression");
      expect(operationLabels.update).toBe("Mise à jour");
    });
  });

  describe("resourceLabels", () => {
    it("maps resource types to French labels", () => {
      expect(resourceLabels.comic_series).toBe("série");
      expect(resourceLabels.tome).toBe("tome");
    });
  });

  describe("fieldLabels", () => {
    it("contains all expected fields", () => {
      expect(fieldLabels.authors).toBe("Auteurs");
      expect(fieldLabels.isbn).toBe("ISBN");
      expect(fieldLabels.title).toBe("Titre");
      expect(fieldLabels.bought).toBe("Acheté");
    });
  });

  describe("formatSyncValue", () => {
    it("returns dash for null/undefined", () => {
      expect(formatSyncValue(null)).toBe("—");
      expect(formatSyncValue(undefined)).toBe("—");
    });

    it("formats booleans", () => {
      expect(formatSyncValue(true)).toBe("Oui");
      expect(formatSyncValue(false)).toBe("Non");
    });

    it("formats arrays", () => {
      expect(formatSyncValue([1, 2, 3])).toBe("3 élément(s)");
    });

    it("formats other values as string", () => {
      expect(formatSyncValue("hello")).toBe("hello");
      expect(formatSyncValue(42)).toBe("42");
    });
  });
});
