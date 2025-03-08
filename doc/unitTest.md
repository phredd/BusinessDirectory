# Guide d'exécution des tests unitaires

Ce document explique comment exécuter les tests unitaires pour le projet d'annuaire d'entreprises.

## Tests PHP (Backend)

### Prérequis

- PHP 8.0+
- Composer
- Une instance MariaDB/MySQL pour les tests

### Installation

```bash
# Installer les dépendances incluant PHPUnit
composer install
```

### Configuration

Assurez-vous que la configuration de la base de données de test est correcte dans `phpunit.xml` et `tests/bootstrap.php`.

### Exécution des tests

Pour exécuter tous les tests PHP:

```bash
./vendor/bin/phpunit
```

Pour exécuter une suite de tests spécifique:

```bash
./vendor/bin/phpunit --testsuite Unit
./vendor/bin/phpunit --testsuite Integration
```

Pour exécuter un test spécifique:

```bash
./vendor/bin/phpunit tests/Unit/DatabaseConnectionTest.php
```

## Tests JavaScript (Frontend)

### Prérequis

- Node.js (version 14+)
- npm ou yarn

### Installation

```bash
# Installer les dépendances incluant Jest
npm install

# Pour les utilisateurs de yarn
yarn
```

### Configuration supplémentaire

Pour exécuter les tests frontend, vous devez installer les dépendances suivantes:

```bash
npm install --save-dev jest @testing-library/jest-dom babel-jest @babel/preset-env jsdom
```

Créez aussi un fichier babel.config.js à la racine:

```js
module.exports = {
  presets: [
    ['@babel/preset-env', {targets: {node: 'current'}}]
  ],
};
```

### Exécution des tests

Pour exécuter tous les tests JavaScript:

```bash
npm test

# Pour les utilisateurs de yarn
yarn test
```

Pour exécuter un test spécifique:

```bash
npx jest tests/frontend/main.test.js
```

Pour exécuter les tests avec la couverture de code:

```bash
npx jest --coverage
```

## Intégration continue

Si vous utilisez un service d'intégration continue (CI), vous pouvez configurer vos tests pour s'exécuter automatiquement à chaque push ou pull request. Par exemple, avec GitHub Actions, vous pouvez créer un fichier `.github/workflows/tests.yml` avec le contenu suivant:

```yaml
name: Tests

on:
  push:
    branches: [ main ]
  pull_request:
    branches: [ main ]

jobs:
  php-tests:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:5.7
        env:
          MYSQL_ALLOW_EMPTY_PASSWORD: yes
          MYSQL_DATABASE: annuaire_test
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3
    
    steps:
    - uses: actions/checkout@v2
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.0'
        extensions: mbstring, pdo, pdo_mysql
    
    - name: Install dependencies
      run: composer install --prefer-dist --no-progress
    
    - name: Run tests
      run: ./vendor/bin/phpunit
      
  js-tests:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v2
    
    - name: Setup Node.js
      uses: actions/setup-node@v2
      with:
        node-version: '14'
    
    - name: Install dependencies
      run: npm ci
    
    - name: Run tests
      run: npm test
```

## Bonnes pratiques

1. **Maintenez à jour vos tests**: Mettez à jour vos tests chaque fois que vous modifiez le code.
2. **Utilisez des données de test isolées**: Assurez-vous que les tests n'interfèrent pas entre eux.
3. **Suivez la couverture de code**: Visez une couverture de code d'au moins 80%.
4. **Testez les cas limites**: N'oubliez pas de tester les cas d'erreur et les cas limites.
5. **Mock les dépendances externes**: Utilisez des mocks pour les API externes, bases de données, etc.

En suivant ces instructions, vous pourrez maintenir une suite de tests robuste qui vous aidera à garantir la qualité de votre code.
