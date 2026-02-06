# language: fr

Fonctionnalité: Création de séries
  En tant qu'utilisateur connecté
  Je veux pouvoir créer des séries
  Afin de gérer ma collection

  Contexte:
    Étant donné je suis connecté

  Scénario: Créer une série BD simple
    Étant donné je suis sur la page de création d'une série
    Quand je remplis le titre avec "Astérix"
    Et je sélectionne le type "BD"
    Et je sélectionne le statut "En cours d'achat"
    Et je soumets le formulaire
    Alors je devrais être sur la page d'accueil
    Et la série "Astérix" devrait exister
    Et la série "Astérix" devrait être de type "BD"

  Scénario: Créer une série manga
    Étant donné je suis sur la page de création d'une série
    Quand je remplis le titre avec "One Piece"
    Et je sélectionne le type "Manga"
    Et je sélectionne le statut "En cours d'achat"
    Et je soumets le formulaire
    Alors je devrais être sur la page d'accueil
    Et la série "One Piece" devrait exister
    Et la série "One Piece" devrait être de type "Manga"

  Scénario: Créer une série wishlist redirige vers la wishlist
    Étant donné je suis sur la page de création d'une série
    Quand je remplis le titre avec "Série à acheter"
    Et je sélectionne le type "BD"
    Et je coche la case wishlist
    Et je soumets le formulaire
    Alors je devrais être sur la page wishlist
    Et la série "Série à acheter" devrait être dans la wishlist

  Scénario: Créer un one-shot
    Étant donné je suis sur la page de création d'une série
    Quand je remplis le titre avec "L'Arabe du futur"
    Et je sélectionne le type "BD"
    Et je coche la case one-shot
    Et je soumets le formulaire
    Alors je devrais être sur la page d'accueil
    Et la série "L'Arabe du futur" devrait exister
    Et la série "L'Arabe du futur" devrait être un one-shot
