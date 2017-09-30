(function($)
{
	/**
	 * EnupalBackup class
	 */
	var EnupalBackup = Garnish.Base.extend({

		options: null,
		errorModal: null,
		logModal: null,

		/**
		 * The constructor.
		 */
		init: function()
		{
			this.addListener($("#showError1"), 'activate', 'showError');
			this.addListener($("#loginfo"), 'activate', 'showLoginfo');
		},

		showLoginfo: function(option)
		{
			if (this.logModal)
			{
				this.logModal.show();
			}
			else
			{
				var $div = $('#log1');
				this.logModal = new Garnish.Modal($div);
			}
		},

		showError: function(option)
		{
			if (this.errorModal)
			{
				this.errorModal.show();
			}
			else
			{
				var $div = $('#error1');
				this.errorModal = new Garnish.Modal($div);
			}
		},

	});

	window.EnupalBackup = EnupalBackup;

})(jQuery);