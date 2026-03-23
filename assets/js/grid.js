/**
 * Post Link Drop Grid - Load More
 *
 * @package Post_Link_Drop
 */

( function () {
	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.pld-load-more' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				loadMore( button );
			} );
		} );
	} );

	function loadMore( button ) {
		if ( button.disabled ) {
			return;
		}

		var gridId = button.dataset.grid;
		var grid   = document.getElementById( gridId );

		if ( ! grid ) {
			return;
		}

		var page = parseInt( button.dataset.page, 10 ) + 1;

		var params = new URLSearchParams( {
			page:    page,
			count:   button.dataset.count,
			status:  button.dataset.status,
			orderby: button.dataset.orderby,
			order:   button.dataset.order,
			new_tab: button.dataset.newTab,
		} );

		button.disabled = true;
		button.classList.add( 'pld-load-more--loading' );

		fetch( pldGrid.restUrl + '?' + params.toString() )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( data ) {
				if ( data.html ) {
					grid.insertAdjacentHTML( 'beforeend', data.html );
					button.dataset.page = page;
				}

				if ( ! data.has_more ) {
					button.parentElement.remove();
				} else {
					button.disabled = false;
				}
			} )
			.catch( function () {
				button.disabled = false;
			} )
			.finally( function () {
				button.classList.remove( 'pld-load-more--loading' );
			} );
	}
} )();
