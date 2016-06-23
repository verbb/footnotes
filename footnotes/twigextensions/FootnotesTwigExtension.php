<?php

namespace Craft;

/**
 * FootnotesTwigExtension extends all Twig templates with custom filters and functions.
 *
 * E.g. this class adds the `footnotes` filter to Twig for collecting footnotes from
 * the filtered string as well as for replacing them with footnote numbers.
 *
 * Within a template just call:
 * ```
 * {{ any_string_value | footnotes }}
 * ```
 *
 * After collecting the footnotes they can be received by calling the `footnotes()` Twig
 * function which is also added by this class.
 *
 * Within a template just call:
 * ```
 * {% set footnotes = footnotes() %}
 * ```
 *
 * @package Craft
 */
class FootnotesTwigExtension extends \Twig_Extension {

	/**
	 * Returns the name of the extension.
	 *
	 * @return string The extension name
	 */
	public function getName() {
		return 'footnotes';
	}

	/**
	 * Returns a list of filters to add to the existing list.
	 *
	 * @return array An array of filters
	 */
	public function getFilters() {
		return array(
			'footnotes' => new \Twig_Filter_Method($this, 'filter_footnotes', array('is_safe' => array('html'))),
		);
	}

	/**
	 * Returns a list of functions to add to the existing list.
	 *
	 * @return array An array of functions
	 */
	public function getFunctions() {
		return array(
			'footnotes_exist' => new \Twig_Function_Method($this, 'footnotes_exist', array('is_safe' => array('html'))),
			'footnotes' => new \Twig_Function_Method($this, 'get_footnotes', array('is_safe' => array('html'))),
		);
	}

	/**
	 * Filters the given string for footnotes and replaces them with a number.
	 *
	 * @param string|null $value
	 *
	 * @return string
	 */
	public function filter_footnotes($value = null) {
		/** @var Footnotes_FootnotesService $footnotes */
		$footnotes = craft()->footnotes_footnotes;

		return $footnotes->filter($value);
	}

	/**
	 * Checks if any footnotes exist.
	 *
	 * @return bool
	 */
	public function footnotes_exist() {
		/** @var Footnotes_FootnotesService $footnotes */
		$footnotes = craft()->footnotes_footnotes;

		return $footnotes->exist();
	}

	/**
	 * Returns all footnotes.
	 *
	 * @return string[]
	 */
	public function get_footnotes() {
		/** @var Footnotes_FootnotesService $footnotes */
		$footnotes = craft()->footnotes_footnotes;

		return $footnotes->get();
	}
}
