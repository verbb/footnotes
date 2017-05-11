if (!RedactorPlugins) var RedactorPlugins = {};

RedactorPlugins.footnotebutton = function () {

	return {
		langs: {
			en: {
				"footnote": 'Footnote'
			},
			de: {
				"footnote": 'Fußnote'
			}
			//	TODO: add other translations
		},

		init: function () {
			//	add button to toolbar
			var sup = this.button.add('footnote', this.lang.get('footnote'));

			//	add callback function for the button to be invoked on each click
			this.button.addCallback(sup, this.footnotebutton.formatSup);

			//	set icon (see also craft/app/resources/lib/redactor/redactor.css for all icon-classes)
			this.button.setIcon(sup, '<i class="re-icon-sup"></i>');
		},

		formatSup: function () {
			this.inline.format('sup');
		}

	};
};
