# language: fr

Fonctionnalité: Filtrage des séries
  En tant qu'utilisateur connecté
  Je veux pouvoir filtrer les séries par type, statut et NAS
  Afin de trouver rapidement ce que je cherche

  Contexte:
    Étant donné je suis connecté
    Et une série BD "Lucky Luke" existe
    Et une série manga "Naruto" existe
    Et une série comics "Batman" existe
    Et une série "Gaston Lagaffe" avec le statut "Terminée" existe
    Et une série "XIII" avec le statut "Arrêtée" existe
    Et une série "Blacksad" avec des tomes sur le NAS existe
    Et une série "Largo Winch" avec des tomes existe

  Scénario: Filtrer par type BD
    Étant donné je suis sur la page d'accueil
    Quand je filtre par type "BD"
    Alors je devrais voir la série "Lucky Luke"
    Et je ne devrais pas voir la série "Naruto"
    Et je ne devrais pas voir la série "Batman"

  Scénario: Filtrer par type Manga
    Étant donné je suis sur la page d'accueil
    Quand je filtre par type "Manga"
    Alors je devrais voir la série "Naruto"
    Et je ne devrais pas voir la série "Lucky Luke"

  Scénario: Filtrer par type Comics
    Étant donné je suis sur la page d'accueil
    Quand je filtre par type "Comics"
    Alors je devrais voir la série "Batman"
    Et je ne devrais pas voir la série "Lucky Luke"

  Scénario: Filtrer par statut Terminée
    Étant donné je suis sur la page d'accueil
    Quand je filtre par statut "Terminée"
    Alors je devrais voir la série "Gaston Lagaffe"
    Et je ne devrais pas voir la série "Lucky Luke"

  Scénario: Filtrer par statut Arrêtée
    Étant donné je suis sur la page d'accueil
    Quand je filtre par statut "Arrêtée"
    Alors je devrais voir la série "XIII"
    Et je ne devrais pas voir la série "Lucky Luke"

  Scénario: Filtrer les séries sur le NAS
    Étant donné je suis sur la page d'accueil
    Quand je filtre les séries sur le NAS
    Alors je devrais voir la série "Blacksad"
    Et je ne devrais pas voir la série "Largo Winch"

  Scénario: Filtrer les séries non présentes sur le NAS
    Étant donné je suis sur la page d'accueil
    Quand je filtre les séries non présentes sur le NAS
    Alors je devrais voir la série "Largo Winch"
    Et je ne devrais pas voir la série "Blacksad"

  Scénario: Recherche par titre sur la page d'accueil
    Étant donné je suis sur la page d'accueil
    Quand je recherche "Lucky"
    Alors je devrais voir la série "Lucky Luke"
    Et je ne devrais pas voir la série "Naruto"
