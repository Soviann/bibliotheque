# Backlog

Liste des tâches et idées pour le projet.

> **Sprints terminés** : voir [BACKLOG-ARCHIVE.md](./BACKLOG-ARCHIVE.md)

---

## Idées différées

Ces idées sont conservées pour référence mais ne doivent pas être implémentées actuellement.

### D.1 Ajout d'une série et/ou d'un tome par scan ISBN

- **Description** : Permettre l'ajout d'une série ou d'un tome en scannant le code-barres ISBN via la caméra du téléphone (mode PWA). Utiliserait une librairie de lecture de codes-barres (ex: `quagga2`, `@aspect/barcode-scanner`) combinée avec le service `IsbnLookupService` existant.
- **Raison du report** : Fonctionnalité avancée, nécessite une PWA plus mature et des tests utilisateur.

### D.2 Ajouter des tests pour le code JavaScript

- **Description** : Mettre en place un framework de test JavaScript (Jest ou Vitest) pour tester les contrôleurs Stimulus et les utilitaires JS. Couvrirait notamment : `library_controller.js`, `search_controller.js`, et les modules utilitaires.
- **Raison du report** : Le code JS actuel est relativement simple et couvert indirectement par les tests E2E Playwright.

### D.3 Mode hors-ligne avec synchronisation différée

- **Description** : Permettre la modification et l'ajout d'entrées (séries, tomes) en mode hors-ligne. Les modifications seraient stockées localement (IndexedDB ou localStorage) puis synchronisées automatiquement avec le serveur lors de la récupération de la connexion internet. Nécessite : détection de l'état de connexion, file d'attente des opérations, gestion des conflits, feedback utilisateur sur l'état de synchronisation.
- **Raison du report** : Fonctionnalité complexe nécessitant une architecture PWA avancée (Service Worker avec Background Sync API), gestion des conflits de données, et tests approfondis des scénarios edge-case.

---

## Utilisation

Pour traiter une tâche dans une nouvelle session Claude Code :

```
Traite la tâche D.1 du BACKLOG.md
```
