# language: fr

Fonctionnalité: Recherche de séries
  En tant qu'utilisateur connecté
  Je veux pouvoir rechercher des séries
  Afin de trouver rapidement une série spécifique

  Contexte:
    Étant donné je suis connecté
    Et une série BD "Les Schtroumpfs" existe
    Et une série BD "Johan et Pirlouit" existe
    Et une série manga "Dragon Ball" existe

  Scénario: Recherche par titre partiel
    Étant donné je suis sur la page d'accueil
    Quand je recherche "Schtroumpf"
    Alors je devrais voir la série "Les Schtroumpfs"
    Et je ne devrais pas voir la série "Johan et Pirlouit"
    Et je ne devrais pas voir la série "Dragon Ball"

  Scénario: Recherche insensible à la casse
    Étant donné je suis sur la page d'accueil
    Quand je recherche "dragon"
    Alors je devrais voir la série "Dragon Ball"

  Scénario: Recherche sans résultats
    Étant donné je suis sur la page d'accueil
    Quand je recherche "inexistant"
    Alors je ne devrais pas voir la série "Les Schtroumpfs"
    Et je ne devrais pas voir la série "Dragon Ball"
