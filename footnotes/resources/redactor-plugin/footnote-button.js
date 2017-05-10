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
			var sup = this.button.add('footnote', this.lang.get('footnote') + '<sup>123</sup>');

			//	add callback function for the button to be invoked on each click
			this.button.addCallback(sup, this.footnotebutton.formatSup);
		},

		formatSup: function () {
			this.inline.format('sup');
		}

	};
};
