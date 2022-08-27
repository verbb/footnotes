<?php
namespace verbb\footnotes\models;

use craft\base\Model;

class Settings extends Model
{
    // Properties
    // =========================================================================

    public bool $enableAnchorLinks = false;
    public bool $enableDuplicateFootnotes = false;
}
