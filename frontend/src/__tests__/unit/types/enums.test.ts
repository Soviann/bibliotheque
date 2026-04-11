import {
  statusOptions,
  statusOptionsAll,
  typeOptions,
  typeOptionsAll,
} from "../../../types/enums";

describe("typeOptions", () => {
  it("contains all comic types without 'all' prefix", () => {
    expect(typeOptions).toEqual([
      { label: "BD", value: "bd" },
      { label: "Comics", value: "comics" },
      { label: "Livre", value: "livre" },
      { label: "Manga", value: "manga" },
    ]);
  });

  it("typeOptionsAll starts with 'Tous les types'", () => {
    expect(typeOptionsAll[0]).toEqual({ label: "Tous les types", value: "" });
    expect(typeOptionsAll.slice(1)).toEqual(typeOptions);
  });
});

describe("statusOptions", () => {
  it("contains all statuses without 'all' prefix", () => {
    expect(statusOptions.map((o) => o.value)).toEqual(
      expect.arrayContaining([
        "buying",
        "downloading",
        "finished",
        "stopped",
        "wishlist",
      ]),
    );
    expect(statusOptions).toHaveLength(5);
  });

  it("statusOptionsAll starts with 'Tous les statuts'", () => {
    expect(statusOptionsAll[0]).toEqual({
      label: "Tous les statuts",
      value: "",
    });
    expect(statusOptionsAll.slice(1)).toEqual(statusOptions);
  });

  it("statusOptions are sorted alphabetically by label", () => {
    const labels = statusOptions.map((o) => o.label);
    expect(labels).toEqual([...labels].sort((a, b) => a.localeCompare(b)));
  });
});
