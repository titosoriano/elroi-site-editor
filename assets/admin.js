/* ElRoi Site Editor (wp-admin): repeaters, image and gallery pickers. */
(function ($) {
	'use strict';

	var uid = 0;
	function newKey(prefix) { uid += 1; return prefix + Date.now().toString(36) + uid; }

	// Cloned markup carries the template's ids; give every field its own id again.
	function freshIds(root) {
		$(root).find('[id^="elroi-"]').each(function () {
			var old = this.id, id = 'elroi-' + newKey('x');
			this.id = id;
			$(root).find('label[for="' + old + '"]').attr('for', id);
			$(root).find('[aria-describedby="' + old + '-h"]').attr('aria-describedby', id + '-h');
			$(root).find('#' + old + '-h').attr('id', id + '-h');
		});
	}

	// Add: clone the repeater's own <template>, swapping its placeholder key (__A__, __B__ ...) for a new one.
	function addItem(rep) {
		var html = rep.children('template[data-elroi-template]')[0].innerHTML;
		var ph = (html.match(/__([A-Z])__/) || [])[0];
		if (ph) html = html.split(ph).join(newKey('n'));
		var item = $(html).filter('[data-elroi-item]');
		freshIds(item);
		rep.children('[data-elroi-items]').append(item);
		return item;
	}
	$(document).on('click', '[data-elroi-add]', function () {
		var item = addItem($(this).closest('[data-elroi-repeater]'));
		item.attr('open', true).find('input:not([type=hidden]), textarea, select').first().trigger('focus');
	});

	$(document).on('click', '[data-elroi-remove]', function () {
		var item = $(this).closest('[data-elroi-item]');
		var title = item.find('[data-elroi-title]').first().text();
		if (window.confirm('Remove "' + title + '"? It is deleted when you click Update.')) item.remove();
	});
	$(document).on('click', '[data-elroi-up]', function () {
		var item = $(this).closest('[data-elroi-item]');
		item.prev('[data-elroi-item]').before(item);
		$(this).trigger('focus');
	});
	$(document).on('click', '[data-elroi-down]', function () {
		var item = $(this).closest('[data-elroi-item]');
		item.next('[data-elroi-item]').after(item);
		$(this).trigger('focus');
	});

	// Keep the item's title in step with its title field.
	$(document).on('input', '[data-elroi-title-source]', function () {
		$(this).closest('[data-elroi-item]').find('[data-elroi-title]').first().text(this.value || '(untitled)');
	});

	// Put a chosen Media Library image into an image picker.
	function setImage(box, a) {
		var url = (a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url);
		box.find('[data-elroi-image-id]').val(a.id);
		box.find('[data-elroi-image-preview]').html($('<img alt="">').attr('src', url));
		box.find('[data-elroi-image-altwrap]').prop('hidden', false);
		box.find('[data-elroi-image-alt]').attr('name', 'elroi_alt[' + a.id + ']').val(a.alt || '').prop('required', true);
		box.find('[data-elroi-image-remove]').prop('hidden', false);
		box.find('[data-elroi-image-pick]').text('Change image');
	}

	$(document).on('click', '[data-elroi-image-pick]', function () {
		var box = $(this).closest('[data-elroi-image]');
		var frame = wp.media({ title: 'Choose image', button: { text: 'Use this image' }, library: { type: 'image' }, multiple: false });
		frame.on('select', function () { setImage(box, frame.state().get('selection').first().toJSON()); });
		frame.open();
	});
	$(document).on('click', '[data-elroi-image-remove]', function () {
		var box = $(this).closest('[data-elroi-image]');
		box.find('[data-elroi-image-id]').val('');
		box.find('[data-elroi-image-preview]').empty();
		box.find('[data-elroi-image-altwrap]').prop('hidden', true);
		box.find('[data-elroi-image-alt]').attr('name', '').prop('required', false);
		$(this).prop('hidden', true);
		box.find('[data-elroi-image-pick]').text('Choose image').trigger('focus');
	});

	// Several photos at once: each becomes a new item, with the photo in the named image field.
	$(document).on('click', '[data-elroi-add-images]', function () {
		var rep = $(this).closest('[data-elroi-repeater]');
		var key = $(this).attr('data-elroi-add-images');
		var frame = wp.media({ title: 'Add photos', button: { text: 'Add these photos' }, library: { type: 'image' }, multiple: 'add' });
		frame.on('select', function () {
			var first = null;
			frame.state().get('selection').each(function (m) {
				var item = addItem(rep);
				var box = item.find('[data-elroi-image]').filter(function () {
					var name = $(this).find('[data-elroi-image-id]').attr('name') || '';
					return name.slice(-(key.length + 2)) === '[' + key + ']';
				}).first();
				setImage(box.length ? box : item.find('[data-elroi-image]').first(), m.toJSON());
				first = first || item;
			});
			if (first) first.find('[data-elroi-image-alt]').trigger('focus');
		});
		frame.open();
	});

	// Gallery picker.
	$(document).on('click', '[data-elroi-gallery-pick]', function () {
		var box = $(this).closest('[data-elroi-gallery]');
		var ids = box.find('[data-elroi-gallery-ids]').val().split(',').filter(Boolean);
		var frame = wp.media({ title: 'Choose photos', button: { text: 'Use these photos' }, library: { type: 'image' }, multiple: 'add' });
		frame.on('open', function () {
			var sel = frame.state().get('selection');
			ids.forEach(function (id) { var a = wp.media.attachment(id); a.fetch(); sel.add(a); });
		});
		frame.on('select', function () {
			var chosen = frame.state().get('selection').toJSON();
			box.find('[data-elroi-gallery-ids]').val(chosen.map(function (a) { return a.id; }).join(','));
			var prev = box.find('[data-elroi-gallery-preview]').empty();
			chosen.forEach(function (a) {
				prev.append($('<img alt="">').attr('src', a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url));
			});
		});
		frame.open();
	});

	// A required field inside a closed panel cannot show its message: open the panel first.
	document.addEventListener('invalid', function (e) {
		var d = e.target.closest && e.target.closest('details');
		while (d) { d.open = true; d = d.parentElement.closest('details'); }
	}, true);
})(jQuery);
