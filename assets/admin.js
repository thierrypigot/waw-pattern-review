/**
 * Écran de recette : aperçus mis à l'échelle et contrôle de validité.
 *
 * Aperçu : la page de recette (front) dans une iframe de largeur réelle
 * (bureau 1440, tablette 834, mobile 390 px, au choix par les pictogrammes de
 * l'aperçu de l'éditeur), réduite pour tenir dans l'écran ; la page transmet
 * sa hauteur par postMessage.
 *
 * Validité : le balisage de chaque déclinaison passe par wp.blocks.parse(),
 * c'est-à-dire le code de l'éditeur de la version de WordPress installée,
 * blocs du cœur enregistrés. Un bloc invalide est un bloc que l'éditeur
 * signalerait « Bloc invalide » à l'ouverture.
 *
 * Sans compilation : JavaScript moderne et globales wp.* déclarées en
 * dépendances (admin.php).
 */
( () => {
	const { apiFetch, blocks, blockLibrary, domReady } = wp;
	const { __, _n, sprintf } = wp.i18n;
	const config = window.wawPatternReview || {};

	// Largeurs et libellés fournis par PHP (waw_pattern_review_devices()).
	const VIEWPORTS = config.devices || {};
	const STORAGE_KEY = 'waw-pattern-review';

	/* ------------------------------------------------------------------ */
	/* Préférences (confort, jamais indispensables)                          */
	/* ------------------------------------------------------------------ */
	const loadPrefs = () => {
		try {
			return JSON.parse( window.localStorage.getItem( STORAGE_KEY ) ) || {};
		} catch {
			return {};
		}
	};
	const savePrefs = ( prefs ) => {
		try {
			window.localStorage.setItem( STORAGE_KEY, JSON.stringify( prefs ) );
		} catch {
			// Stockage indisponible : préférences non mémorisées.
		}
	};

	/* ------------------------------------------------------------------ */
	/* Aperçus                                                              */
	/* ------------------------------------------------------------------ */
	const state = { lang: Object.keys( config.urls || {} )[ 0 ] || '', view: 'desktop', chrome: true, ...loadPrefs() };
	if ( ! ( state.lang in ( config.urls || {} ) ) ) {
		state.lang = Object.keys( config.urls || {} )[ 0 ] || '';
	}
	if ( ! ( state.view in VIEWPORTS ) ) {
		state.view = 'desktop';
	}

	const previewUrl = ( extra = {} ) => {
		const url = new URL( config.urls[ state.lang ], window.location.origin );
		if ( ! state.chrome ) {
			url.searchParams.set( 'waw_pr_chrome', '0' );
		}
		Object.entries( extra ).forEach( ( [ key, value ] ) => url.searchParams.set( key, value ) );
		return url.toString();
	};

	let framesRoot;

	const layout = () => {
		framesRoot?.querySelectorAll( '.waw-pr__frame' ).forEach( ( frame ) => {
			const iframe = frame.querySelector( 'iframe' );
			const box = frame.querySelector( '.waw-pr__viewport' );
			const width = Number( iframe.dataset.width );
			const height = Number( iframe.dataset.height || 600 );
			const scale = Math.min( 1, box.clientWidth / width );
			iframe.style.transform = `scale(${ scale })`;
			box.style.height = `${ Math.ceil( height * scale ) }px`;
		} );
	};

	const renderFrames = () => {
		framesRoot.replaceChildren(
			...[ state.view ].map( ( key ) => {
				const { width, label } = VIEWPORTS[ key ];
				const figure = document.createElement( 'figure' );
				figure.className = 'waw-pr__frame';

				const box = document.createElement( 'div' );
				box.className = 'waw-pr__viewport';
				box.style.maxWidth = `${ width + 2 }px`; // + bordures
				const iframe = document.createElement( 'iframe' );
				iframe.src = previewUrl();
				iframe.title = sprintf( /* translators: %s: largeur d'écran. */ __( 'Aperçu %s', 'waw-pattern-review' ), label );
				iframe.dataset.width = String( width );
				iframe.style.width = `${ width }px`;
				iframe.loading = 'lazy';
				box.append( iframe );

				figure.append( box );
				return figure;
			} )
		);
		layout();

		const open = document.querySelector( '[data-control="open"]' );
		if ( open ) {
			const url = new URL( previewUrl() );
			url.searchParams.delete( 'waw_pr_embed' );
			open.href = url.toString();
		}
	};

	window.addEventListener( 'message', ( event ) => {
		if ( event.origin !== window.location.origin || 'waw-pattern-review:height' !== event.data?.type ) {
			return;
		}
		framesRoot?.querySelectorAll( 'iframe' ).forEach( ( iframe ) => {
			if ( iframe.contentWindow === event.source ) {
				iframe.dataset.height = String( event.data.height );
				iframe.style.height = `${ event.data.height }px`;
			}
		} );
		layout();
	} );

	/* ------------------------------------------------------------------ */
	/* Validité                                                             */
	/* ------------------------------------------------------------------ */
	let coreBlocksReady = false;
	const ensureCoreBlocks = () => {
		if ( ! coreBlocksReady ) {
			blockLibrary.registerCoreBlocks();
			coreBlocksReady = true;
		}
	};

	// Message d'une anomalie de validation (format printf de l'éditeur).
	const issueText = ( issue ) => {
		const [ format = '', ...args ] = issue?.args || [];
		let i = 0;
		return String( format )
			.replace( /%[sdo]/g, () => {
				const value = args[ i++ ];
				const text = 'object' === typeof value ? JSON.stringify( value ) : String( value );
				return text.length > 160 ? `${ text.slice( 0, 160 ) }…` : text;
			} )
			.trim();
	};

	const inspect = ( block, problems ) => {
		if ( 'core/missing' === block.name ) {
			problems.push( {
				level: 'warning',
				text: sprintf( /* translators: %s: nom du bloc. */ __( 'Bloc non disponible ici : %s', 'waw-pattern-review' ), block.attributes?.originalName || block.name ),
			} );
		} else if ( false === block.isValid ) {
			const details = ( block.validationIssues || [] ).map( issueText ).filter( Boolean );
			problems.push( {
				level: 'error',
				text: sprintf( /* translators: %s: nom du bloc. */ __( 'Bloc invalide : %s', 'waw-pattern-review' ), block.name ),
				details,
			} );
		}
		( block.innerBlocks || [] ).forEach( ( inner ) => inspect( inner, problems ) );
	};

	const checkVariant = ( markup ) => {
		const problems = [];
		blocks.parse( markup ).forEach( ( block ) => inspect( block, problems ) );
		const level = problems.some( ( p ) => 'error' === p.level ) ? 'error' : ( problems.length ? 'warning' : 'ok' );
		return { level, problems };
	};

	const LEVEL_TEXT = {
		ok: __( 'valide', 'waw-pattern-review' ),
		warning: __( 'à vérifier', 'waw-pattern-review' ),
		error: __( 'invalide', 'waw-pattern-review' ),
	};

	const worst = ( levels ) => ( levels.includes( 'error' ) ? 'error' : ( levels.includes( 'warning' ) ? 'warning' : 'ok' ) );

	const setBadge = ( slug, level ) => {
		const badge = document.querySelector( `[data-status-for="${ CSS.escape( slug ) }"]` );
		if ( badge ) {
			badge.className = `waw-pr__status is-${ level }`;
			badge.textContent = LEVEL_TEXT[ level ];
		}
	};

	const fetchVariants = ( slug ) =>
		apiFetch( { path: slug ? `/waw-pattern-review/v1/variants?slug=${ encodeURIComponent( slug ) }` : '/waw-pattern-review/v1/variants' } );

	const renderChecks = ( pattern ) => {
		const root = document.querySelector( '.waw-pr__checks' );
		const list = root.querySelector( 'ol' );
		list.replaceChildren();
		const levels = pattern.variants.map( ( variant, index ) => {
			const result = checkVariant( variant.markup );
			const item = document.createElement( 'li' );
			item.className = `is-${ result.level }`;

			const status = document.createElement( 'span' );
			status.className = `waw-pr__status is-${ result.level }`;
			status.textContent = LEVEL_TEXT[ result.level ];

			const link = document.createElement( 'a' );
			link.href = previewUrl( { waw_pr_variant: index + 1 } ).replace( /([?&])waw_pr_embed=1&?/, '$1' );
			link.target = '_blank';
			link.rel = 'noopener';
			link.textContent = variant.label;

			item.append( status, ' ', link );
			result.problems.forEach( ( problem ) => {
				const p = document.createElement( 'p' );
				p.textContent = [ problem.text, ...( problem.details || [] ) ].join( ' · ' );
				item.append( p );
			} );
			list.append( item );
			return result.level;
		} );

		// Résumé sur une ligne ; le détail ne s'ouvre seul qu'en cas de problème.
		const level = worst( levels );
		const failing = levels.filter( ( l ) => 'ok' !== l ).length;
		const summary = root.querySelector( '.waw-pr__checks-summary' );
		summary.className = `waw-pr__checks-summary waw-pr__status is-${ level }`;
		summary.textContent = failing
			? sprintf( /* translators: 1: déclinaisons à revoir, 2: total. */ _n( '%1$d sur %2$d à revoir', '%1$d sur %2$d à revoir', failing, 'waw-pattern-review' ), failing, levels.length )
			: sprintf( /* translators: %d: total. */ _n( '%d déclinaison valide', '%d déclinaisons valides', levels.length, 'waw-pattern-review' ), levels.length );
		root.open = 'ok' !== level;
		setBadge( pattern.slug, level );
	};

	const validateCurrent = async () => {
		ensureCoreBlocks();
		try {
			const [ pattern ] = await fetchVariants( config.current );
			if ( pattern ) {
				renderChecks( pattern );
			}
		} catch ( error ) {
			document.querySelector( '.waw-pr__checks-summary' ).textContent = error?.message || String( error );
		}
	};

	const validateAll = async ( button ) => {
		ensureCoreBlocks();
		const summary = document.querySelector( '.waw-pr__summary' );
		button.disabled = true;
		summary.textContent = __( 'Validation en cours…', 'waw-pattern-review' );
		try {
			const patterns = await fetchVariants( '' );
			let total = 0;
			let failing = 0;
			patterns.forEach( ( pattern ) => {
				const levels = pattern.variants.map( ( variant ) => checkVariant( variant.markup ).level );
				total += levels.length;
				failing += levels.filter( ( level ) => 'ok' !== level ).length;
				setBadge( pattern.slug, worst( levels ) );
			} );
			summary.textContent = failing
				? sprintf( /* translators: 1: déclinaisons à revoir, 2: total. */ _n( '%1$d déclinaison à revoir sur %2$d.', '%1$d déclinaisons à revoir sur %2$d.', failing, 'waw-pattern-review' ), failing, total )
				: sprintf( /* translators: %d: total. */ _n( '%d déclinaison, toutes valides.', '%d déclinaisons, toutes valides.', total, 'waw-pattern-review' ), total );
		} catch ( error ) {
			summary.textContent = error?.message || String( error );
		} finally {
			button.disabled = false;
		}
	};

	/* ------------------------------------------------------------------ */
	/* Démarrage                                                            */
	/* ------------------------------------------------------------------ */
	domReady( () => {
		framesRoot = document.querySelector( '.waw-pr__frames' );
		if ( ! framesRoot || ! config.urls ) {
			return;
		}

		const lang = document.querySelector( '[data-control="lang"]' );
		if ( lang ) {
			lang.value = state.lang;
			lang.addEventListener( 'change', () => {
				state.lang = lang.value;
				savePrefs( state );
				renderFrames();
			} );
		}

		const devices = document.querySelectorAll( '.waw-pr__devices [data-view]' );
		const pressDevice = () => devices.forEach( ( button ) => button.setAttribute( 'aria-pressed', String( button.dataset.view === state.view ) ) );
		devices.forEach( ( button ) => {
			button.addEventListener( 'click', () => {
				state.view = button.dataset.view;
				savePrefs( state );
				pressDevice();
				renderFrames();
			} );
		} );
		pressDevice();

		const chrome = document.querySelector( '[data-control="chrome"]' );
		chrome.checked = state.chrome;
		chrome.addEventListener( 'change', () => {
			state.chrome = chrome.checked;
			savePrefs( state );
			renderFrames();
		} );

		document.querySelector( '[data-action="validate-all"]' )?.addEventListener( 'click', ( event ) => validateAll( event.currentTarget ) );

		new ResizeObserver( layout ).observe( framesRoot );
		renderFrames();
		validateCurrent();
	} );
} )();
