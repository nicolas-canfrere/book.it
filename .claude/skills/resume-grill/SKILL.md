---
name: resume-grill
description: Use at the end of a grill-with-docs session, before switching to a new session for plan writing. Produces a synthesis of decisions and a code-volume estimate.
---

# Grill Session Summary

## Session Synthesis

Produce a structured summary of what was resolved during this session:

- **Termes résolus** — entrées nouvelles ou mises à jour dans CONTEXT.md
- **Décisions clés** — choix effectués avec la raison retenue
- **ADRs créés ou mis à jour** — liste par titre
- **Questions ouvertes** — points non résolus que le plan devra traiter

## Estimation du volume de code

Estime le périmètre d'implémentation :

- **Nouveaux fichiers** — nombre et type (entités domaine, repos, handlers, controllers, tests…)
- **Fichiers modifiés** — fichiers existants touchés et pourquoi
- **Signal de taille** — petit (< 5 fichiers), moyen (5–20 fichiers), large (> 20 fichiers)

"Je ne sais pas encore" est une réponse valide pour tout ce qui nécessite une exploration plus poussée.

## Note de passage de relais

Termine par une phrase que l'utilisateur peut coller comme contexte dans la prochaine session :

> "On a grillé [feature/concept]. Décisions clés : [X, Y, Z]. Périmètre estimé : [signal]. Questions ouvertes : [liste]."
