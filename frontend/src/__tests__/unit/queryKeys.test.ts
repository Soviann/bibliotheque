import { describe, expect, it } from "vitest";
import { queryKeys } from "../../queryKeys";

describe("queryKeys", () => {
  describe("authors", () => {
    it("retourne la clé de recherche avec le terme", () => {
      expect(queryKeys.authors.search("Urasawa")).toEqual(["authors", "Urasawa"]);
    });
  });

  describe("batchLookup", () => {
    it("retourne la clé de preview avec type et force", () => {
      expect(queryKeys.batchLookup.preview("manga", true)).toEqual([
        "batch-lookup-preview", "manga", true,
      ]);
    });

    it("retourne la clé de preview avec valeurs par défaut", () => {
      expect(queryKeys.batchLookup.preview("", false)).toEqual([
        "batch-lookup-preview", "", false,
      ]);
    });
  });

  describe("comics", () => {
    it("retourne la clé de collection", () => {
      expect(queryKeys.comics.all).toEqual(["comics"]);
    });

    it("retourne la clé de détail avec l'id", () => {
      expect(queryKeys.comics.detail(42)).toEqual(["comic", 42]);
    });

    it("retourne la clé de détail avec undefined", () => {
      expect(queryKeys.comics.detail(undefined)).toEqual(["comic", undefined]);
    });

    it("retourne le préfixe pour l'invalidation en masse", () => {
      expect(queryKeys.comics.detailPrefix).toEqual(["comic"]);
    });
  });

  describe("lookup", () => {
    it("retourne la clé ISBN", () => {
      expect(queryKeys.lookup.isbn("978-2-1234", "bd")).toEqual([
        "lookup", "isbn", "978-2-1234", "bd",
      ]);
    });

    it("retourne la clé titre", () => {
      expect(queryKeys.lookup.title("Naruto", "manga")).toEqual([
        "lookup", "title", "Naruto", "manga",
      ]);
    });

    it("retourne la clé candidats titre", () => {
      expect(queryKeys.lookup.titleCandidates("Naruto", "manga", 5)).toEqual([
        "lookup", "title-candidates", "Naruto", "manga", 5,
      ]);
    });

    it("retourne la clé covers", () => {
      expect(queryKeys.lookup.covers("Naruto", "manga")).toEqual([
        "lookup", "covers", "Naruto", "manga",
      ]);
    });
  });

  describe("offline", () => {
    it("retourne la clé du compteur de queue", () => {
      expect(queryKeys.offline.queueCount).toEqual(["offline-queue-count"]);
    });

    it("retourne la clé des échecs de sync", () => {
      expect(queryKeys.offline.syncFailures).toEqual(["syncFailures"]);
    });
  });

  describe("purge", () => {
    it("retourne la clé de preview avec le nombre de jours", () => {
      expect(queryKeys.purge.preview(30)).toEqual(["purge-preview", 30]);
    });
  });

  describe("trash", () => {
    it("retourne la clé de collection", () => {
      expect(queryKeys.trash.all).toEqual(["trash"]);
    });
  });
});
