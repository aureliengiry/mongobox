var addTumblr = addTumblr || {};

(function($)
{
	addTumblr.init = function()
	{
		this.slider = $('#tumblr');
		this.boutonShowForm = $('#btn-add-tumblr-show');
		this.divFormAdd = $('#tumblr-add-wall');
		this.buttonSubmit = $('#submit-form-tumblr-add');
		this.form = $('#ajax_form_tumblr_add');
		this.ajaxLoader = $('#ajax_loader_tumblr_add');
		this.formImage = $('#tumblr_image');
		this.formText = $('#tumblr_text');
		this.submitting = false;
		this.shown = false;

		// Call function
		this.observeShowForm();
		this.observeSubmitForm();
	};

	addTumblr.displayForm = function()
	{
		this.shown = true;
		this.divFormAdd.slideDown('fast');
		this.boutonShowForm.find('i').addClass('glyphicon glyphicon-chevron-up').removeClass('glyphicon glyphicon-chevron-down');
	};

	addTumblr.hideForm = function()
	{
		this.shown = false;
		this.divFormAdd.slideUp('fast', function()
		{
			if( addTumblr.submitting )
			{
				addTumblr.submitting = false;
				addTumblr.form[0].reset();
				$('.tag-item').remove();
				addTumblr.form.show();
				addTumblr.ajaxLoader.hide();
			}
		});
		this.boutonShowForm.find('i').removeClass('glyphicon glyphicon-chevron-up').addClass('glyphicon glyphicon-chevron-down');
	};

	addTumblr.observeShowForm = function()
	{
		this.boutonShowForm.bind('click', function(e)
		{
			( addTumblr.shown ) ? addTumblr.hideForm(): addTumblr.displayForm();
			$('.error-add-tumblr').remove();
		});
	};

	addTumblr.observeSubmitForm = function()
	{
		this.form.on('click', '#submit-form-tumblr-add', function(e)
		{
			e.preventDefault();

			addTumblr.form.find('.error-add-tumblr').remove();
			// Check image submit
			if( addTumblr.formImage.val() === '' || addTumblr.formImage.val() === addTumblr.formImage.attr('placeholder') )
			{
				addTumblr.formImage.focus();
				return false;
			}
			// Check text submit
			if( addTumblr.formText.val() === '' || addTumblr.formText.val() === addTumblr.formText.attr('placeholder') )
			{
				addTumblr.formText.focus();
				return false;
			}
			// Check tag choices
			if( $('#tumblr_tags div.tag-item'). length === 0 )
			{
				$('#tumblr_tags').parents('.span4:first').append('<div class="alert alert-danger error-add-tumblr">A tag must be added.</div>')
				return false;
			}
			// Check group choices
			if( $('#tumblr_groups input[type=checkbox]:checked').length === 0 )
			{
				$('#tumblr_groups').parents('.span4:first').append('<div class="alert alert-danger error-add-tumblr">A group must be selected.</div>')
				return false;
			}

			addTumblr.form.hide();
			addTumblr.ajaxLoader.show();

			if( addTumblr.submitting === true )
				return false;

			addTumblr.loadAjax();
		});
	};

	addTumblr.loadAjax = function()
	{
		this.submitting = true;
		$.ajax({
			type: 'POST',
			url: this.form.attr('action'),
			data: this.form.serialize(),
			dataType: 'json',
			success: function(data)
			{
				addTumblr.hideForm();
				// If not on homepage
				if( !data.showTumblr )
					return false;
				// If group added
				if( data.success )
				{
					// If 5 tumblr displayed, remove one to add another
					if( addTumblr.slider.find('li').length === 5 )
					{
						addTumblr.slider.find('li').last().fadeOut(300, function()
						{
							$(this).remove();
							addTumblr.displayNewTumblr(data.tumblrView);
						});
					} else
					{
						addTumblr.displayNewTumblr(data.tumblrView);
					}
				} else
				{
					addTumblr.slider.before('<div class="alert alert-danger error-add-tumblr">Vous avez ajouté un tumblr sans groupe, veuillez l\'éditer pour qu\'elle apparaisse sur le wall.</div>');
				}
			}
		});
	};

	addTumblr.displayNewTumblr = function(tumblrView)
	{
		addTumblr.slider.find('ul:first').prepend(tumblrView);
		// Reloading tumblr popover
		tumblr.loadPopover();
	};
})(jQuery);