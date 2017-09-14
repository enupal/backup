(function($)
{
	/**
	 * EnupalBackup class
	 */
	var EnupalBackup = Garnish.Base.extend({

		options: null,
		modalError1: null,

		/**
		 * The constructor.
		 */
		init: function()
		{
			this.addListener($("#showError1"), 'activate', 'showError1');
		},

		showError1: function(option)
		{
			if (this.modalError1)
			{
				this.modalError1.show();
			}
			else
			{
				var $div = $('#error1');
				this.modalError1 = new Garnish.Modal($div);
			}
		},

	});

	window.EnupalBackup = EnupalBackup;

})(jQuery);