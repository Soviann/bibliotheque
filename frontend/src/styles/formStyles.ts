/** Classes de focus partagées par les champs de formulaire */
export const formFocusRing =
  "focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500";

/** Champ texte / nombre / url — sans largeur, sans focus ring */
export const formInputClassName =
  "rounded-xl border border-surface-border bg-surface-elevated px-4 py-2.5 text-sm text-text-primary dark:border-white/10 dark:bg-white/5";

/** Champ texte avec focus ring */
export const formInputFocusClassName =
  `${formInputClassName} ${formFocusRing}`;

/** Variante bg-surface-secondary avec focus ring (modales de fusion) */
export const formInputSecondaryClassName =
  "rounded-xl border border-surface-border bg-surface-secondary px-4 py-2.5 text-sm text-text-primary dark:border-white/10 dark:bg-white/5";

export const formInputSecondaryFocusClassName =
  `${formInputSecondaryClassName} ${formFocusRing}`;

/** Champ compact (px-2 au lieu de px-4) — sélecteurs date */
export const formInputCompactClassName =
  "rounded-xl border border-surface-border bg-surface-elevated px-2 py-2.5 text-sm text-text-primary dark:border-white/10 dark:bg-white/5";

/** Bouton Listbox pour formulaires (variante py-2.5) */
export const formListboxButtonClassName =
  `flex w-full items-center justify-between gap-2 ${formInputClassName} transition hover:border-primary-400 ${formFocusRing}`;

/** Bouton Listbox variante bg-surface-secondary (modales de fusion) */
export const formListboxButtonSecondaryClassName =
  `flex w-full items-center justify-between gap-2 ${formInputSecondaryClassName} transition hover:border-primary-400 ${formFocusRing}`;

/** Select natif avec focus ring (padding légèrement plus grand) */
export const formSelectClassName =
  `${formInputClassName.replace("py-2.5", "py-3")} ${formFocusRing}`;

/** Checkbox */
export const formCheckboxClassName =
  "h-4 w-4 rounded border-surface-border text-primary-600";

/** Checkbox avec focus ring */
export const formCheckboxFocusClassName =
  `${formCheckboxClassName} focus:ring-primary-500`;

/** Label de formulaire */
export const formLabelClassName =
  "mb-1 block text-sm font-medium text-text-secondary";
