# language: fr

Fonctionnalité: Suppression de séries
  En tant qu'utilisateur connecté
  Je veux pouvoir supprimer des séries
  Afin de nettoyer ma collection

  Contexte:
    Étant donné je suis connecté

  Scénario: Supprimer une série de la bibliothèque
    Étant donné une série BD "Série à supprimer" existe
    Quand je supprime la série "Série à supprimer"
    Alors je devrais être sur la page d'accueil
    Et la série "Série à supprimer" ne devrait pas exister

  Scénario: Supprimer une série de la wishlist redirige vers la wishlist
    Étant donné une série wishlist "Wishlist à supprimer" existe
    Quand je supprime la série "Wishlist à supprimer"
    Alors je devrais être sur la page wishlist
    Et la série "Wishlist à supprimer" ne devrait pas exister
