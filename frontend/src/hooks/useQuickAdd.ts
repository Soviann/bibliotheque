import { useCallback, useState } from "react";

export interface QuickAddItem {
  coverUrl: string | null;
  title: string;
  tomeNumber: number;
}

export function useQuickAdd() {
  const [addedItems, setAddedItems] = useState<QuickAddItem[]>([]);
  const [batchMode, setBatchMode] = useState(false);

  const toggleBatchMode = useCallback(() => setBatchMode((v) => !v), []);

  const addItem = useCallback((item: QuickAddItem) => {
    setAddedItems((prev) => [...prev, item]);
    if (navigator.vibrate) {
      navigator.vibrate(30);
    }
  }, []);

  const clearItems = useCallback(() => setAddedItems([]), []);

  return { addItem, addedItems, batchMode, clearItems, toggleBatchMode };
}
