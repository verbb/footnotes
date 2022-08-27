<?php
namespace verbb\footnotes\models;

use craft\base\Model;

class Settings extends Model
{
    // Properties
    // =========================================================================

    /**
     * @var bool
     */
    public $enableAnchorLinks = false;

    /**
     * @var bool
     */
    public $enableDuplicateFootnotes = false;
}
