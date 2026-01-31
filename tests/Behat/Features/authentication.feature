# language: fr

Fonctionnalité: Authentification
  En tant qu'utilisateur
  Je veux pouvoir me connecter et me déconnecter
  Afin d'accéder à ma bibliothèque

  Contexte:
    Étant donné un utilisateur "test@example.com" avec le mot de passe "password" existe

  Scénario: Connexion réussie
    Étant donné je suis sur la page de connexion
    Quand je me connecte avec "test@example.com" et "password"
    Alors je devrais être sur la page d'accueil

  Scénario: Connexion échouée avec mauvais mot de passe
    Étant donné je suis sur la page de connexion
    Quand je me connecte avec "test@example.com" et "mauvais_mot_de_passe"
    Alors je devrais être sur la page de connexion
    Et je devrais voir une erreur d'authentification

  Scénario: Accès protégé redirige vers la connexion
    Quand j'accède à la page "/" sans être connecté
    Alors je devrais être redirigé vers la page de connexion

  Scénario: Déconnexion
    Étant donné je suis connecté
    Quand je me déconnecte
    Alors je devrais être sur la page de connexion
