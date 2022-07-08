
# Mentions Légales

>Le nom et les logos Strava sont tous protégés par les lois applicables en matière de marques, de droits d'auteurs et de propriété intellectuelle.
Ce plugin n'est pas une application officielle Strava. Il est compatible avec Strava au travers le l'API Strava (voir [https://developers.strava.com/](https://developers.strava.com/) )

Le plugin Strava de Jeedom a été développé de manière à être compatible avec Strava.
![graph1](./docs/assets/images/api_logo_cptblWith_strava_horiz_light.png)


***

# Description

Ce plugin permet d'associer Jeedom à Strava, et ainsi récupérer les activités de l'athlète.
L'objectif n'est pas de dupliquer les informations contenues dans Strava, mais plutôt de récupérer les informations pertinentes des activités, et de les consolider pour avoir une vue personnalisée.

>Par exemple, Strava propose par défaut un résumé hebdomadaire et annuelle pour trois sports : natation, vélo, et course à pied. Avec le plugin, il est possible d'avoir ce même résumé pour 37 sports différents !

>![37sports](./docs/assets/images/37sports.png)

Le plugin se base sur l'API Strava, qui propose 2 types de requêtes.
- Les requêtes dites 'pull', où le plugin va chercher les informations dans Strava.
- les requêtes dites 'push' quand Strava envoie une notification au plugin pour l'informer d'un changement dans Strava. C'est le cas par exemple quand une nouvelle activité est synchronisée entre votre montre et Strava, après une belle séance de vélo !

Grâce à ce plugin, vous serez en temps réel au courant de votre bilan sportif, avec pour chacune des activités sélectionnées:
- le nombre d’occurrences dans la semaine et dans l'année
- le cumul des kilomètres dans la semaine et dans l'année
- le cumul de dénivelé positif dans la semaine et dans l'année
- le cumul de temps dans la semaine et dans l'année,

Strava propose également un service d'analyse de vos performances, qui prend bien sûr en compte votre poids. Au travers du plugin, il est également possible de mettre votre poids à jour dans Strava, en utilisant par exemple les informations de votre balance connectée (merci à mmourcia pour l'idée !).
