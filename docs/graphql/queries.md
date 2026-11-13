# Queries

Footnotes stores references and definitions inside the CKEditor field's HTML. A client can request that complete HTML directly, or use Footnotes' processed fields when it needs the body and structured definitions separately.

## Set Up the Schema

Craft's GraphQL API must be enabled through the [`enableGql` general configuration setting](https://craftcms.com/docs/5.x/reference/config/general.html#enablegql). To use `footnotesProcessed`, edit the CKEditor field and set its **GraphQL Mode** to **Full data** so Craft exposes the generated `{handle}_CkeditorField` object instead of a plain `String`.

The schema or token must already have permission to read the entry and field. The root `parseFootnotesFromHtml` query also requires the **Footnotes → Process footnotes via GraphQL (root query and CKEditor fields)** permission (`footnotes:read`) under **GraphQL → Schemas**.

## Request Complete Field HTML

When the client can render the stored document as one unit, request the CKEditor field's `html` value. It contains the body, linked references, definitions, and backlinks:

```graphql
query Article($slug: [String]) {
  entries(section: "news", slug: $slug) {
    ... on newsArticle_Entry {
      title
      body {
        html
      }
    }
  }
}
```

No Footnotes-specific query is required for this path.

## Request Body and Structured Definitions

Every generated CKEditor field type also exposes `footnotesProcessed`. Its `html` value contains the body with linked references but without the definitions region. Its `items` array contains the ordered definitions for a client-rendered list:

```graphql
query Article($slug: [String]) {
  entries(section: "news", slug: $slug) {
    ... on newsArticle_Entry {
      title
      body {
        footnotesProcessed(anchorScope: "article-detail") {
          html
          items {
            id
            number
            text
            html
            listAnchorId
            referenceAnchorId
            referenceAnchorIds
          }
        }
      }
    }
  }
}
```

Use each item's `listAnchorId` on the definition wrapper. `referenceAnchorIds` contains every in-text occurrence for a repeated note, so the client can render one backlink per value. `referenceAnchorId` holds the first occurrence.

The optional `anchorScope` argument makes the generated IDs deterministic and prevents collisions when one view processes several fields. Omit it to generate a scoped token for that resolve, or pass an empty string for unscoped IDs such as `footnote-1` and `fnref:1`.

[Interfaces & Types](interfaces-types.md#the-footnotesitem-type) describes every field on each item.

Numbering starts at 1 for each `footnotesProcessed` resolve. If several fields should share one numbered list, collect their results on the client in query order and assign the combined presentation numbers there.

Processed `html` uses the plugin-wide `referenceStyle`. Raw CKEditor `html` keeps the number in each reference anchor. Headless clients that request only the stored HTML can apply their preferred punctuation from the numeric `number` value or their own presentation rules.

When querying `chunks`, the Footnotes field extension does not appear on individual `CkeditorMarkup` chunks. Use the parent field's `footnotesProcessed` value, which includes parsed nested-entry content, or pass a markup chunk's `html` to `parseFootnotesFromHtml`.

## Plugin Queries

### The `parseFootnotesFromHtml` Query

Returns the processed body and ordered definitions for an HTML string, as a [`FootnotesProcessed`](interfaces-types.md#the-footnotesprocessed-type) object. Use it when a rich-text value is exposed as a plain string, or when processing a markup-only CKEditor chunk. The schema needs the `footnotes:read` permission described above.

| Argument | Type | Description |
| --- | --- | --- |
| `html` | `String!` | The rich text HTML containing `<sup class="footnote">` markers. |
| `anchorScope` | `String` | A stable prefix for the generated anchor IDs. Omit it to generate a scoped token for each resolve, or pass an empty string for unscoped `footnote-1` and `fnref:1` IDs. |

Supply the HTML through a GraphQL variable rather than interpolating it into the query:

```graphql
query Footnotes($html: String!) {
  parseFootnotesFromHtml(html: $html, anchorScope: "article-detail") {
    html
    items {
      id
      number
      text
      html
      listAnchorId
      referenceAnchorIds
    }
  }
}
```

```json
{
  "html": "<p>Rainfall was above average<sup class=\"footnote\">Bureau of Meteorology, 2025.</sup> across the region.</p>"
}
```

With anchor links enabled and the plain reference style, the response contains the body with a linked reference and the definition as a structured item:

```json
{
  "data": {
    "parseFootnotesFromHtml": {
      "html": "<p>Rainfall was above average<sup class=\"footnote\"><a id=\"fnref:article-detail.1\" href=\"#footnote-article-detail.1\">1</a></sup> across the region.</p>",
      "items": [
        {
          "id": null,
          "number": 1,
          "text": "Bureau of Meteorology, 2025.",
          "html": null,
          "listAnchorId": "footnote-article-detail.1",
          "referenceAnchorIds": null
        }
      ]
    }
  }
}
```

Classic inline notes like this one return null for `id`, `html` and `referenceAnchorIds`. Notes created with the CKEditor footnote tools return all three.

## Processing Limits

Footnotes accepts up to 1 MiB of HTML, 1,000 footnote markers, and a 255-byte `anchorScope` across the Footnotes fields resolved in one HTTP request. Aliases and batched GraphQL operations share those limits. If a request exceeds the budget, request the original CKEditor value and process or divide the content in the client instead.
