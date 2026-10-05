# WAW : recette des compositions

Extension WordPress de recette visuelle des compositions (patterns) d'un thème de blocs. Chaque composition est rendue à la volée, avec toutes ses déclinaisons, dans le gabarit du thème actif, en mobile, tablette et bureau, dans chaque langue, avec un contrôle de validité des blocs fait par le code de l'éditeur.

Aucune page n'est créée en base : la recette est toujours à jour du code du thème.

Développée par [WeAre[WP]](https://www.wearewp.pro/).

## Fonctionnalités

- **Écran Apparence > Recette** : liste des compositions et nombre de déclinaisons, aperçu en bureau (1 440 px), tablette (834 px) ou mobile (390 px), au choix par les pictogrammes de l'aperçu de l'éditeur, mis à l'échelle de l'écran ; choix de la langue (Polylang) ; en-tête et pied de page du thème affichables ou non ; ouverture dans un onglet.
- **Page de recette virtuelle** : `/?waw_pattern_review=<composition>` rend toutes les déclinaisons dans le gabarit du thème (canevas des thèmes de blocs). Réservée aux personnes qui peuvent modifier l'apparence (`edit_theme_options`), jamais indexée, jamais en cache.
- **Déclinaisons** : style de la racine × largeur de la racine (voir plus bas).
- **Validité des blocs** : le balisage de chaque déclinaison passe par `wp.blocks.parse()`, avec les blocs du cœur de la version de WordPress installée. « invalide » signifie que l'éditeur afficherait « Bloc invalide » ; « à vérifier » signale un bloc non disponible sur l'écran (bloc d'une extension). Bouton « Tout valider » pour toutes les compositions.

## Prérequis

- WordPress 7.1 ou supérieur, thème de blocs
- PHP 8.1 ou supérieur
- Facultatif : [WAW : sélecteur de styles](https://github.com/thierrypigot/waw-style-picker), version qui suit la 0.1.1 (« Par défaut » de composition), pour les styles par composition ; Polylang pour les langues

## Déclinaisons

| Axe | Valeurs par défaut |
|---|---|
| Style de la racine | « Par défaut » : la composition telle qu'insérée, avec son style livré. Si la composition limite ses styles avec `waw_style_picker_restrict_pattern_styles()` : « Par défaut », puis les autres styles autorisés (comme une voiture : la configuration de base, puis les options) |
| Largeur de la racine | Large et Pleine largeur si le bloc accepte les deux, que la largeur livrée est l'une d'elles et que la racine n'est pas verrouillée en `contentOnly` (l'éditeur y masque l'alignement). Sinon la largeur livrée |

Une composition sans styles déclarés n'a donc que sa version par défaut : déclarer les styles autorisés d'une composition est la façon d'enrichir sa recette.

Les compositions recettées sont celles du dossier `patterns/` du thème actif et de son parent, sauf celles masquées de l'outil d'insertion (`Inserter: false`).

## Filtres

| Filtre | Rôle | Arguments |
|---|---|---|
| `waw_pattern_review_patterns` | Liste des compositions recettées | `$patterns` (nom => données) |
| `waw_pattern_review_include_hidden` | Inclure les compositions `Inserter: false` (en-tête, pied de page…) | `$include` (`false`) |
| `waw_pattern_review_styles` | Styles de la racine à recetter (`''` : par défaut) | `$styles`, `$pattern`, `$root` |
| `waw_pattern_review_alignments` | Largeurs de la racine à recetter (`''` : largeur du contenu) | `$aligns`, `$pattern`, `$root` |

Exemple : un thème qui rend l'alignement aux groupes verrouillés le déclare pour la recette.

```php
add_filter( 'waw_pattern_review_alignments', function ( $aligns, $pattern, $root ) {
	if ( 'core/group' === $root['blockName'] && 'contentOnly' === ( $root['attrs']['templateLock'] ?? '' ) ) {
		return array( $root['attrs']['align'] ?? 'wide', 'wide', 'full' );
	}
	return $aligns;
}, 10, 3 );
```

## Page de recette

| Paramètre | Effet |
|---|---|
| `waw_pattern_review=<composition>` | Composition à afficher (obligatoire) |
| `waw_pr_variant=<n>` | Une seule déclinaison, numérotée à partir de 1 |
| `waw_pr_chrome=0` | Sans en-tête ni pied de page |
| `waw_pr_embed=1` | Sans barre d'administration, hauteur transmise à l'écran de recette |

Avec Polylang, la langue est celle de l'URL : `pll_home_url( 'en' )` suivi des paramètres.

## Fonctionnement

- Les fichiers de composition sont inclus directement à chaque rendu, dans une portée isolée : le registre de WordPress garde le contenu de la première inclusion, dans la langue de ce moment-là.
- Une déclinaison modifie les attributs du bloc racine et remplace sur place ses classes `is-style-*` et `align*` dans le balisage, sans doublon : le bloc reste valide.
- La page de recette passe par le canevas des thèmes de blocs (`template-canvas.php`) : styles globaux, styles par bloc, en-tête et pied de page du thème sont ceux d'une vraie page.

## Limites connues

- Le contrôle de validité ne connaît que les blocs du cœur : un bloc d'extension apparaît « à vérifier ». Il ne signale pas non plus un balisage valide que l'éditeur réécrirait à l'enregistrement (classe ou attribut superflu, par exemple) : pour ce contrôle strict, utiliser l'oracle G2 du plugin Claude `wearewp-fse`.
- Seule la racine de la composition est déclinée : un style porté par un bloc intérieur (une couverture dans une colonne) n'est pas parcouru.

## Développement

Pas de compilation : `assets/admin.js` est du JavaScript moderne qui utilise les globales `wp.*` déclarées en dépendances. Les pictogrammes Bureau / Tablette / Mobile sont les tracés de `@wordpress/icons` (`desktop`, `tablet`, `mobile`), repris dans `waw_pattern_review_devices()` : aucun script du cœur ne les expose en global.

| Fichier | Rôle |
|---|---|
| `waw-pattern-review.php` | Fichier principal |
| `includes/variants.php` | Compositions, déclinaisons, balisage |
| `includes/front.php` | Page de recette virtuelle |
| `includes/rest.php` | Route `waw-pattern-review/v1/variants` (balisage pour la validation) |
| `includes/admin.php` | Écran Apparence > Recette |
| `assets/admin.js`, `assets/admin.css` | Aperçus et validation |

## Licence

GPL-2.0-or-later.
