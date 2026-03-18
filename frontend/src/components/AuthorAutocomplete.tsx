import {
  Combobox,
  ComboboxInput,
  ComboboxOption,
  ComboboxOptions,
} from "@headlessui/react";
import { Plus, Search, X } from "lucide-react";
import { formLabelClassName } from "../styles/formStyles";
import type { Author } from "../types/api";

interface AuthorAutocompleteProps {
  addAuthor: (author: Author) => void;
  authorOptions: Author[];
  authorSearch: string;
  authors: Author[];
  removeAuthor: (index: number) => void;
  setAuthorSearch: (v: string) => void;
}

export default function AuthorAutocomplete({
  addAuthor,
  authorOptions,
  authorSearch,
  authors,
  removeAuthor,
  setAuthorSearch,
}: AuthorAutocompleteProps) {
  return (
    <div>
      <label className={formLabelClassName}>Auteurs</label>
      <div className="flex flex-wrap gap-2 mb-2">
        {authors.map((author, i) => (
          <span
            className="flex items-center gap-1 rounded-full bg-primary-100 px-3 py-1 text-sm font-medium text-primary-700 dark:bg-primary-950/30 dark:text-primary-400"
            key={author.id}
          >
            {author.name}
            <button
              aria-label={`Retirer ${author.name}`}
              className="ml-1 rounded-full p-0.5 hover:bg-primary-200 dark:hover:bg-primary-900/40"
              onClick={() => removeAuthor(i)}
              type="button"
            >
              <X className="h-3 w-3" />
            </button>
          </span>
        ))}
      </div>
      <Combobox
        onChange={(author: Author | null) => {
          if (author) addAuthor(author);
        }}
        value={null}
      >
        <div className="relative">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" />
          <ComboboxInput
            className="w-full rounded-lg border border-surface-border bg-surface-primary py-2 pl-10 pr-3 text-sm text-text-primary"
            displayValue={() => authorSearch}
            onChange={(e) => setAuthorSearch(e.target.value)}
            placeholder="Rechercher ou créer un auteur…"
          />
          <ComboboxOptions className="absolute z-10 mt-1 max-h-48 w-full overflow-auto rounded-lg border border-surface-border bg-surface-primary shadow-lg">
            {authorOptions.map((author) => (
              <ComboboxOption
                className="cursor-pointer px-3 py-2 text-sm text-text-primary data-[focus]:bg-primary-50 dark:data-[focus]:bg-primary-950/30"
                key={author.id}
                value={author}
              >
                {author.name}
              </ComboboxOption>
            ))}
            {authorSearch.length >= 2 && !authorOptions.some((a) => a.name.toLowerCase() === authorSearch.toLowerCase()) && (
              <>
                {authorOptions.length > 0 && (
                  <div className="mx-3 border-t border-surface-border" />
                )}
                <ComboboxOption
                  className="cursor-pointer px-3 py-2 text-sm font-medium text-primary-700 dark:text-primary-400 data-[focus]:bg-primary-50 dark:data-[focus]:bg-primary-950/30"
                  value={{ "@id": "", id: -Date.now(), name: authorSearch } as Author}
                >
                  <Plus className="mr-1 inline h-3 w-3" />
                  Créer « {authorSearch} »
                </ComboboxOption>
              </>
            )}
          </ComboboxOptions>
        </div>
      </Combobox>
    </div>
  );
}
