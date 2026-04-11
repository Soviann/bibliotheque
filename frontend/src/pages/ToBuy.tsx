import { ShoppingCart } from "lucide-react";
import { useMemo } from "react";
import AcquisitionList from "../components/AcquisitionList";
import AcquisitionTabs from "../components/AcquisitionTabs";
import { useComics } from "../hooks/useComics";
import { filterSeriesToBuy } from "../utils/toBuyUtils";

export default function ToBuy() {
  const { data, isFetching, isLoading } = useComics();
  const allComics = data?.member ?? [];

  const toBuyComics = useMemo(() => filterSeriesToBuy(allComics), [allComics]);

  return (
    <div className="space-y-4">
      <AcquisitionTabs />
      <AcquisitionList
        comics={toBuyComics}
        emptyDescription="Toutes vos séries en cours sont complètes"
        emptyIcon={ShoppingCart}
        emptyTitle="Rien à acheter"
        isFetching={isFetching}
        isLoading={isLoading}
        tomeAriaLabel={(label) => `Marquer le tome ${label} comme acheté`}
      />
    </div>
  );
}
