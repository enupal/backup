(function($)
{
	/**
	 * EnupalBackup class
	 */
	var EnupalBackup = Garnish.Base.extend({

		options: null

		/**
		 * The constructor.
		 */
		init: function()
		{
			this.addListener($("#useCss"), 'activate', 'changeOptions');
		},

		changeOptions: function(option)
		{
			var option = option.currentTarget;
			var value = $(option).attr('aria-checked');
			var $select = $("#easing");
			$select.empty();

			if (value == 'true')
			{
				$.each(this.cssEasingOptions, function( index, value ) {
					$select.append('<option value="'+index+'">'+value+'</option>');
				});
			}
			else
			{
				$.each(this.easingOptions, function( index, value ) {
					$select.append('<option value="'+index+'">'+value+'</option>');
				});
			}
		},
	});

	window.EnupalBackup = EnupalBackup;

})(jQuery);