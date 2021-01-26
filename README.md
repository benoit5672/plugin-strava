# plugin-strava

# Mentions Legales

>Le nom et les logos Strava sont tous protégés par les lois applicables en matière de marques, de droits d'auteur et de propriété intellectuelle.
Ce plugin n'est pas une application officielle Strava. Il est compatible avec Strava au travers le l'API Strava (see [https://developers.strava.com/](https://developers.strava.com/) )

Le plugin Strava de jeedom a ete developpe de maniere a etre compatible avec Strava.
![graph1](./docs/assets/images/api_logo_cptblWith_strava_horiz_light.png)


***

# Description

Ce plugin permet d'associer Jeedom a Strava, et ainsi recuperer les activitees de l'athlete.
L'objectif n'est pas de dupliquer les informations contenues dans Strava, mais plutot de recuperer les informations pertinentes des activitees, et de les consolider pour avoir une vue personnalisee.

>Par exemple, Strava propose par defaut un resume hebdomadaire et annuelle pour trois sports : natation, velo, et course a pied. Avec le plugin, il est possible d'avoir ce meme resume pour 37 sports differents !

>![37sports](./docs/assets/images/37sports.png)

Le plugin se base sur l'API Strava, qui propose 2 types de requetes.
- Les requetes dites 'pull', ou le plugin va chercher les informations dans Strava. 
- les requetes dites 'push' quand Strava envoie une notification au plugin pour l'informer d'un changement dans Strava. C'est la cas par exemple quand une nouvelle activitees est synchronisee entre votre montre et Strava, apres une belle seance de velo !

Grace a ce plugin, vous serez en temps reel au courant de votre bilan sportif, avec pour chaune des activitees selectionnees:
- le nombre d'occurence dans la semaine et dans l'annee
- le cumul des kilometres dans la semaine et dans l'annee
- le cumul de denivelle positif dans la semaine et dans l'annee
- le cumul de temps dans la semaine et dans l'annee,

Strava propose egalement un service d'analyse de vos performances, qui prends bien sur en compte votre poids. Au travers du plugin, il est egalement possible de mettre votre poids a jour dans Strava, en utilisant par exemple les informations de votre balance connectee (merci a mmourcia pour l'idee !). 
