# Changelog plugin strava


>**Mentions Légales**

>Le nom et les logos Strava sont tous protégés par les lois applicables en matière de marques, de droits d'auteurs et de propriété intellectuelle.
Ce plugin n'est pas une application officielle Strava. Il est compatible avec Strava au travers le l'API Strava (voir [https://developers.strava.com/](https://developers.strava.com/) )

Le plugin Strava de Jeedom a été développé de manière à être compatible avec Strava.
![graph1](../assets/images/api_logo_cptblWith_strava_horiz_light.png)



>**IMPORTANT**
>
>Pour rappel s'il n'y a pas d'information sur la mise à jour, c'est que celle-ci concerne uniquement de la mise à jour de documentation, de traduction ou de texte.

***

# 28/02/2022 (stable-1.0)
Version initiale, basee sur la version beta-0.5


# 24/02/2022 (beta-0.5)
Des modifications ont été apportées a la version beta, suite aux remarques de sagitaz. Merci a lui d'avoir fait progresser le plugin !

- Fixe concernant l'historique des informations (semaine) en cas de remise a zero, ou "refresh" sur un athlete.
- Fixe sur les unités (km au lieu de kms), et ceux-ci ne sont plus écrasés lors d'une sauvegarde s'ils ont été mis a jour.
- L'affichage des temps se fait au travers du widget d'affichage (Strava/stravaDuration) qui réalise un affichage heures:minutes:secondes au lieu de secondes comme auparavant.
- Fixe sur les autorisations 'Strava' suite aux mises a jour sécurité de jeedom 4.2.

# 14/10/2021 (beta-0.4)
- Mise à jour de la documentation, pour indiquer la mise en place d'une base de données, et également un conseil lors de l'autorisation accordée a Strava.
Si vous avez déjà installe le plugin Strava, alors, pour mettre a jour la base de données, il vous suffit d'aller dans chaque athlète et de cliquer sur "RaZ Statistiques". Sinon, la procédure d'installation est documentée, il suffit de suivre la séquence pour autorise jeedom a accéder a vos information de Strava.

# 01/02/2021 (beta-0.3)
- Mise à jour de la documentation, notamment concernant la création de l'application dans Strava. Ajout des accents dans la documentation en Français.
- Le poids est récupéré toutes les nuits depuis Strava, et mis a jour pour chaque athlète Strava. Il est mis a jour immédiatement si l'action 'Envoyer poids' est utilisée.
- Le bouton 'Ajouter une information' a été supprime dans la section 'Commandes' de l’athlète.
- Correction de typos, et ajout des accents dans les messages d'erreurs et les champs "information" créés. Pour bénéficier des changements, il faut :

1. décocher toutes les options de sports,
2. sauvegarder,
3. re-cocher tous les sports a surveiller,
4. sauvegarder à nouveau,
5. Il faut ensuite cliquer sur 'RAZ statistiques' pour recharger toutes les statistiques depuis Strava.  

# 25/01/2021

- Initiale Beta version
- Le lien entre le plugin et Strava se fait au moyen de l'API Strava (oauth2), et 'push notification' au travers de webhook.
- 37 sports sont peuvent être sélectionnés, afin d'avoir des cumuls a la semaine, et par an.
- possibilité d'avoir plusieurs athlètes avec le même plugin.

>**À savoir**
>
>le nombre de requêtes à l'API Strava est limité par application. Il est possible de faire 100 requêtes toutes les 15 minutes, 1000 requêtes par jour.
