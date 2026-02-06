# language: fr

Fonctionnalité: Liste de souhaits
  En tant qu'utilisateur connecté
  Je veux gérer ma liste de souhaits
  Afin de suivre les séries que je veux acheter

  Contexte:
    Étant donné je suis connecté

  Scénario: Voir la wishlist
    Étant donné une série wishlist "Série souhaitée" existe
    Et une série BD "Série possédée" existe
    Quand je vais sur la page wishlist
    Alors je devrais voir la série "Série souhaitée"
    Et je ne devrais pas voir la série "Série possédée"

  Scénario: Déplacer une série vers la bibliothèque
    Étant donné une série wishlist "À déplacer" existe
    Quand je déplace la série "À déplacer" vers la bibliothèque
    Alors je devrais être sur la page d'accueil
    Et la série "À déplacer" ne devrait pas être dans la wishlist
    Et la série "À déplacer" devrait avoir le statut "En cours d'achat"

  Scénario: Les séries de la bibliothèque ne sont pas visibles dans la wishlist
    Étant donné une série BD "Ma BD" existe
    Quand je vais sur la page wishlist
    Alors je ne devrais pas voir la série "Ma BD"
