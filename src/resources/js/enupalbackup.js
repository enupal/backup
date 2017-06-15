(function($)
{
	/**
	 * EnupalSlider class
	 */
	var EnupalSlider = Garnish.Base.extend({

		cssEasingOptions: null,
		easingOptions: null,
		/**
		 * The constructor.
		 * @param - The easing options
		 */
		init: function(cssEasingOptions, easingOptions)
		{
			this.easingOptions = easingOptions;
			this.cssEasingOptions = cssEasingOptions;
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

	window.EnupalSlider = EnupalSlider;

})(jQuery);