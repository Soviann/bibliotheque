# ADR 0001 — Pas de migration de la couche Gemini vers Symfony AI

- **Date** : 2026-06-20
- **Statut** : Accepté
- **Périmètre** : `backend/src/Service/Lookup/Gemini/`

## Contexte

La couche d'enrichissement IA repose aujourd'hui sur le client tiers
`google-gemini-php/symfony` (^2.0), enveloppé par du code maison
(`GeminiClientPool`, `GeminiQueryService`, `AbstractGeminiLookupProvider`,
`GeminiCircuitBreaker`, `GeminiJsonParser`). La question s'est posée de migrer
vers le composant officiel **Symfony AI** (`symfony/ai-platform` +
`symfony/ai-bundle` + bridge Gemini).

L'étude de faisabilité a confirmé que Symfony AI couvre nativement la majorité
des besoins : bridge Gemini, grounding Google Search
(`server_tools.google_search`), sortie structurée JSON (`response_format` →
tableau typé, plus robuste que notre extraction par regex), suivi du token usage
(que nous n'avons pas aujourd'hui). Le cache et le rate limiter Symfony restent
inchangés.

## Décision

**Nous ne migrons pas.** Nous conservons `google-gemini-php/symfony` et notre
couche maison.

## Raison principale — rotation multi-clés (`GeminiClientPool`)

C'est le point bloquant. Notre pool itère **modèles (boucle externe) × clés API
(boucle interne)** dans un ordre de priorité précis, avec un **cooldown
d'épuisement en mémoire de 90 s** par combinaison et une distinction
`rateLimited` (429) vs autres codes retryables (400/401/403/404/500/503).

Le `FailoverPlatform` de Symfony AI bascule entre **plateformes différentes**
(p. ex. Ollama → OpenAI) selon une **liste plate ordonnée**, pas entre N clés
sur une même plateforme Gemini. Reproduire notre comportement imposerait :

- d'enregistrer le bridge Gemini N fois (une par clé) puis d'aplatir
  manuellement toutes les combinaisons `clé × modèle` ;
- de réimplémenter par-dessus le cooldown d'épuisement et le flag `rateLimited`,
  absents du framework.

Bilan : les deux seules parties réellement spécifiques de notre code (le pool de
rotation clé×modèle **et** le `GeminiCircuitBreaker` à reset quotidien Pacifique)
sont précisément celles que Symfony AI ne remplace pas proprement. On
continuerait donc à maintenir du code maison **en plus** de la migration — le
rapport coût/bénéfice est défavorable tant que l'on reste mono-fournisseur
(Gemini).

## Points secondaires non tranchés (n'ont pas pesé dans la décision)

- **Safety settings** (`HarmCategory`/`HarmBlockThreshold`, 4 catégories en
  `BLOCK_ONLY_HIGH`) : pas de surface de config first-class confirmée côté bridge
  Gemini ; passage probable via les `options` brutes, à vérifier.
- **`promptFeedback.blockReason`** : à confirmer qu'il reste accessible via les
  metadata de la réponse abstraite.

## Conséquences

- On garde la dette de maintenance de ~560 lignes de couche Gemini maison.
- On ne bénéficie pas (pour l'instant) de la sortie structurée native ni du
  suivi token usage de Symfony AI.

## Quand rouvrir cette décision

Reconsidérer **uniquement** si l'un de ces déclencheurs apparaît :

1. **Besoin multi-fournisseurs** (ajouter Anthropic/OpenAI/VertexAI à côté de
   Gemini) — c'est là que l'abstraction Symfony AI devient rentable.
2. Symfony AI ajoute une **rotation multi-clés sur une même plateforme** avec
   cooldown/épuisement (rendrait `GeminiClientPool` superflu).
3. Abandon du mono-fournisseur Gemini gratuit (tier RPD) qui justifie
   aujourd'hui la rotation de clés.
