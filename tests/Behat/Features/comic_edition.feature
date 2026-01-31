# language: fr

Fonctionnalité: Modification de séries
  En tant qu'utilisateur connecté
  Je veux pouvoir modifier les séries existantes
  Afin de maintenir ma collection à jour

  Contexte:
    Étant donné je suis connecté
    Et une série BD "Tintin" existe

  Scénario: Modifier le titre d'une série
    Étant donné je suis sur la page d'édition de la série "Tintin"
    Quand je remplis le titre avec "Les Aventures de Tintin"
    Et je soumets le formulaire
    Alors je devrais être sur la page d'accueil
    Et la série "Les Aventures de Tintin" devrait exister
    Et la série "Tintin" ne devrait pas exister

  Scénario: Modifier le type d'une série
    Étant donné je suis sur la page d'édition de la série "Tintin"
    Quand je sélectionne le type "Comics"
    Et je soumets le formulaire
    Alors la série "Tintin" devrait être de type "Comics"

  Scénario: Modifier le statut d'une série
    Étant donné je suis sur la page d'édition de la série "Tintin"
    Quand je sélectionne le statut "Terminée"
    Et je soumets le formulaire
    Alors la série "Tintin" devrait avoir le statut "Terminée"
