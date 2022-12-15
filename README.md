# drupal_event

Provides a Drupal Event content type with sub-pages to be reused in projects.

# Installation

In `composer.json`:

1. In `"repositories":[]` add:
```
{
    "type": "git",
    "url": "git@github.com:eaudeweb/drupal_event.git"
}
```

2. A SSH Key is required.

3. Run: ```composer require drupal/drupal_event```

## Basic Field Configuration
Views Sort:
- **Closest Date** - custom views sorting. Display the closest events to the present date using the pager limit. If there are less upcoming event or none, past events will be shown.

Block:
- **Event Menu Block** - render a summary of sub-pages. Field `field_event_elements` it's used.

Field Formatters:
- **Date interval** - `flexible_daterange_interval`
- **Rendered entity with limit** - display a limited number of items. Use `use_link` setting to insert a redirect page.

## drupal_paragraph

Provides generic paragraphs to be reused in projects. Provides also the functionality to create sub-pages for an event.

Modules required: `block_field`, `viewsreference`, `paragraphs_browser`

Paragraphs available:
- Block content
- Button
- Heading
- HTML
- Image with description
- List with Items
- Views

Paragraphs used to create sub-pages for an event:
- Page HTML
- Schedule
- Speakers
