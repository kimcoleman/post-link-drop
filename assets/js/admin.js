/**
 * Link Drop - Admin JavaScript
 *
 * @package Post_Link_Drop
 */

(function($) {
	'use strict';

	/**
	 * Quick link functionality.
	 */
	function initQuickLink() {
		$(document).on('click', '.ld-quick-link-btn', function(e) {
			e.preventDefault();

			var $wrap = $(this).closest('.ld-quick-link-wrap');
			var $input = $wrap.find('.ld-quick-link-input');
			var postId = $wrap.data('post-id');
			var url = $input.val().trim();

			if (!url) {
				$input.focus();
				return;
			}

			// Basic URL validation
			if (!url.match(/^https?:\/\//i)) {
				alert('Please enter a valid URL starting with http:// or https://');
				$input.focus();
				return;
			}

			$wrap.addClass('ld-loading');

			$.ajax({
				url: ldAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'pld_quick_link',
					nonce: ldAdmin.nonce,
					post_id: postId,
					url: url
				},
				success: function(response) {
					if (response.success) {
						// Replace the input with the linked URL
						$wrap.html('<a href="' + escapeHtml(response.data.url) + '" target="_blank">' + escapeHtml(truncateUrl(response.data.url)) + '</a>');

						// Update status column
						var $row = $wrap.closest('tr');
						$row.find('.column-pld_status').html('<span style="color:#00a32a;">Linked</span>');
					} else {
						alert(response.data.message || 'An error occurred.');
						$wrap.removeClass('ld-loading');
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
					$wrap.removeClass('ld-loading');
				}
			});
		});

		// Allow enter key to submit
		$(document).on('keypress', '.ld-quick-link-input', function(e) {
			if (e.which === 13) {
				e.preventDefault();
				$(this).siblings('.ld-quick-link-btn').click();
			}
		});
	}

	/**
	 * Status change functionality.
	 */
	function initStatusChange() {
		$(document).on('click', '.ld-status-btn', function(e) {
			e.preventDefault();

			var $btn = $(this);
			var $wrap = $btn.closest('.ld-action-buttons');
			var postId = $wrap.data('post-id');
			var newStatus = $btn.data('status');

			$wrap.addClass('ld-loading');

			$.ajax({
				url: ldAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'pld_set_status',
					nonce: ldAdmin.nonce,
					post_id: postId,
					status: newStatus
				},
				success: function(response) {
					if (response.success) {
						// Reload the page to reflect changes
						location.reload();
					} else {
						alert(response.data.message || 'An error occurred.');
						$wrap.removeClass('ld-loading');
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
					$wrap.removeClass('ld-loading');
				}
			});
		});
	}

	/**
	 * Helper: Escape HTML.
	 */
	function escapeHtml(text) {
		var div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	/**
	 * Helper: Truncate URL for display.
	 */
	function truncateUrl(url) {
		try {
			var parsed = new URL(url);
			var path = parsed.pathname;
			if (path.length > 25) {
				path = path.substring(0, 22) + '...';
			}
			return path || url;
		} catch (e) {
			return url.length > 25 ? url.substring(0, 22) + '...' : url;
		}
	}

	/**
	 * Initialize on document ready.
	 */
	$(document).ready(function() {
		initQuickLink();
		initStatusChange();
	});

})(jQuery);
