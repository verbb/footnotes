<?php

namespace Craft;

/**
 * Plugin for enabling footnotes in rich text fields.
 *
 * @package Craft
 */
class FootnotesPlugin extends BasePlugin {

	/**
	 * Returns the plugin’s name.
	 *
	 * @return string The plugin’s name.
	 */
	public function getName() {
		return Craft::t('Footnotes');
	}

	/**
	 * Returns the plugin’s description.
	 *
	 * @return string|null The plugin’s description.
	 */
	public function getDescription() {
		return Craft::t('Enables footnotes for RichtText fields and Twig templates.');
	}

	/**
	 * Returns the plugin’s version number.
	 *
	 * @return string The plugin’s version number.
	 */
	public function getVersion() {
		return '1.0.0';
	}

	/**
	 * Returns the plugin developer’s name.
	 *
	 * @return string The plugin developer’s name.
	 */
	public function getDeveloper() {
		return 'Vierbeuter';
	}

	/**
	 * Returns the plugin developer’s URL.
	 *
	 * @return string The plugin developer’s URL.
	 */
	public function getDeveloperUrl() {
		return 'http://www.vierbeuter.de/';
	}

	/**
	 * Returns the plugin documentation’s URL.
	 *
	 * @return string|null The plugin documentation’s URL.
	 */
	public function getDocumentationUrl() {
		return 'https://github.com/Vierbeuter/craft-footnotes';
	}

	/**
	 * Initializes the plugin.
	 */
	public function init() {
		parent::init();

		if (craft()->request->isCpRequest() && craft()->userSession->isLoggedIn()) {
			craft()->templates->includeJsResource('footnotes/redactor-plugin/footnote-button.js');
		}
	}

	/**
	 * Adds Twig extensions.
	 *
	 * @return FootnotesTwigExtension
	 *
	 * @throws \Exception
	 */
	public function addTwigExtension() {
		Craft::import('plugins.footnotes.twigextensions.FootnotesTwigExtension');

		return new FootnotesTwigExtension();
	}
}
