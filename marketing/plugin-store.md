Footnotes gives CKEditor authors a deliberate way to add supporting notes without interrupting the main text. Mark the note in the editor and render an ordered, linked footnote list through Twig.

Authors can identify text as a footnote from the CKEditor toolbar instead of hand-writing anchors or HTML. The content remains part of the rich-text workflow while the published page can separate supporting detail from the main passage.

## Features

- **CKEditor control:** Let authors mark a note through the familiar rich-text toolbar.
- **Ordered notes:** Collect marked content into a structured footnote list.
- **Linked references:** Connect the inline marker and its corresponding note for easier reading.
- **Scoped anchors:** Keep references unique when a page contains several footnote-enabled content bodies.
- **Duplicate handling:** Choose how repeated notes should be represented in the generated list.
- **Twig rendering:** Place processed content and notes within the project’s own templates.
- **GraphQL support:** Carry footnote-aware rich text into a headless implementation.
- **Field-level setup:** Enable the feature where authors need it without changing every editor.
- **Flexible rendering:** Twig helpers process footnote markers into linked references and a corresponding list. Developers retain control over the surrounding template and can use the field through GraphQL when the project is headless.
