# PK-Zone — gestion de parking

PK-Zone est une application web de gestion de parking pensée pour les petits parkings : réservation de place par les clients, suivi des véhicules en temps réel, tarification à l’heure et historique des paiements pour l’administrateur.

![Tableau de bord admin](docs/screenshots/admin.png)

## Fonctionnalités
**Client :** [inscription, réservation d'une place, suivi de sa session]
**Administrateur :** [tableau de bord, gestion des places, tarifs occasionnels, encaissement et libération, historique]

## Technologies
PHP 8.4, Symfony 8.1, Doctrine ORM, Twig, MySQL/MariaDB

## Audit et corrections
Après la première version de l'application, j'ai mené un audit de sécurité
et de fiabilité du code. Voici ce que j'ai trouvé et corrigé :

| Problème trouvé | Correction |
|---|---|
| Un client voyait et pouvait clôturer les sessions des autres | Relation session ↔ client, filtrage et contrôle de propriétaire côté serveur |
| Aucun jeton CSRF sur les formulaires | Jeton unique par action, vérifié côté serveur |
| Doubles réservations et doubles paiements possibles | Transaction avec verrou pessimiste (`SELECT ... FOR UPDATE`) |
| Le client se déclarait lui-même « payé » | Encaissement réservé à l'administrateur (route protégée) |
| Supprimer une place effaçait l'historique des paiements | Suppression refusée si la place a un historique |
| Connexion sans limite de tentatives | `login_throttling` + mot de passe de 8 caractères minimum |
| Migrations incomplètes (table `user` absente) | Migration ajoutée, schéma reconstruit depuis une base vide |
| Calcul du tarif copié à plusieurs endroits | Service `ParkingBillingService` unique |
| Validations uniquement côté navigateur | Validations côté serveur, fuseau horaire fixé |

## Installation locale
Prérequis : PHP ≥ 8.4, Composer, MySQL ou MariaDB.

```
git clone https://github.com/Brillando78/PK-Zone.git
cd PK-Zone
composer install
```

Crée un fichier `.env.local` avec ta connexion à la base. Adapte `serverVersion`
à ton serveur (par exemple `mariadb-10.4.32` pour XAMPP) :

```
DATABASE_URL="mysql://root:@127.0.0.1:3306/parking_management?serverVersion=8.0&charset=utf8mb4"
```

```
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console app:create-admin admin@example.com MotDePasse123
php bin/console app:init-parking
php -S 127.0.0.1:8000 -t public
```

Ouvre http://127.0.0.1:8000, puis connecte-toi avec le compte administrateur.

## Limites connues et pistes d'amélioration

### Limites actuelles

- **Paiement manuel** : l'encaissement est enregistré par l'administrateur au guichet. Il n'y a pas de paiement en ligne (mobile money, carte), et le mode de règlement n'est pas enregistré.
- **Un seul type de place** : le champ `type` existe, mais seul le type « standard » est proposé (pas de places électriques ou réservées aux personnes à mobilité réduite).
- **Tarification simple** : un tarif horaire unique, modifiable pour des dates précises. Toute heure entamée est due, avec un minimum d'une heure. Il n'y a pas de forfait journalier, d'abonnement ni de tarif par type de place.
- **Places non désactivables** : une place qui a déjà servi ne peut pas être supprimée, pour préserver l'historique, mais on ne peut pas non plus la déclarer « hors service ».
- **Pas d'annulation** : un client ne peut pas annuler une réservation faite par erreur. La clôture par l'administrateur facture au minimum une heure, même en cas d'erreur de saisie.
- **Une plaque, deux places** : le verrou en base empêche deux véhicules d'occuper la même place, mais pas qu'une même plaque soit réservée sur deux places au même instant (il faudrait une contrainte d'unicité).
- **Comptes** : pas de confirmation d'adresse e-mail ni de réinitialisation du mot de passe. Le message affiché après trop de tentatives de connexion est en anglais (message par défaut de Symfony).
- **Historique non paginé** : toutes les sessions terminées sont chargées d'un coup, ce qui ralentirait avec un gros volume de données.
- **Pas de tests automatiques** : les corrections ont été vérifiées manuellement.
- **Usage local** : le projet n'est pas déployé ni configuré pour la production (variables d'environnement, sauvegardes, HTTPS à prévoir).

### Pistes d'amélioration

1. **Tests automatiques** : accès refusé (403) aux sessions d'un autre client, protection contre les doublons, tests unitaires du calcul de tarif (`ParkingBillingService`).
2. **Désactivation des places** (champ `isActive`) à la place de la suppression.
3. **Paiement en ligne** par mobile money ou carte, avec confirmation avant clôture de la session.
4. **Contrainte d'unicité** sur la plaque des sessions actives.
5. **Statuts en enums PHP** (`active`, `completed`, `available`, `occupied`) à la place de chaînes de texte.
6. **Pagination et filtres de l'historique**, export CSV des revenus.
7. **Intégration continue** (GitHub Actions) pour lancer les tests et une analyse statique à chaque push.
8. **Reçus et notifications par e-mail**, réinitialisation du mot de passe.
9. **Rôles supplémentaires** (agent d'accueil / administrateur).