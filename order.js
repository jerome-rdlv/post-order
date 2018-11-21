(function ($, type) {

    var $body = $('body');
    var $table = $('.'+ type +' .wp-list-table');
    if ($table.find('tbody tr').length > 1) {
        
        // add drag handle
        $table.find('tr').prepend('<td class="handle"></td>');
        
        // add drag handle on added rows
        var tbody = $table.find('tbody').get(0);
        var observer = new MutationObserver(function (mutations, observer) {
            for (var i = 0; i < mutations.length; ++i) {
                if (mutations[i].type === 'childList' && mutations[i].target === tbody) {
                    for (var j = 0; j < mutations[i].addedNodes.length; ++j) {
                        console.log(mutations[i].addedNodes[j]);
                        var $node = $(mutations[i].addedNodes[j]);
                        if (!$node.find('> td.handle').length) {
                            $node.prepend('<td class="handle"></td>');
                        }
                    }
                }
            }
            if (mutations.type === 'childlist') {
                // console.log('child added', mutations);
            }
        });
        observer.observe(tbody, {
            childList: true
        });
        
        var $containment = $table.find('tbody');
        $table.sortable({
            axis: 'y',
            items: 'tbody > tr',
            handle: 'td.handle',
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
            stop: function (e, ui) {
                ui.item.css({
                    'position': '',
                    'display': ''
                });
            },
            update: function (e, ui) {
                var order = [];
                ui.item.closest('tbody').find('input[name="post[]"],input[name="delete_tags[]"]').each(function () {
                    order.push(this.value);
                });
                var data = {
                    action: rdlv_order.action,
                    nonce: rdlv_order.update_order_nonce,
                    order: order
                };
                if ($body.hasClass('edit-tags-php')) {
                    data.taxonomy = /taxonomy\-([^ $]+)/.exec($body.attr('class'))[1];
                }
                else {
                    data.post_type = /post\-type\-([^ $]+)/.exec($body.attr('class'))[1];
                }
                $.post({
                    url: rdlv_order.update_order_url,
                    data: data
                });
            }
        });
    }

})(jQuery, rdlv_order.type);