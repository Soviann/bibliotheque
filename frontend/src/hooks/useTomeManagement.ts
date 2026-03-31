import { useState } from "react";
import { toast } from "sonner";
import { fetchLookupIsbn } from "./useLookup";
import type { FormData, TomeFormData } from "./useComicForm";
import { compareTomes } from "./useComicForm";

export interface TomeManager {
  addBatchTomes: () => void;
  addTome: () => void;
  batchFrom: number;
  batchSize: number;
  batchTo: number;
  lookupTomeIsbn: (index: number) => void;
  maxBatchSize: number;
  removeTome: (index: number) => void;
  setBatchFrom: (v: number) => void;
  setBatchTo: (v: number) => void;
  tomeLookupLoading: number | null;
  updateTome: <K extends keyof TomeFormData>(index: number, key: K, value: TomeFormData[K]) => void;
}

const maxBatchSize = 100;

function emptyTome(number: number, isHorsSerie = false): TomeFormData {
  return { bought: false, isHorsSerie, isbn: "", number, onNas: false, read: false, title: "", tomeEnd: "" };
}

export function useTomeManagement(
  form: FormData,
  update: <K extends keyof FormData>(key: K, value: FormData[K]) => void,
): TomeManager {
  const [batchFrom, setBatchFrom] = useState(1);
  const [batchTo, setBatchTo] = useState(1);
  const [tomeLookupLoading, setTomeLookupLoading] = useState<number | null>(null);

  const batchSize = batchTo - batchFrom + 1;

  const addTome = () => {
    const regularTomes = form.tomes.filter((t) => !t.isHorsSerie);
    const nextNum = regularTomes.length > 0 ? Math.max(...regularTomes.map((t) => t.number)) + 1 : 1;
    update("tomes", [...form.tomes, emptyTome(nextNum)]);
  };

  const addBatchTomes = () => {
    if (batchFrom < 1 || batchFrom > batchTo || batchSize > maxBatchSize) return;
    const existingNumbers = new Set(form.tomes.map((t) => t.number));
    const newTomes: TomeFormData[] = [];
    for (let n = batchFrom; n <= batchTo; n++) {
      if (!existingNumbers.has(n)) {
        newTomes.push(emptyTome(n));
      }
    }
    if (newTomes.length > 0) {
      update("tomes", [...form.tomes, ...newTomes].sort(compareTomes));
    }
  };

  const removeTome = (index: number) => {
    update(
      "tomes",
      form.tomes.filter((_, i) => i !== index),
    );
  };

  const updateTome = <K extends keyof TomeFormData>(index: number, key: K, value: TomeFormData[K]) => {
    update(
      "tomes",
      form.tomes.map((t, i) => (i === index ? { ...t, [key]: value } : t)),
    );
  };

  const lookupTomeIsbn = async (index: number) => {
    const isbn = form.tomes[index]?.isbn;
    if (!isbn || isbn.length < 10) return;

    setTomeLookupLoading(index);
    try {
      const result = await fetchLookupIsbn(isbn, form.type);
      update(
        "tomes",
        form.tomes.map((t, i) =>
          i === index
            ? { ...t, isbn: result.isbn ?? t.isbn, title: result.title ?? t.title, tomeEnd: result.tomeEnd?.toString() ?? t.tomeEnd }
            : t,
        ),
      );
      toast.success(`Tome ${form.tomes[index].number} : informations récupérées`);
    } catch {
      toast.error("Échec de la recherche ISBN");
    } finally {
      setTomeLookupLoading(null);
    }
  };

  return {
    addBatchTomes,
    addTome,
    batchFrom,
    batchSize,
    batchTo,
    lookupTomeIsbn,
    maxBatchSize,
    removeTome,
    setBatchFrom,
    setBatchTo,
    tomeLookupLoading,
    updateTome,
  };
}
