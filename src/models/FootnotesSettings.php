<?php

namespace vierbeuter\footnotes\models;

use craft\base\Model;

/**
 * The FootnotesSettings class represents the footnotes plugin's settings.
 *
 * @package vierbeuter\footnotes\models
 */
class FootnotesSettings extends Model
{

    /**
     * @var bool
     */
    public $enableAnchorLinks = false;
    /**
     * @var bool
     */
    public $enableDuplicateFootnotes = false;
}
