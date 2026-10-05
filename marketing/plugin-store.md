Footnotes lets CKEditor authors write rich supporting notes without leaving the document. References stay in the main text while definitions live in an editable footnotes region at the end of the same field.

The saved field value contains accessible references, definitions and return links, so ordinary field output is complete. Developers can also move or combine definitions through a local Twig collection, customise the markup, or consume structured rich notes through GraphQL.

## Features

- Edit footnote definitions in the same CKEditor document as their references.
- Use paragraphs, links, inline formatting, soft breaks and simple lists inside definitions.
- Reuse one definition from several references, with a return link for every occurrence.
- Display in-text references as plain numbers or Wikipedia-style square-bracketed numbers.
- Render complete footnotes through ordinary field output without frontend JavaScript.
- Move or combine definitions from one or several content bodies through a local Twig collection.
- Customise reference, list, item and backlink attributes without replacing semantic markup.
- Read rich definition HTML, stable identities and repeated-reference data through GraphQL.
- Keep existing CKEditor, Redactor and Twig integrations operational through a compatibility layer.
