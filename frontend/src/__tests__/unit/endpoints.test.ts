import { describe, expect, it } from "vitest";
import { endpoints } from "../../endpoints";

describe("endpoints", () => {
  describe("authors", () => {
    it("retourne le chemin de base", () => {
      expect(endpoints.authors).toBe("/authors");
    });
  });

  describe("batchLookup", () => {
    it("retourne le chemin preview", () => {
      expect(endpoints.batchLookup.preview).toBe("/tools/batch-lookup/preview");
    });

    it("retourne le chemin run", () => {
      expect(endpoints.batchLookup.run).toBe("/tools/batch-lookup/run");
    });
  });

  describe("comicSeries", () => {
    it("retourne le chemin de collection", () => {
      expect(endpoints.comicSeries.collection).toBe("/comic_series");
    });

    it("retourne le chemin de détail", () => {
      expect(endpoints.comicSeries.detail(42)).toBe("/comic_series/42");
    });

    it("retourne le chemin de restauration", () => {
      expect(endpoints.comicSeries.restore(7)).toBe("/comic_series/7/restore");
    });

    it("retourne le chemin des tomes", () => {
      expect(endpoints.comicSeries.tomes(7)).toBe("/comic_series/7/tomes");
    });
  });

  describe("login", () => {
    it("retourne le chemin google", () => {
      expect(endpoints.login.google).toBe("/login/google");
    });
  });

  describe("lookup", () => {
    it("retourne le chemin covers", () => {
      expect(endpoints.lookup.covers).toBe("/lookup/covers");
    });

    it("retourne le chemin isbn", () => {
      expect(endpoints.lookup.isbn).toBe("/lookup/isbn");
    });

    it("retourne le chemin title", () => {
      expect(endpoints.lookup.title).toBe("/lookup/title");
    });
  });

  describe("mergeSeries", () => {
    it("retourne le chemin detect", () => {
      expect(endpoints.mergeSeries.detect).toBe("/merge-series/detect");
    });

    it("retourne le chemin execute", () => {
      expect(endpoints.mergeSeries.execute).toBe("/merge-series/execute");
    });

    it("retourne le chemin preview", () => {
      expect(endpoints.mergeSeries.preview).toBe("/merge-series/preview");
    });

    it("retourne le chemin suggest", () => {
      expect(endpoints.mergeSeries.suggest).toBe("/merge-series/suggest");
    });
  });

  describe("purge", () => {
    it("retourne le chemin execute", () => {
      expect(endpoints.purge.execute).toBe("/tools/purge/execute");
    });

    it("retourne le chemin preview", () => {
      expect(endpoints.purge.preview).toBe("/tools/purge/preview");
    });
  });

  describe("tomes", () => {
    it("retourne le chemin de détail", () => {
      expect(endpoints.tomes.detail(99)).toBe("/tomes/99");
    });
  });

  describe("trash", () => {
    it("retourne le chemin de collection", () => {
      expect(endpoints.trash.collection).toBe("/trash");
    });

    it("retourne le chemin de suppression permanente", () => {
      expect(endpoints.trash.permanent(5)).toBe("/trash/5/permanent");
    });
  });
});
