import { Search as SearchIcon } from "lucide-react";
import { useEffect, useState } from "react";
import ComicCard from "../components/ComicCard";
import { useComics } from "../hooks/useComics";

export default function Search() {
  const [query, setQuery] = useState("");
  const [debouncedQuery, setDebouncedQuery] = useState("");

  useEffect(() => {
    const timer = setTimeout(() => setDebouncedQuery(query), 300);
    return () => clearTimeout(timer);
  }, [query]);

  const { data, isLoading } = useComics(
    debouncedQuery.length >= 2 ? { search: debouncedQuery } : undefined,
  );

  const comics = data?.["hydra:member"] ?? [];

  return (
    <div className="space-y-4">
      <h1 className="text-xl font-bold text-slate-900">Recherche</h1>

      <div className="relative">
        <SearchIcon className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
        <input
          autoFocus
          className="w-full rounded-lg border border-slate-300 bg-white py-2 pl-10 pr-4 text-sm text-slate-700 placeholder:text-slate-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
          onChange={(e) => setQuery(e.target.value)}
          placeholder="Rechercher une série…"
          type="search"
          value={query}
        />
      </div>

      {debouncedQuery.length < 2 ? (
        <div className="py-12 text-center text-slate-400">
          Saisissez au moins 2 caractères
        </div>
      ) : isLoading ? (
        <div className="py-12 text-center text-slate-400">Recherche…</div>
      ) : comics.length === 0 ? (
        <div className="py-12 text-center text-slate-400">Aucun résultat</div>
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
