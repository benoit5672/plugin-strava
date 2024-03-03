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

## 03/03/2024 stable 1.3

Cette version contient tous les developpements beta depuis la 1.0, soit beta-1.1, beta-1.2 et beta-1.3

## 14/22/2023 (beta 1.3)

Version basee sur la version beta-1.2

* **nouveau**: 13 nouveaux sports ont été ajoutés (Badminton, eVTT, Gravel, HIIT, VTT, Pickleball, Pilates, Racquetball, Squash, Tennis de table, Tennis,
Trail, Vélo couché (salle)

## 25/11/2022 (beta 1.2)

Version basée sur la version stable-1.1

* **nouveau**: il y a maintenant 8 compteurs globaux, qui sont toujours independant des sports selectionnés. En plus des compteurs quotidiens, heddomadaires et annuels, il y a maintenant un compteur mensuel.
* **nouveau**: dans la page de configuration de l'athlète, il y a maintenant la possibilité de choisir la granularité du suivi des activtités. On a la possibilité d'activer un suivi quotidien, hebdomadaire, mensuel et/ou annuel de chaque activité. Par defaut, les cases 'par semaine' et 'par an' sont selectionnés, ce qui correspond aux valeurs des versions précédentes. Une fois votre selection faite, vous devez 'Sauvegarder' l'athlète, et cliquer sur le bouton 'Rafraichir les données'.
* Correction d'un bug au redemarrage de jeedom, qui dupliquait les informations de l'athlète.
* Mis a jour de la page 'Commande' de l'athlète, pour reprendre le look&feel de jeedom 4.3, avec l'affichage des valeurs directement dans la table.

## 07/03/2020 (beta 1.1)

Version basée sur la version stable-1.0.
Toutes les informations de Strava se trouvant en base de données Jeedom, que le sport soit coché ou non dans la configuration
de l'athlète, j'ai revu quelques peu la gestion de l'athlète.
1. les sports configurés sont les sports pour lesquels vous voulez le detail des activités. Pour chaque sport coché, vous aurez le nombre de séances, le temps d'effort, et le dénivelé par semaine et par an.
2. **nouveau**: j'ai ajoute 6 compteurs "globaux", c'est a dire indépendant des sports sélectionnés dans la configuration de l'athlète.  

* Compteur du nombre d'activités par jour. Ce champ est historiser par défaut, mode de lissage "max", et garder pendant 1 an.
* Durée des efforts par jour. Ce champ est historiser par défaut, mode de lissage "max", et garder pendant 1 an.
* Cumul du nombre d'activités pour la semaine en cours.
* Cumul de la durée des efforts de la semaine en cours.
* Cumul du nombre d'activités pour l’année en cours.
* Cumul de la durée des efforts de l’année en cours.
Ces nouveaux compteurs permettent de "surveiller" uniquement les activités les plus courantes, mais d'avoir en meme temps des compteurs d’activités plus global. Par exemple, j'ai fait de manière très occasionnel des raquettes a neige cet hiver, j'ai envie de voir apparaître ces sorties dans mes compteurs annuels, sans toutefois surveiller l’activité 'Raquettes'

Dans la configuration de l'athlète, j'ai ajoute un bouton "Rafraîchir les données".

* **nouveau** Rafraîchir les données: utilise la base de données Jeedom pour rafraîchir toutes les informations de l'athlète. Cela est utile notamment quand vous ajoutez ou supprimez des sports a "surveiller".
* Forcer la mise a jour: si les informations du widget ne vous semble pas a jour, alors vous pouvez cliquer sur ce bouton. Jeedom va chercher a "completer" les données manquantes depuis la dernière mise a jour, et rafraîchir les données en utilisant les derniers informations de la base de données Jeedom.
* RaZ statistiques: doit être fait lors de la creation d'un athlète, pour récupérer l'historique depuis Strava. Cela peut également
être utilisé pour effacer toutes les informations de la base de données Jeedom pour cet athlete, puis récupérer l'ensemble des données de l'athlète depuis Strava, avant de rafraîchir les informations en utilisant la base de données Jeedom.

## 28/02/2022 (stable-1.0)

Version initiale, basee sur la version beta-0.5

## 24/02/2022 (beta-0.5)

Des modifications ont été apportées a la version beta, suite aux remarques de sagitaz. Merci a lui d'avoir fait progresser le plugin !

* Fixe concernant l'historique des informations (semaine) en cas de remise a zero, ou "refresh" sur un athlete.
* Fixe sur les unités (km au lieu de kms), et ceux-ci ne sont plus écrasés lors d'une sauvegarde s'ils ont été mis a jour.
* L'affichage des temps se fait au travers du widget d'affichage (Strava/stravaDuration) qui réalise un affichage heures:minutes:secondes au lieu de secondes comme auparavant.
* Fixe sur les autorisations 'Strava' suite aux mises a jour sécurité de jeedom 4.2.

## 14/10/2021 (beta-0.4)

* Mise à jour de la documentation, pour indiquer la mise en place d'une base de données, et également un conseil lors de l'autorisation accordée a Strava.
Si vous avez déjà installe le plugin Strava, alors, pour mettre a jour la base de données, il vous suffit d'aller dans chaque athlète et de cliquer sur "RaZ Statistiques". Sinon, la procédure d'installation est documentée, il suffit de suivre la séquence pour autorise jeedom a accéder a vos information de Strava.

## 01/02/2021 (beta-0.3)

* Mise à jour de la documentation, notamment concernant la création de l'application dans Strava. Ajout des accents dans la documentation en Français.
* Le poids est récupéré toutes les nuits depuis Strava, et mis a jour pour chaque athlète Strava. Il est mis a jour immédiatement si l'action 'Envoyer poids' est utilisée.
* Le bouton 'Ajouter une information' a été supprime dans la section 'Commandes' de l’athlète.
* Correction de typos, et ajout des accents dans les messages d'erreurs et les champs "information" créés. Pour bénéficier des changements, il faut :

1. décocher toutes les options de sports,
2. sauvegarder,
3. re-cocher tous les sports a surveiller,
4. sauvegarder à nouveau,
5. Il faut ensuite cliquer sur 'RAZ statistiques' pour recharger toutes les statistiques depuis Strava.  

## 25/01/2021

* Initiale Beta version
* Le lien entre le plugin et Strava se fait au moyen de l'API Strava (oauth2), et 'push notification' au travers de webhook.
* 37 sports sont peuvent être sélectionnés, afin d'avoir des cumuls a la semaine, et par an.
* possibilité d'avoir plusieurs athlètes avec le même plugin.

>**À savoir**
>
>le nombre de requêtes à l'API Strava est limité par application. Il est possible de faire 100 requêtes toutes les 15 minutes, 1000 requêtes par jour.
