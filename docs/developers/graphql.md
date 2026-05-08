# GraphQL
Footnotes adds GraphQL support so you can render the same body + footnote list you would build in Twig with the `footnotes` filter and `footnotes()` function.

## Requirements
- Craft’s GraphQL API must be enabled (`enableGql` in [general config](https://craftcms.com/docs/5.x/reference/config/general.html#enablegql)).
- The field must be a **CKEditor** field whose GraphQL mode is set to **full data** (not “as plain text”). In the field settings, open the CKEditor field layout and set **GraphQL mode** to **Full data** so the field exposes the structured `{handle}_CkeditorField` type instead of a plain `String`.

## CKEditor Field
On every generated CKEditor GraphQL type (names like `body_CkeditorField`), you get an extra non-null field:

- **`footnotesProcessed`** (`FootnotesProcessed!`) — optional argument **`anchorScope`** (`String`): same meaning as the Twig filter option; omit to generate a scoped token per resolve, or pass `""` for legacy `footnote-1` / `fnref:1` fragments.

It bundles:

| Field | Twig equivalent |
| ----- | ---------------- |
| `html` | `{{ entry.body \| footnotes }}` |
| `items` | The list you would loop in `{% for number, text in footnotes() %}` |

Each `FootnotesItem` includes:

| GraphQL field | Purpose |
| ------------- | ------- |
| `number` | Integer footnote number (1-based). |
| `text` | Footnote text from the editor. |
| `referenceAnchorId` | e.g. `fnref:1` or scoped `fnref:entry-12.1` — matches `id` on the in-text reference when [anchor links](../feature-tour/usage.md#anchor-links) are enabled. |
| `listAnchorId` | e.g. `footnote-1` or `footnote-a1b2c3d4e5f6g7h8.1` — use as `id` on your list row for `#` links from the reference. |
| `numberMarkup` | When anchor links are enabled, HTML for the marker in the list (like Twig’s `number` with `raw`). Otherwise `null` — use `number`. |

### Example Query
```graphql
query Article($slug: [String]) {
  entries(section: "news", slug: $slug) {
    ... on newsArticle_Entry {
      title
      fieldHandle {
        html
        footnotesProcessed {
          html
          items {
            number
            text
            referenceAnchorId
            listAnchorId
            numberMarkup
          }
        }
      }
    }
  }
}
```

Add `footnotesProcessed(anchorScope: "entry-123")` when you want a stable prefix (for example concatenate your entry id on the client). Omit the argument to generate a scoped token per resolve.

**Note:** Numbering is computed **per field** for each GraphQL resolve. Unlike Twig, where multiple `| footnotes` filters on one request share one global counter, each `footnotesProcessed` (and each `parseFootnotesFromHtml` call) starts numbering at 1. That matches typical headless usage (one field = one article body).

### Chunks
If you query `chunks { ... on CkeditorMarkup { ... } }` instead of the root `html`, Craft does not run this plugin’s type hook on `CkeditorMarkup`. For markup-only chunks, either:

- Use the root field’s `footnotesProcessed` (it uses full parsed HTML including nested entries), or
- Pass the chunk’s `html` through the helper query below.

## Root Query
When a rich text field is exposed as a **plain string** in GraphQL, you can still process footnotes server-side. The query accepts an optional **`anchorScope`** argument with the same rules as `footnotesProcessed`.

```graphql
query Footnotes($html: String!) {
  parseFootnotesFromHtml(html: $html) {
    html
    items {
      number
      text
      referenceAnchorId
      listAnchorId
      numberMarkup
    }
  }
}
```

### Schema Permission
The `parseFootnotesFromHtml` query is only registered when the active GraphQL schema includes the **Footnotes** permission **Process footnotes via GraphQL (root query and CKEditor fields)** (`footnotes:read`). Enable it under **GraphQL → Schemas** for the token or schema you use.

The **`footnotesProcessed`** field on CKEditor types does **not** require this extra permission; if you can read the entry field, you can read `footnotesProcessed`.

