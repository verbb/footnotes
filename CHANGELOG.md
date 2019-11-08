# Changelog

<!--## [Unreleased](https://github.com/Vierbeuter/craft-footnotes/compare/master...develop)-->

## [2.2.0](https://github.com/Vierbeuter/craft-footnotes/releases/tag/2.0.0) - 2019-11-08

### Added
 - [#3](https://github.com/Vierbeuter/craft-footnotes/issues/3): new setting for changing how to deal with duplicate footnotes &rarr; either combine identical footnotes to one footnote (which is the behaviour of all previous releases and therefore the plugin's default) or list all footnotes seperately, even those that are equal (which is optional, of course)
 - German translations for settings page
 - slightly extended the README file

### Fixed
 - minor fix of docs: added missing note to README about the `<sup>` tag's `footnote` class (which has been added with release 2.1.0)

## [2.1.1](https://github.com/Vierbeuter/craft-footnotes/releases/tag/2.1.1) - 2019-10-30

### Fixed
 - [#6](https://github.com/Vierbeuter/craft-footnotes/issues/6): fixed the footnote button whose icon always stayed black, also on hovering &rarr; now the icon color does change on hovering the button
 - fixed changelog format to be recognized by Craft Plugin Store

## [2.1.0](https://github.com/Vierbeuter/craft-footnotes/releases/tag/2.1.0) - 2019-10-30

### Changed
 - [#6](https://github.com/Vierbeuter/craft-footnotes/issues/6): changed icon of footnote button and slightly changed the behaviour of the footnote button: footnotes are not just tagged with `<sup>…</sup>` any longer, now they're tagged with `<sup class="footnote">…</sup>` to make footnotes coexist with regular superscript text

### Fixed
 - changelog format

## [2.0.0](https://github.com/Vierbeuter/craft-footnotes/releases/tag/2.0.0) - 2019-10-29

### Added
 - minor improvement: set icon for footnote button

### Changed
 - update sources for Craft CMS 3

## [1.1.3](https://github.com/Vierbeuter/craft-footnotes/releases/tag/1.1.3) - 2017-05-10

### Added
 - minor improvement: Footnotes directly followed by other footnotes (either with or without whitespace characters between them) will now be comma-separated:  
*"your text<sup>2</sup> <sup>3</sup><sup>4</sup>"* will now be rendered as *"your text<sup>2, 3, 4</sup>"*

## [1.1.2](https://github.com/Vierbeuter/craft-footnotes/releases/tag/1.1.2) - 2017-03-31

### Fixed
 - fixed anchor link replacement in "footnotes" filter

## [1.1.1](https://github.com/Vierbeuter/craft-footnotes/releases/tag/1.1.1) - 2016-07-19

### Fixed
- [#2](https://github.com/Vierbeuter/craft-footnotes/issues/2): fixed missing settings template

## [1.1.0](https://github.com/Vierbeuter/craft-footnotes/releases/tag/1.1.0) - 2016-07-05

### Added
 - [#1](https://github.com/Vierbeuter/craft-footnotes/issues/1): anchor links can now be added to footnotes

## [1.0.0](https://github.com/Vierbeuter/craft-footnotes/releases/tag/1.0.0) - 2016-06-23

### Added
- First version of footnotes plugin released for Craft CMS 2
