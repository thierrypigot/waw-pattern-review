# Changelog

Toutes les évolutions notables de l'extension sont consignées ici.

Format inspiré de [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/), numérotation [Semantic Versioning](https://semver.org/lang/fr/). Tant que la version reste en `0.x`, les filtres peuvent encore évoluer sur une version mineure.

## [Non publié]

## [0.2.0] - 2026-10-06

### Ajouté

- « Ouvrir dans un onglet » ouvre une vue plein fenêtre de l'écran de recette, sans menus ni barre d'administration : l'aperçu à la largeur choisie et une barre flottante qui reprend les réglages de l'écran (langue, en-tête et pied, bureau, tablette, mobile), avec le retour à la recette et un bouton pour la réduire. Les réglages suivent l'adresse de l'onglet. Les liens des déclinaisons dans « Validité des blocs » ouvrent la même vue.

## [0.1.0] - 2026-10-05

### Ajouté

- Page de recette virtuelle `/?waw_pattern_review=<composition>`, rendue dans le canevas du thème de blocs actif, réservée à `edit_theme_options`, non indexée.
- Déclinaisons par composition : « Par défaut » (la composition telle qu'insérée) puis les autres styles autorisés par l'extension WAW : sélecteur de styles, × largeur de la racine.
- Écran Apparence > Recette : aperçu bureau, tablette ou mobile au choix par les pictogrammes de l'aperçu de l'éditeur, mis à l'échelle, choix de la langue Polylang, en-tête et pied de page facultatifs.
- Contrôle de validité des blocs par `wp.blocks.parse()`, par composition et pour toutes (« Tout valider »).
- Route REST `waw-pattern-review/v1/variants`.
- Filtres `waw_pattern_review_patterns`, `waw_pattern_review_include_hidden`, `waw_pattern_review_styles`, `waw_pattern_review_alignments`.
