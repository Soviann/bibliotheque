import { useState } from "react";
import ComicCard from "../components/ComicCard";
import Filters from "../components/Filters";
import { useComics } from "../hooks/useComics";
import { ComicStatus } from "../types/enums";

export default function Wishlist() {
  const [type, setType] = useState("");

  const { data, isLoading } = useComics({
    status: ComicStatus.WISHLIST,
    type: type || undefined,
  });

  const comics = data?.member ?? [];

  return (
    <div className="space-y-4">
      <h1 className="text-xl font-bold text-slate-900">Liste de souhaits</h1>

      <Filters
        onStatusChange={() => {}}
        onTypeChange={setType}
        status={ComicStatus.WISHLIST}
        type={type}
      />

      {isLoading ? (
        <div className="py-12 text-center text-slate-400">Chargement…</div>
      ) : comics.length === 0 ? (
        <div className="py-12 text-center text-slate-400">Aucun souhait pour le moment</div>
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
