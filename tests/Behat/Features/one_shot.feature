# language: fr

# NOTE: Ces tests nécessitent JavaScript (@javascript) pour fonctionner correctement.
# Ils vérifient le comportement dynamique du formulaire qui masque/affiche
# la section tomes selon que la case one-shot est cochée ou non.
# Ces tests sont désactivés par défaut car ils nécessitent une configuration
# Selenium avancée avec une base de données partagée entre le driver BrowserKit et Chrome.

@javascript @wip
Fonctionnalité: Gestion des one-shots
  En tant qu'utilisateur connecté
  Je veux que la section tomes soit masquée pour les one-shots
  Afin de simplifier le formulaire

  Contexte:
    Étant donné je suis connecté

  Scénario: Cocher one-shot masque la section tomes
    Étant donné je suis sur la page de création d'une série
    Quand je coche la case one-shot
    Et j'attends 0.5 seconde
    Alors la section tomes ne devrait pas être visible

  Scénario: Décocher one-shot affiche la section tomes
    Étant donné je suis sur la page de création d'une série
    Quand je coche la case one-shot
    Et j'attends 0.5 seconde
    Et je décoche la case one-shot
    Et j'attends 0.5 seconde
    Alors la section tomes devrait être visible
