# Laravel Ratings

> Système d'évaluation et d'avis polymorphique pour applications Laravel

Un package Laravel complet pour gérer des évaluations par étoiles (1 à 5) avec avis textuels, calculs de moyenne, distribution des notes et support polymorphique.

---

## 📋 Table des matières

- [Fonctionnalités](#fonctionnalités)
- [Prérequis](#prérequis)
- [Installation](#installation)
- [Configuration](#configuration)
- [Utilisation](#utilisation)
  - [Créer une évaluation](#créer-une-évaluation)
  - [Modifier une évaluation](#modifier-une-évaluation)
  - [Supprimer une évaluation](#supprimer-une-évaluation)
  - [Vérifier une évaluation](#vérifier-une-évaluation)
  - [Récupérer les évaluations](#récupérer-les-évaluations)
  - [Statistiques et moyennes](#statistiques-et-moyennes)
  - [Filtrer par niveau](#filtrer-par-niveau)
- [Niveaux d'évaluation](#niveaux-dévaluation)
- [Référence de l'API](#référence-de-lapi)
- [Value Objects](#value-objects)
- [Structure de la base de données](#structure-de-la-base-de-données)
- [Tests](#tests)
- [Contribuer](#contribuer)
- [Licence](#licence)

---

## ✨ Fonctionnalités

- ✅ **Double polymorphisme** - Évaluez n'importe quel modèle avec n'importe quel utilisateur
- ✅ **5 niveaux d'évaluation** - ⭐ à ⭐⭐⭐⭐⭐ avec labels personnalisés
- ✅ **Avis textuels** - Commentaires optionnels associés aux notes
- ✅ **Calcul de moyenne** - Note moyenne automatique avec arrondi à 2 décimales
- ✅ **Distribution des notes** - Répartition des évaluations par niveau
- ✅ **Anti-doublon** - Un utilisateur ne peut pas évaluer deux fois le même objet
- ✅ **Pattern Repository** - Séparation propre de la logique d'accès aux données
- ✅ **Support des DTOs** - Objets de transfert de données typés
- ✅ **Value Objects** - DateTime, Métadonnées
- ✅ **Support des métadonnées** - Stockez des données supplémentaires au format JSON
- ✅ **Suppression douce** - Suppression sécurisée avec possibilité de restauration
- ✅ **Filtrage avancé** - Filtrez par note minimale, maximale, par auteur, par objet

---

## 🚀 Prérequis

- PHP 8.2 ou supérieur
- Laravel 12.0, 13.0, 14.0 ou 15.0

---

## 📦 Installation

Installez le package via Composer :

```bash
composer require andydefer/laravel-ratings
```

### Publier les migrations

```bash
php artisan vendor:publish --tag=ratings-migrations
```

### Exécuter les migrations

```bash
php artisan migrate
```

---

## ⚙️ Configuration

Le package est automatiquement découvert par Laravel. Aucune configuration supplémentaire n'est requise.

Si vous devez personnaliser le Service Provider, ajoutez-le manuellement dans `config/app.php` :

```php
'providers' => [
    // ...
    AndyDefer\LaravelRatings\RatingsServiceProvider::class,
],
```

---

## 📖 Utilisation

### Créer une évaluation

```php
use AndyDefer\LaravelRatings\Services\RatingService;
use AndyDefer\LaravelRatings\Enums\RatingLevel;

class ProductController extends Controller
{
    public function rate(RatingService $ratingService, Product $product)
    {
        $user = auth()->user();

        // Évaluer avec 5 étoiles et un avis
        $rating = $ratingService->rate(
            rater: $user,
            rateable: $product,
            rating: RatingLevel::FIVE,
            review: 'Excellent produit, je recommande vivement !'
        );

        return response()->json([
            'message' => 'Évaluation ajoutée avec succès',
            'rating' => $rating
        ]);
    }
}
```

### Modifier une évaluation

```php
public function updateRating(RatingService $ratingService, Product $product)
{
    $user = auth()->user();

    try {
        $updated = $ratingService->updateRating(
            rater: $user,
            rateable: $product,
            rating: RatingLevel::FOUR,
            review: 'Très bon produit, mais quelques petites améliorations possibles'
        );

        return response()->json([
            'message' => 'Évaluation mise à jour',
            'rating' => $updated
        ]);
    } catch (RuntimeException $e) {
        return response()->json(['error' => $e->getMessage()], 404);
    }
}
```

### Supprimer une évaluation

```php
public function deleteRating(RatingService $ratingService, Product $product)
{
    $user = auth()->user();

    try {
        $ratingService->deleteRating($user, $product);

        return response()->json([
            'message' => 'Évaluation supprimée avec succès'
        ]);
    } catch (RuntimeException $e) {
        return response()->json(['error' => $e->getMessage()], 404);
    }
}
```

### Vérifier une évaluation

```php
// Vérifier si l'utilisateur a déjà évalué
$hasRated = $ratingService->hasRated($user, $product);

// Récupérer l'évaluation spécifique
$rating = $ratingService->getRaterRating($user, $product);
```

### Récupérer les évaluations

```php
// Récupérer toutes les évaluations d'un produit
$ratings = $ratingService->getRatings($product);

// Récupérer toutes les évaluations d'un utilisateur
$userRatings = $ratingService->getRatingsByRater($user);

// Récupérer uniquement les bonnes notes (4⭐ et 5⭐)
$topRatings = $ratingService->getRatings(
    rateable: $product,
    minRating: RatingLevel::FOUR
);
```

### Statistiques et moyennes

```php
// Calculer la note moyenne
$average = $ratingService->getAverageRating($product); // 4.2

// Compter le nombre total d'évaluations
$total = $ratingService->countRatings($product); // 127

// Compter les évaluations par niveau
$fiveStars = $ratingService->countRatingsByLevel($product, RatingLevel::FIVE);

// Obtenir la distribution complète
$distribution = $ratingService->getRatingDistribution($product);
// [
//     1 => 2,
//     2 => 5,
//     3 => 10,
//     4 => 32,
//     5 => 78
// ]
```

### Filtrer par niveau

```php
// Récupérer les évaluations entre 3⭐ et 5⭐
$filtered = $ratingService->getRatings(
    rateable: $product,
    minRating: RatingLevel::THREE,
    maxRating: RatingLevel::FIVE
);

// Récupérer uniquement les notes supérieures à 3⭐
$goodRatings = $ratingService->getRatings(
    rateable: $product,
    minRating: RatingLevel::FOUR
);

// Récupérer uniquement les notes inférieures à 3⭐
$badRatings = $ratingService->getRatings(
    rateable: $product,
    maxRating: RatingLevel::THREE
);
```

---

## 🏷️ Niveaux d'évaluation

| Niveau | Valeur | Étoiles | Label |
|--------|--------|---------|-------|
| `RatingLevel::ONE` | `1` | ⭐ | Très mauvais |
| `RatingLevel::TWO` | `2` | ⭐⭐ | Mauvais |
| `RatingLevel::THREE` | `3` | ⭐⭐⭐ | Moyen |
| `RatingLevel::FOUR` | `4` | ⭐⭐⭐⭐ | Bien |
| `RatingLevel::FIVE` | `5` | ⭐⭐⭐⭐⭐ | Excellent |

### Utilisation des étoiles et labels

```php
use AndyDefer\LaravelRatings\Enums\RatingLevel;

$level = RatingLevel::FIVE;
echo $level->getStars();       // ⭐⭐⭐⭐⭐
echo $level->getLabel();       // Excellent
echo $level->getPercentage();  // 100%

$level = RatingLevel::THREE;
echo $level->getStars();       // ⭐⭐⭐
echo $level->getLabel();       // Moyen
echo $level->getPercentage();  // 60%
```

---

## 📚 Référence de l'API

### RatingService

| Méthode | Description | Retourne |
|---------|-------------|----------|
| `rate(Model $rater, Model $rateable, RatingLevel $rating, ?string $review)` | Crée une évaluation | `Model` |
| `updateRating(Model $rater, Model $rateable, RatingLevel $rating, ?string $review)` | Modifie une évaluation | `Model` |
| `deleteRating(Model $rater, Model $rateable)` | Supprime une évaluation | `void` |
| `hasRated(Model $rater, Model $rateable)` | Vérifie si une évaluation existe | `bool` |
| `getRaterRating(Model $rater, Model $rateable)` | Récupère l'évaluation d'un utilisateur | `?Model` |
| `getRatings(Model $rateable, ?RatingLevel $minRating, ?RatingLevel $maxRating)` | Récupère les évaluations d'un objet | `Collection` |
| `getRatingsByRater(Model $rater)` | Récupère les évaluations d'un utilisateur | `Collection` |
| `getAverageRating(Model $rateable)` | Calcule la note moyenne | `float` |
| `getRatingDistribution(Model $rateable)` | Obtient la distribution des notes | `array` |
| `countRatings(Model $rateable)` | Compte les évaluations | `int` |
| `countRatingsByLevel(Model $rateable, RatingLevel $level)` | Compte les évaluations par niveau | `int` |

### RatingRepository

| Méthode | Description | Retourne |
|---------|-------------|----------|
| `getAverageRating(Model $rateable)` | Calcule la note moyenne | `float` |
| `getRatingDistribution(Model $rateable)` | Obtient la distribution des notes | `array` |

---

## 🎯 Value Objects

Le package supporte les Value Objects suivants :

| Value Object | Description | Exemple |
|--------------|-------------|---------|
| `DateTimeVO` | Date/heure | `DateTimeVO::from('2024-01-01 12:00:00')` |
| `StrictDataObject` | Métadonnées typées | `StrictDataObject::from(['key' => 'value'])` |

### Accesseurs dans le modèle Rating

```php
$rating = Rating::find(1);

// ✅ Accès via les accesseurs Eloquent (propriétés directement)
$metadata = $rating->metadata;       // StrictDataObject|null
$ratingLevel = $rating->rating_level; // RatingLevel (via cast)
$review = $rating->review;           // string|null

// ✅ Relations
$rater = $rating->rater;          // Auteur (User, Admin, etc.)
$rateable = $rating->rateable;    // Objet évalué (Product, Service, etc.)

// ✅ Vérification des valeurs
if ($rating->created_at) {
    echo $rating->created_at->format('Y-m-d H:i:s');
}

if ($rating->metadata) {
    $orderId = $rating->metadata->get('order_id');
}
```

---

## 📝 Structure de la base de données

```sql
CREATE TABLE ratings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rater_type VARCHAR(255) NOT NULL,      -- Type de l'évaluateur
    rater_id BIGINT UNSIGNED NOT NULL,     -- ID de l'évaluateur
    rateable_type VARCHAR(255) NOT NULL,   -- Type de l'objet évalué
    rateable_id BIGINT UNSIGNED NOT NULL,  -- ID de l'objet évalué
    rating_level TINYINT NOT NULL,         -- 1, 2, 3, 4, 5
    review TEXT NULL,                      -- Avis textuel
    metadata JSON NULL,                    -- Métadonnées
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    
    UNIQUE INDEX idx_unique_rating (rater_type, rater_id, rateable_type, rateable_id),
    INDEX idx_rater (rater_type, rater_id),
    INDEX idx_rateable (rateable_type, rateable_id),
    INDEX idx_rating_level (rating_level)
);
```

---

## 🔍 Exemple complet

```php
use AndyDefer\LaravelRatings\Services\RatingService;
use AndyDefer\LaravelRatings\Enums\RatingLevel;
use Illuminate\Http\Request;

class ProductReviewController extends Controller
{
    public function __construct(
        private readonly RatingService $ratingService
    ) {}

    public function store(Request $request, Product $product)
    {
        $user = $request->user();

        // Vérifier si l'utilisateur a déjà acheté le produit
        if (!$user->hasPurchased($product)) {
            return response()->json([
                'error' => 'Vous devez acheter le produit pour donner un avis'
            ], 403);
        }

        // Vérifier si l'utilisateur a déjà donné un avis
        if ($this->ratingService->hasRated($user, $product)) {
            return response()->json([
                'error' => 'Vous avez déjà donné votre avis sur ce produit'
            ], 422);
        }

        try {
            $rating = $this->ratingService->rate(
                rater: $user,
                rateable: $product,
                rating: RatingLevel::from($request->input('rating')),
                review: $request->input('review')
            );

            return response()->json([
                'message' => 'Avis publié avec succès',
                'rating' => $rating,
                'average' => $this->ratingService->getAverageRating($product),
                'total' => $this->ratingService->countRatings($product)
            ], 201);

        } catch (RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function show(Product $product)
    {
        $distribution = $this->ratingService->getRatingDistribution($product);
        $total = $this->ratingService->countRatings($product);
        $average = $this->ratingService->getAverageRating($product);
        $ratings = $this->ratingService->getRatings($product);

        return response()->json([
            'average_rating' => $average,
            'total_reviews' => $total,
            'distribution' => $distribution,
            'reviews' => $ratings->map(function ($rating) {
                return [
                    'id' => $rating->id,
                    'user' => $rating->rater->name,
                    'rating' => $rating->rating_level->value,
                    'stars' => $rating->rating_level->getStars(),
                    'review' => $rating->review,
                    'created_at' => $rating->created_at?->format('Y-m-d H:i:s')
                ];
            })
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $user = $request->user();

        try {
            $updated = $this->ratingService->updateRating(
                rater: $user,
                rateable: $product,
                rating: RatingLevel::from($request->input('rating')),
                review: $request->input('review')
            );

            return response()->json([
                'message' => 'Avis mis à jour',
                'rating' => $updated
            ]);

        } catch (RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function destroy(Product $product)
    {
        $user = request()->user();

        try {
            $this->ratingService->deleteRating($user, $product);

            return response()->json([
                'message' => 'Avis supprimé avec succès'
            ]);

        } catch (RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function myReviews(Request $request)
    {
        $user = $request->user();
        $ratings = $this->ratingService->getRatingsByRater($user);

        return response()->json([
            'total' => $ratings->count(),
            'reviews' => $ratings
        ]);
    }

    public function stats(Product $product)
    {
        $distribution = $this->ratingService->getRatingDistribution($product);
        $total = $this->ratingService->countRatings($product);
        $average = $this->ratingService->getAverageRating($product);

        // Préparer les données pour l'affichage
        $formattedDistribution = [];
        foreach (RatingLevel::cases() as $level) {
            $formattedDistribution[$level->value] = [
                'label' => $level->getLabel(),
                'stars' => $level->getStars(),
                'count' => $distribution[$level->value] ?? 0,
                'percentage' => $total > 0 
                    ? round(($distribution[$level->value] ?? 0) / $total * 100, 1)
                    : 0
            ];
        }

        return response()->json([
            'product' => $product->name,
            'average_rating' => $average,
            'total_reviews' => $total,
            'distribution' => $formattedDistribution
        ]);
    }
}
```

---

## 🧪 Tests

### Exécuter les tests

```bash
composer test
```

### Exécuter uniquement les tests unitaires

```bash
composer test-unit
```

### Exécuter uniquement les tests d'intégration

```bash
composer test-integration
```

### Configuration des tests

Le package utilise `orchestra/testbench` pour les tests d'intégration avec une base de données SQLite en mémoire.

---

## 🔧 Développement

### Style de code

```bash
./vendor/bin/pint
```

### Analyse statique

```bash
./vendor/bin/phpstan analyse
./vendor/bin/psalm
```

---

## 🤝 Contribuer

Veuillez consulter [CONTRIBUTING](CONTRIBUTING.md) pour plus de détails.

### Flux de développement

1. Forkez le dépôt
2. Créez une branche de fonctionnalité (`git checkout -b feature/amazing-feature`)
3. Apportez vos modifications
4. Exécutez les tests (`composer test`)
5. Committez vos modifications (`git commit -m 'Ajouter une fonctionnalité géniale'`)
6. Poussez vers la branche (`git push origin feature/amazing-feature`)
7. Ouvrez une Pull Request

---

## 📦 Dépendances

- [`andydefer/php-vo`](https://github.com/andydefer/php-vo) - Value Objects
- [`andydefer/laravel-repository`](https://github.com/andydefer/laravel-repository) - Implémentation du pattern Repository
- [`andydefer/domain-structures`](https://github.com/andydefer/domain-structures) - Structures de domaine (AbstractRecord, AbstractData)

---

## 👨‍💻 Auteur

**Andy Kani**
- GitHub: [@andydefer](https://github.com/andydefer)
- Email: andykanidimbu@gmail.com

---

## 📄 Licence

Ce package est sous licence MIT. Voir le fichier [LICENSE](LICENSE) pour plus d'informations.

---

## ⭐ Support

Si vous trouvez ce package utile, n'hésitez pas à lui donner une ⭐ sur GitHub !

---

## 🙏 Remerciements

- Framework Laravel
- Tous les contributeurs et utilisateurs de ce package

---

**Construit avec ❤️ pour la communauté Laravel**
```

---

## 📋 Résumé des modifications

| Avant (obsolète) | Après (correct) |
|------------------|-----------------|
| `$rating->getCreatedAt()` | `$rating->created_at` |
| `$rating->getUpdatedAt()` | `$rating->updated_at` |
| `$rating->getDeletedAt()` | `$rating->deleted_at` |
| `$rating->getMetadata()` | `$rating->metadata` |
| `$rating->getRatingLevel()` | `$rating->rating_level` |
| `$rating->getReview()` | `$rating->review` |