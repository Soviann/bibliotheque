import { Download } from "lucide-react";
import { useMemo } from "react";
import AcquisitionList from "../components/AcquisitionList";
import AcquisitionTabs from "../components/AcquisitionTabs";
import { useComics } from "../hooks/useComics";
import { filterSeriesToDownload } from "../utils/toBuyUtils";

export default function ToDownload() {
  const { data, isFetching, isLoading } = useComics();
  const allComics = data?.member ?? [];

  const toDownloadComics = useMemo(
    () => filterSeriesToDownload(allComics),
    [allComics],
  );

  return (
    <div className="space-y-4">
      <AcquisitionTabs />
      <AcquisitionList
        comics={toDownloadComics}
        emptyDescription="Toutes vos séries à télécharger sont complètes"
        emptyIcon={Download}
        emptyTitle="Rien à télécharger"
        isFetching={isFetching}
        isLoading={isLoading}
        tomeAriaLabel={(label) => `Marquer le tome ${label} comme téléchargé`}
      />
    </div>
  );
}
