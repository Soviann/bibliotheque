import { useState } from "react";
import ComicCard from "../components/ComicCard";
import Filters from "../components/Filters";
import { useComics } from "../hooks/useComics";

export default function Home() {
  const [status, setStatus] = useState("");
  const [type, setType] = useState("");

  const { data, isLoading } = useComics({
    status: status || undefined,
    type: type || undefined,
  });

  const comics = data?.member ?? [];

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-bold text-slate-900">Ma bibliothèque</h1>
        <span className="text-sm text-slate-500">
          {data?.totalItems ?? 0} séries
        </span>
      </div>

      <Filters
        onStatusChange={setStatus}
        onTypeChange={setType}
        status={status}
        type={type}
      />

      {isLoading ? (
        <div className="py-12 text-center text-slate-400">Chargement…</div>
      ) : comics.length === 0 ? (
        <div className="py-12 text-center text-slate-400">Aucune série trouvée</div>
      ) : (
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
          {comics.map((comic) => (
            <ComicCard comic={comic} key={comic.id} />
          ))}
        </div>
      )}
    </div>
  );
}
