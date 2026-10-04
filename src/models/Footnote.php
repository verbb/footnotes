<?php
namespace verbb\footnotes\models;

use Twig\Markup;

class Footnote
{
    // Properties
    // =========================================================================

    public string $id;
    public int $number;
    public Markup $html;
    public string $text;
    public string $anchorId;

    private array $references = [];


    // Public Methods
    // =========================================================================

    public function __construct(string $id, int $number, string $html, string $text, string $anchorId)
    {
        $this->id = $id;
        $this->number = $number;
        $this->html = new Markup($html, 'UTF-8');
        $this->text = $text;
        $this->anchorId = $anchorId;
    }

    public function addReference(FootnoteReference $reference): void
    {
        $this->references[] = $reference;
    }

    public function getReferences(): array
    {
        return $this->references;
    }
}
