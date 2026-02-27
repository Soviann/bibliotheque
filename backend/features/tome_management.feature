# language: fr

# NOTE: Ces tests nécessitent JavaScript (@javascript) pour fonctionner correctement.
# Ils vérifient l'ajout dynamique de tomes via le contrôleur Stimulus.
# Ces tests sont désactivés par défaut car ils nécessitent une configuration
# Selenium avancée avec une base de données partagée entre le driver BrowserKit et Chrome.

@javascript @wip
Fonctionnalité: Gestion des tomes
  En tant qu'utilisateur connecté
  Je veux pouvoir gérer les tomes d'une série
  Afin de suivre ma collection de manière détaillée

  Contexte:
    Étant donné je suis connecté

  Scénario: Ajouter un tome avec numéro, titre et ISBN
    Étant donné je suis sur la page de création d'une série
    Quand je remplis le titre avec "Ma Série Test"
    Et je sélectionne le type "BD"
    Et je clique sur ajouter un tome
    Et j'attends 0.5 seconde
    Et je remplis le tome 1 avec le numéro 1
    Et je remplis le titre du tome 1 avec "Premier tome"
    Et je remplis l'ISBN du tome 1 avec "978-2-1234-5678-9"
    Et je coche le tome 1 comme acheté
    Et je soumets le formulaire
    Alors la série "Ma Série Test" devrait exister

  Scénario: Marquer un tome sur le NAS
    Étant donné je suis sur la page de création d'une série
    Quand je remplis le titre avec "Série avec NAS"
    Et je sélectionne le type "BD"
    Et je clique sur ajouter un tome
    Et j'attends 0.5 seconde
    Et je remplis le tome 1 avec le numéro 1
    Et je coche le tome 1 comme acheté
    Et je coche le tome 1 comme sur le NAS
    Et je soumets le formulaire
    Alors la série "Série avec NAS" devrait exister

  Scénario: Ajouter plusieurs tomes
    Étant donné je suis sur la page de création d'une série
    Quand je remplis le titre avec "Série Multi-Tomes"
    Et je sélectionne le type "Manga"
    Et je clique sur ajouter un tome
    Et j'attends 0.5 seconde
    Et je remplis le tome 1 avec le numéro 1
    Et je clique sur ajouter un tome
    Et j'attends 0.5 seconde
    Et je remplis le tome 2 avec le numéro 2
    Et je soumets le formulaire
    Alors la série "Série Multi-Tomes" devrait exister
