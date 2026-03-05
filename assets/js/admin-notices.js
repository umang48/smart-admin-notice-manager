jQuery(document).ready(function ($) {
	const container = $('<div id="toggadno-notices-container" class="wrap"></div>');
	$('.wrap > h1').after(container);

	const notices = [];
	const groups = {};

	// Collect all wrapped notices
	$('.toggadno-wrapper').each(function () {
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
	$.each(groups, function (slug, group) {
		const groupHtml = `
			<div class="toggadno-group" data-slug="${slug}">
				<div class="toggadno-group-header">
					<div class="toggadno-group-title">${group.name} (${group.notices.length} notices)</div>
					<div class="toggadno-group-toggle">
						<span class="dashicons dashicons-arrow-down-alt2"></span>
					</div>
				</div>
				<div class="toggadno-group-content">
					${group.notices.map(notice => `
						<div class="toggadno-notice-item" data-hash="${notice.hash}">
							${notice.content}
							<div class="toggadno-actions">
								<button class="toggadno-btn-snooze" data-hash="${notice.hash}">Remind me later (1 day)</button>
								<button class="toggadno-btn-dismiss" data-hash="${notice.hash}">Dismiss forever</button>
							</div>
						</div>
					`).join('')}
				</div>
			</div>
		`;
		container.append(groupHtml);
	});

	// Toggle group visibility
	$(document).on('click', '.toggadno-group-header', function () {
		$(this).siblings('.toggadno-group-content').toggleClass('open');
		$(this).find('.dashicons').toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
	});

	// Handle Snooze/Dismiss
	$(document).on('click', '.toggadno-btn-snooze, .toggadno-btn-dismiss', function (e) {
		e.preventDefault();
		const $btn = $(this);
		const hash = $btn.data('hash');
		const type = $btn.hasClass('toggadno-btn-snooze') ? 'snooze' : 'forever';

		$btn.text('Processing...');

		$.post(toggadnoData.ajaxurl, {
			action: 'toggadno_dismiss_notice',
			nonce: toggadnoData.nonce,
			hash: hash,
			type: type
		}, function (response) {
			if (response.success) {
				const $item = $btn.closest('.toggadno-notice-item');
				$item.slideUp(function () {
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
