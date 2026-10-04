# GraphQL

Footnotes stores document-native references and definitions inside the CKEditor field's HTML. A client can request that complete HTML directly, or use Footnotes' processed fields when it needs the body and structured definitions separately.

## Requirements

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

Use each item's `listAnchorId` on the definition wrapper. `referenceAnchorIds` contains every in-text occurrence for a repeated note, so the client can render one backlink per value. `referenceAnchorId` remains the first occurrence for clients written against the earlier shape.

The optional `anchorScope` argument makes the generated IDs deterministic and prevents collisions when one view processes several fields. Omit it to generate a scoped token for that resolve, or pass an empty string for the classic unscoped fragment format.

Each item exposes the following fields:

| Field | Result |
| --- | --- |
| `id` | Stable definition identity for document-native notes, or `null` for classic inline content. |
| `number` | One-based display number derived from reference order. |
| `text` | Plain definition text for document-native notes. Classic inline content retains its historical HTML string for compatibility. |
| `html` | Rich definition HTML for document-native notes, or `null` for classic inline content. |
| `listAnchorId` | Fragment target for the rendered definition. |
| `referenceAnchorId` | First in-text reference ID. |
| `referenceAnchorIds` | Every in-text reference ID in occurrence order, or `null` for classic inline content. |
| `numberMarkup` | Compatibility marker markup when `enableAnchorLinks` is enabled, otherwise `null`. |

Numbering starts at 1 for each `footnotesProcessed` resolve. If several fields should share one numbered list, collect their results on the client in query order and assign the combined presentation numbers there.

## Process an HTML String

Use `parseFootnotesFromHtml` when a rich-text value is exposed as a plain string or when processing a markup-only CKEditor chunk. Supply the HTML through a GraphQL variable rather than interpolating it into the query:

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

Footnotes accepts up to 1 MiB of HTML, 1,000 footnote markers, and a 255-byte `anchorScope` across the Footnotes fields resolved in one HTTP request. Aliases and batched GraphQL operations share those limits. If a request exceeds the budget, request the original CKEditor value and process or divide the content in the client instead.

When querying `chunks`, the Footnotes field extension does not appear on individual `CkeditorMarkup` chunks. Use the parent field's `footnotesProcessed` value, which includes parsed nested-entry content, or pass a markup chunk's `html` to `parseFootnotesFromHtml`.
