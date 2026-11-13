# Interfaces & Types

Footnotes adds two object types to Craft’s GraphQL schema, and a `footnotesProcessed` field to every CKEditor field type. [Queries](queries.md) shows complete requests that use them. A `!` after a field’s type means it’s never null.

## Fields

### The `footnotesProcessed` Field

Footnotes adds `footnotesProcessed` to each generated `{handle}_CkeditorField` type, which Craft exposes when the CKEditor field’s **GraphQL Mode** is **Full data**. It returns a [`FootnotesProcessed`](#the-footnotesprocessed-type) object for the field’s content, including content from nested entries.

| Argument | Type | Description |
| --- | --- | --- |
| `anchorScope` | `String` | A stable prefix for the generated anchor IDs, matching the Twig filter’s `anchorScope` option. Omit it to generate a scoped token for each resolve, or pass an empty string for unscoped `footnote-1` and `fnref:1` IDs. |

```graphql
body {
  footnotesProcessed(anchorScope: "article-detail") {
    html
    items {
      number
      text
      listAnchorId
    }
  }
}
```

## Types

### The `FootnotesProcessed` Type

The processed body and its footnotes, returned by the `footnotesProcessed` field and the `parseFootnotesFromHtml` query.

::: reference
#### `html`

**Type:** `String!`

The body HTML with each footnote marker replaced by a numbered reference. The definitions region isn’t included.
:::

::: reference
#### `items`

**Type:** `[FootnotesItem!]!`

The ordered footnote definitions, for rendering a list below the content. An empty list when the HTML has no footnotes.
:::

### The `FootnotesItem` Type

One footnote definition in a `FootnotesProcessed` object’s `items` list.

For a classic inline note processed with the anchor scope `article-detail`, an item looks like this:

```json
{
  "id": null,
  "number": 1,
  "text": "Bureau of Meteorology, 2025.",
  "html": null,
  "listAnchorId": "footnote-article-detail.1",
  "referenceAnchorId": "fnref:article-detail.1",
  "referenceAnchorIds": null,
  "numberMarkup": null
}
```

Notes created with the CKEditor footnote tools also return their `id`, `html` and every reference in `referenceAnchorIds`, and use compact anchor IDs such as `fn-article-detail-0000000001`. [Rendering Footnotes](../template-guides/rendering-footnotes.md) explains how those IDs are built.

::: reference
#### `html`

**Type:** `String`

The rich definition HTML, or null for classic inline notes.
:::

::: reference
#### `id`

**Type:** `String`

The stable definition ID, or null for classic inline notes.
:::

::: reference
#### `listAnchorId`

**Type:** `String!`

The ID to put on the rendered definition, such as `footnote-1` for a classic inline note.
:::

::: reference
#### `number`

**Type:** `Int!`

The one-based footnote number, matching the reference numbers in the processed HTML.
:::

::: reference
#### `numberMarkup`

**Type:** `String`

The HTML for the list marker, using the `referenceStyle` setting, when `enableAnchorLinks` is enabled. Otherwise null, so use `number` instead.
:::

::: reference
#### `referenceAnchorId`

**Type:** `String!`

The ID of the first in-text reference, such as `fnref:1` for a classic inline note.
:::

::: reference
#### `referenceAnchorIds`

**Type:** `[String!]`

All in-text reference IDs for the definition, in the order they appear. A note referenced more than once has several values. Null for classic inline notes.
:::

::: reference
#### `text`

**Type:** `String!`

The plain definition text. Classic inline notes return the HTML stored inside the marker.
:::
