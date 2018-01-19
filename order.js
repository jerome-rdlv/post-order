(function ($) {

	function initSortables(types)
	{
	    var $body = $('body');
		var selector = types.map(function (type) {
			return '.'+ type +' .wp-list-table';
		}).join(', ');
		var $sortables = $(selector);
		$sortables.each(function () {
			if ($sortables.find('tbody tr').length > 1) {
				var $containment = $sortables.find('tbody');
				$sortables.sortable({
					axis: 'y',
					items: 'tbody > tr',
					containment: $containment,
					tolerance: 'pointer',
					cursor: 'move',
					opacity: '.8',
					forcePlaceholderSize: true,
					helper: function (e, $tr) {
						var $originals = $tr.children();
						var $helper = $tr.clone();
						$helper.attr('id', '');
						$helper.children().each(function (index)
						{
							// Set helper cell sizes to match the original sizes
							$(this).width($originals.eq(index).width());
						});
						$helper.css({
							display: 'table',
							width: $tr.width()
						});
						return $helper;
					},
					start: function (e, ui) {
						ui.placeholder.html(ui.item.clone().html());
						var sort = $(this).sortable('instance');
						sort.containment[3] = $containment.offset().top + $containment.height() - ui.helper.height();
					},
					update: function (e, ui) {
						ui.item.css({
							'position': '',
							'display': ''
						});
						var order = [];
						ui.item.closest('tbody').find('input[name="post[]"],input[name="delete_tags[]"]').each(function () {
							order.push(this.value);
						});
						var data = {
                            action: 'update_order',
                            nonce: rdlv_order.update_order_nonce,
                            order: order
                        };
						if ($body.hasClass('edit-tags-php')) {
                            data.taxonomy = /taxonomy\-([^ $]+)/.exec($body.attr('class'))[1];
                        }
                        else {
                            data.post_type = /post\-type\-([^ $]+)/.exec($body.attr('class'))[1];
                        }
						$.ajax({
							url: rdlv_order.update_order_url,
							data: data
						});
					}
				});
			}
		});
	}
	
	initSortables(rdlv_order.types);

})(jQuery);