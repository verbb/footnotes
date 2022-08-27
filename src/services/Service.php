<?php
namespace verbb\footnotes\services;

use verbb\footnotes\Footnotes;

use craft\base\Component;
use craft\base\Model;
use craft\helpers\Html;

use craft\redactor\FieldData;

use InvalidArgumentException;

class Service extends Component
{
    // Properties
    // =========================================================================

    /**
     * @var string[]
     */
    protected $footnotes;

    /**
     * @var Model
     */
    protected $settings;


    // Public Methods
    // =========================================================================

    public function init(): void
    {
        // Reset footnotes array
        $this->set();

        // Get plugin settings
        $this->settings = Footnotes::$plugin->getSettings();
    }

    /**
     * Sets the footnotes or resets them if no value given.
     *
     * @param string[] $footnotes
     */
    public function set(array $footnotes = []): void
    {
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
     * @param string|FieldData $string
     *
     * @return string
     *
     * @see get()
     */
    public function filter($string, array $options = []): string
    {
        //  check if given value is a Redactor field's data (containing the markup )
        if ($string instanceof FieldData) {
            $string = $string->getParsedContent();
        }

        //  empty fields return NULL instead of an empty string --> nothing to do for us here, therefore just return an empty string
        if (empty($string)) {
            return '';
        }

        //  ensure the filter is used correctly
        if (!is_string($string)) {
            throw new InvalidArgumentException('expected value of type string or ' . FieldData::class . ', but ' . (is_object($string) ? get_class($string) : gettype($string)) . ' given');
        }

        //  extract the contents of all occurrences of <sup> tags
        preg_match_all('#<sup class="footnote".*?>(.*?)</sup>#', $string, $matches);

        //  collect the footnotes and replace them with numbers
        $footnotesWithSup = reset($matches);
        $footnotes = next($matches);

        foreach ($footnotesWithSup as $key => $footnote) {
            $number = $this->add($footnotes[$key]);
            $replaceWith = $number;

            //  add anchor link
            if ($this->settings->enableAnchorLinks) {
                $anchorAttributes = $options['anchorAttributes'] ?? [];
                $anchorAttrs = array_merge_recursive($anchorAttributes, ['id' => 'fnref:' . $number, 'href' => '#footnote-' . $number]);
                
                $replaceWith = Html::tag('a', $replaceWith, $anchorAttributes);
            }

            $superscriptAttributes = $options['superscriptAttributes'] ?? [];
            $superscriptAttrs = array_merge_recursive($superscriptAttributes, ['class' => 'footnote']);

            $replaceWith = Html::tag('sup', $replaceWith, $superscriptAttrs);

            //  check if "duplicate footnotes" feature is enabled to search'n'replace differently
            if ($this->settings->enableDuplicateFootnotes) {
                //  replace first footnote only (ignore any other identical ones)
                $string = substr_replace($string, $replaceWith, strpos($string, $footnote), strlen($footnote));
            } else {
                //  replace all footnotes of same text
                $string = str_replace($footnote, $replaceWith, $string);
            }
        }

        //  enable multiple, comma-separated footnotes
        //  like "<sup>2, 3</sup>" instead of having "<sup>2</sup><sup>3</sup>"

        //  therefore find all closing </sup> followed by opening <sup> tags (eventually divided by whitespaces)
        preg_match_all('#</sup>\s*<sup class="footnote".*?>#', $string, $matches);
        $footnotesCloseAndOpen = reset($matches);

        //  iterate all found "</sup><sup>" (including those with whitespaces such as "</sup> <sup>" or even "</sup>  	 <sup>")
        foreach ($footnotesCloseAndOpen as $footnoteCloseAndOpen) {
            //  replace with just a comma
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
    public function add(string $footnote): int
    {
        //  just add the new footnote in case of "duplicate footnotes" feature is activated insteadof searching for any existing footnote of same content added before
        if ($this->settings->enableDuplicateFootnotes) {
            //  return array size after adding which is the last footnote's number (not the array index)
            return array_push($this->footnotes, $footnote);
        }

        //  check if given footnote already exists
        $key = array_search($footnote, $this->footnotes);

        //  add the new footnote
        if ($key === false) {
            $this->footnotes[] = $footnote;
            $key = array_search($footnote, $this->footnotes);
        }

        //  return the footnote's number (not the array index)
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
    public function exist(): bool
    {
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
    public function get(array $options = []): array
    {
        $result = [];

        foreach ($this->footnotes as $key => $footnote) {
            $number = $key + 1;

            if ($this->settings->enableAnchorLinks) {
                $anchorAttributes = $options['anchorAttributes'] ?? [];
                $anchorAttrs = array_merge_recursive($anchorAttributes, ['name' => 'footnote-' . $number]);

                $number = Html::tag('a', $number, $anchorAttrs);
            }

            $result[$number] = $footnote;
        }

        return $result;
    }
}
