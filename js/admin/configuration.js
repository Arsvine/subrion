$(function()
{
	$('input[name^="param"], select[name^="param"]').on('change', function()
	{
		var id = $(this).attr('id');

		$("[data-id^='js-" + id + "']").hide();
		$('[data-id="js-' + id + '-' + $(this).val() + '"]').show();
	});

	$('.js-set-default').on('click', function()
	{
		var div = $(this).parent().parent().parent().get(0);
		$(div).removeClass('common').addClass('custom');
		$(div).find('.chck').val('0');
	});

	$('.js-set-custom').on('click', function()
	{
		var div = $(this).parent().parent().parent().get(0);
		$(div).removeClass('custom').addClass('common');
		$(div).find('.chck').val('1');
	});

	$('.item_val').dblclick(function()
	{
		var div = $(this).parent().parent().get(0);
		$(div).removeClass('custom').addClass('common');
		$(div).find('.chck').val('1');
	});


	// STYLE CHOOSER
	//---------------------------

	if ($('#sap_style').length > 0)
	{
		$('body').addClass('sap-style-transition');

		var $o = $('#sap_style');
		var $parent = $o.parent();
		var currentStyle = $o.val();
		var $currentStyleCSS = $('link[href$="bootstrap-' + currentStyle + '.css"]');
		var currentStyleLink = $currentStyleCSS.attr('href');

		$currentStyleCSS.attr('id', 'defaultStyle');

		var styles = {
			colors: {
				'calmy': 'background: #a2dadb; border: 8px solid #3d4c4f;',
				'emerald': 'background: #47c1a8; border: 8px solid #25272a;',
				'alizarin': 'background: #f6a440; border: 8px solid #432523;',
				'gebeus-waterfall': 'background: #38b7ea; border: 8px solid #1d1c24;',
				'roseus': 'background: #e45b9b; border: 8px solid #3d4049;',
				'radiant-orchid': 'background: #B163A3; border: 8px solid #3d4049;'
			},
			css: 'height: 34px; width: 34px; margin-right: 10px; display: inline-block;'
		};

		$.each(styles.colors, function(key, value)
		{
			$parent.append('<div class="sap-style-color ' + (currentStyle == key ? ' active' : '') + '" data-color="' + key + '" style="' + value + styles.css + '"></div>');

			var css = currentStyleLink.replace(currentStyle, key);

			$currentStyleCSS.before('<link rel="stylesheet" type="text/css" href="' + css + '" data-style="' + key + '">');
		});

		$('.sap-style-color', $parent).on('click', function()
		{
			if (!$(this).hasClass('active'))
			{
				$(this).addClass('active').siblings().removeClass('active');
				$o.val($(this).data('color'));

				// set new sap style
				$('#defaultStyle').attr('href', $('link[data-style="' + $(this).data('color') + '"]').attr('href'));

				// save sap style new configuration
				$.ajax(
				{
					data: {name: 'sap_style', value: $(this).data('color')},
					dataType: 'json',
					failure: function()
					{
						Ext.MessageBox.alert(_t('error'));
					},
					type: 'POST',
					url: intelli.config.admin_url + '/configuration/read.json?action=update',
					success: function(response)
					{
						if ('boolean' == typeof response.result && response.result)
						{
							intelli.notifFloatBox({msg: response.message, type: response.result ? 'notif' : 'error', autohide: true});
						}
					}
				});
			}
		});

		$('.sap-style-color', $parent).hover(function()
		{
			$('link[data-style="' + $(this).data('color') + '"]').clone().insertAfter('#defaultStyle').attr('id', 'currentStyle');
		}, function()
		{
			$('#currentStyle').remove();
		});
	}
});