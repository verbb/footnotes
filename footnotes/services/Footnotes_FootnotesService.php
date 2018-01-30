<?php

namespace Craft;

/**
 * The footnotes service provides methods for handling a set of footnotes.
 *
 * @package Craft
 */
class Footnotes_FootnotesService extends BaseApplicationComponent {

	/**
	 * @var string[]
	 */
	protected $footnotes;
	/**
	 * @var BaseModel
	 */
	protected $settings;

	/**
	 * Footnotes_FootnotesService constructor.
	 */
	public function __construct() {
		//	reset footnotes array
		$this->set();
		//	get plugin settings
		$this->settings = craft()->plugins->getPlugin('footnotes')->getSettings();
	}

	/**
	 * Sets the footnotes or resets them if no value given.
	 *
	 * @param string[] $footnotes
	 */
	public function set($footnotes = array()) {
		$this->footnotes = $footnotes;
	}

	/**
	 * Filters the given string and extracts all substrings
	 * within &lt;sup&gt; tags. Replaces those substrings with
	 * footnote indexes that reference to the corresponding
	 * (extracted) strings.
	 *
	 * For getting the strings these footnote indexes refer to
	 * use the get() method.
	 *
	 * @param string|RichTextData $string
	 *
	 * @return string
	 *
	 * @see get()
	 */
	public function filter($string) {
		//	check if given value is of type RichTextData
		if ($string instanceof RichTextData) {
			$string = $string->getParsedContent();
		}

		//	ensure the filter is used correctly
		if (!is_string($string)) {
			throw new \InvalidArgumentException('expected value of type string or RichtTextData but ' . (is_object($string) ? get_class($string) : gettype($string)) . ' given');
		}

		//	extract the contents of all occurences of <sup> tags
		preg_match_all('#<sup.*?>(.*?)</sup>#', $string, $matches);

		//	collect the footnotes and replace them with numbers
		$footnotesWithSup = reset($matches);
		$footnotes = next($matches);

		foreach ($footnotesWithSup as $key => $footnote) {
			$number = $this->add($footnotes[$key]);
			$replaceWith = $number;

			//	add anchor link
			if ($this->settings->enableAnchorLinks) {
				$replaceWith = '<a id="fnref:' . $number . '" href="#footnote-' . $number . '">' . $replaceWith . '</a>';
			}

			$replaceWith = '<sup>' . $replaceWith . '</sup>';
			$string = str_replace($footnote, $replaceWith, $string);
		}

		//	enable multiple, comma-separated footnotes
		//	like "<sup>2, 3</sup>" instead of having "<sup>2</sup><sup>3</sup>"

		//	therefore find all closing </sup> followed by opening <sup> tags (eventually divided by whitespaces)
		preg_match_all('#</sup>\s*<sup.*?>#', $string, $matches);
		$footnotesCloseAndOpen = reset($matches);

		//	iterate all found "</sup><sup>" (including those with whitespaces such as "</sup> <sup>" or even "</sup>  	 <sup>")
		foreach ($footnotesCloseAndOpen as $footnoteCloseAndOpen) {
			//	replace with just a comma
			$string = str_replace($footnoteCloseAndOpen, ', ', $string);
		}

		return $string;
	}

	/**
	 * Adds the given footnote text and returns its number.
	 *
	 * @param string $footnote
	 *
	 * @return int
	 */
	public function add($footnote) {
		//	check if given footnote already exists
		$key = array_search($footnote, $this->footnotes);

		//	add the new footnote
		if ($key === false) {
			$this->footnotes[] = $footnote;
			$key = array_search($footnote, $this->footnotes);
		}

		//	return the footnote's number (not the array index)
		return $key + 1;
	}

	/**
	 * Checks if any footnote exists.
	 *
	 * In other words: The method returns if the collection
	 * of footnotes is non-empty.
	 *
	 * @return bool
	 */
	public function exist() {
		return !empty($this->footnotes);
	}

	/**
	 * Returns all footnotes which could be collected by using
	 * the filter() method.
	 *
	 * The footnotes' array keys begin with 1.
	 *
	 * @return string[]
	 *
	 * @see filter()
	 */
	public function get() {
		$result = array();

		foreach ($this->footnotes as $key => $footnote) {
			$number = $key + 1;

			//	add anchor
			if ($this->settings->enableAnchorLinks) {
				$number = '<a name="footnote-' . $number . '">' . $number . '</a>';
			}

			$result[$number] = $footnote;
		}

		return $result;
	}
}
