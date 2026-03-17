import { useState } from "react";
import { useAuthors } from "./useAuthors";
import type { FormData } from "./useComicForm";
import type { Author } from "../types/api";

export interface AuthorManager {
  addAuthor: (author: Author) => void;
  authorOptions: Author[];
  authorSearch: string;
  removeAuthor: (index: number) => void;
  setAuthorSearch: (v: string) => void;
}

export function useAuthorManagement(
  form: FormData,
  update: <K extends keyof FormData>(key: K, value: FormData[K]) => void,
): AuthorManager {
  const [authorSearch, setAuthorSearch] = useState("");
  const { data: authorResults } = useAuthors(authorSearch);
  const authorOptions = authorResults?.member ?? [];

  const addAuthor = (author: Author) => {
    if (!form.authors.some((a) => a.name === author.name)) {
      update("authors", [...form.authors, author]);
    }
    setAuthorSearch("");
  };

  const removeAuthor = (index: number) => {
    update(
      "authors",
      form.authors.filter((_, i) => i !== index),
    );
  };

  return {
    addAuthor,
    authorOptions,
    authorSearch,
    removeAuthor,
    setAuthorSearch,
  };
}
