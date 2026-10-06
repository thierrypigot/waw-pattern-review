/**
 * Vue « onglet » de l'écran de recette : la page de recette en plein écran,
 * à la largeur choisie, réglée depuis une barre flottante.
 *
 * Les réglages (langue, en-tête et pied, largeur) suivent l'adresse de
 * l'onglet : un rechargement ou un lien copié rouvre le même aperçu.
 */
( () => {
	const config = window.wawPatternReviewTab || {};
	const devices = config.devices || {};
	const state = { ...config.state };

	const root = document.querySelector( '.waw-pr-tab' );
	if ( ! root ) {
		return;
	}
	const frame = root.querySelector( '[data-control="frame"]' );
	const bar = root.querySelector( '.waw-pr-tab__bar' );

	const frameUrl = () => {
		const url = new URL( config.urls[ state.lang ], window.location.origin );
		if ( ! state.chrome ) {
			url.searchParams.set( 'waw_pr_chrome', '0' );
		}
		if ( state.variant ) {
			url.searchParams.set( 'waw_pr_variant', String( state.variant ) );
		}
		return url.toString();
	};

	const syncAddress = () => {
		const url = new URL( window.location.href );
		url.searchParams.set( 'lang', state.lang );
		url.searchParams.set( 'device', state.view );
		url.searchParams.set( 'chrome', state.chrome ? '1' : '0' );
		window.history.replaceState( null, '', url );
	};

	const load = () => {
		frame.src = frameUrl();
		syncAddress();
	};

	const resize = () => {
		root.dataset.view = state.view;
		frame.style.width = 'desktop' === state.view ? '100%' : `${ devices[ state.view ]?.width || 0 }px`;
		root.querySelectorAll( '[data-view]' ).forEach( ( button ) => button.setAttribute( 'aria-pressed', String( button.dataset.view === state.view ) ) );
		syncAddress();
	};

	const lang = root.querySelector( '[data-control="lang"]' );
	if ( lang ) {
		lang.value = state.lang;
		lang.addEventListener( 'change', () => {
			state.lang = lang.value;
			load();
		} );
	}

	const chrome = root.querySelector( '[data-control="chrome"]' );
	chrome.checked = state.chrome;
	chrome.addEventListener( 'change', () => {
		state.chrome = chrome.checked;
		load();
	} );

	root.querySelectorAll( '[data-view]' ).forEach( ( button ) =>
		button.addEventListener( 'click', () => {
			state.view = button.dataset.view;
			resize();
		} )
	);

	// Barre réduite à son seul bouton, pour voir le bas de la page.
	const toggle = root.querySelector( '[data-control="toggle"]' );
	toggle.addEventListener( 'click', () => {
		const expanded = 'true' !== toggle.getAttribute( 'aria-expanded' );
		const label = expanded ? toggle.dataset.labelCollapse : toggle.dataset.labelExpand;
		toggle.setAttribute( 'aria-expanded', String( expanded ) );
		toggle.setAttribute( 'aria-label', label );
		toggle.title = label;
		root.classList.toggle( 'is-collapsed', ! expanded );
	} );

	// L'aperçu s'arrête au-dessus de la barre, quelle que soit sa hauteur.
	new ResizeObserver( () => root.style.setProperty( '--waw-pr-bar', `${ Math.ceil( bar.getBoundingClientRect().height ) }px` ) ).observe( bar );

	resize();
	load();
} )();
