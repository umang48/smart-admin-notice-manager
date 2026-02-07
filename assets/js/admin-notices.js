jQuery(document).ready(function($) {
	const container = $('<div id="sanm-notices-container" class="wrap"></div>');
	$('.wrap > h1').after(container);

	const notices = [];
	const groups = {};

	// Collect all wrapped notices
	$('.sanm-wrapper').each(function() {
		const $wrapper = $(this);
		const sourceSlug = $wrapper.data('source-slug');
		const sourceName = $wrapper.data('source-name');
		const hash = $wrapper.data('hash');
		const content = $wrapper.html();

		// Remove original from DOM
		$wrapper.remove();

		if (!groups[sourceSlug]) {
			groups[sourceSlug] = {
				name: sourceName,
				notices: []
			};
		}

		groups[sourceSlug].notices.push({
			hash: hash,
			content: content
		});
	});

	// Render groups
	$.each(groups, function(slug, group) {
		const groupHtml = `
			<div class="sanm-group" data-slug="${slug}">
				<div class="sanm-group-header">
					<div class="sanm-group-title">${group.name} (${group.notices.length} notices)</div>
					<div class="sanm-group-toggle">
						<span class="dashicons dashicons-arrow-down-alt2"></span>
					</div>
				</div>
				<div class="sanm-group-content">
					${group.notices.map(notice => `
						<div class="sanm-notice-item" data-hash="${notice.hash}">
							${notice.content}
							<div class="sanm-actions">
								<button class="sanm-btn-snooze" data-hash="${notice.hash}">Remind me later (1 day)</button>
								<button class="sanm-btn-dismiss" data-hash="${notice.hash}">Dismiss forever</button>
							</div>
						</div>
					`).join('')}
				</div>
			</div>
		`;
		container.append(groupHtml);
	});

	// Toggle group visibility
	$(document).on('click', '.sanm-group-header', function() {
		$(this).siblings('.sanm-group-content').toggleClass('open');
		$(this).find('.dashicons').toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
	});

	// Handle Snooze/Dismiss
	$(document).on('click', '.sanm-btn-snooze, .sanm-btn-dismiss', function(e) {
		e.preventDefault();
		const $btn = $(this);
		const hash = $btn.data('hash');
		const type = $btn.hasClass('sanm-btn-snooze') ? 'snooze' : 'forever';

		$btn.text('Processing...');

		$.post(sanmdata.ajaxurl, {
			action: 'sanm_dismiss_notice',
			nonce: sanmdata.nonce,
			hash: hash,
			type: type
		}, function(response) {
			if (response.success) {
				const $item = $btn.closest('.sanm-notice-item');
				$item.slideUp(function() {
					$item.remove();
					// Update count or remove group if empty (TODO)
				});
			} else {
				alert('Error dismissing notice.');
				$btn.text('Retry');
			}
		});
	});
});
