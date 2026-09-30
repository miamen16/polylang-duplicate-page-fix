# Polylang Duplicate Page Fix

Fixes the compatibility issue between **Duplicate Page** and **Polylang**.

## Problem

When a post/page is duplicated with Duplicate Page, the copied post can inherit the original Polylang translation group.

For example:

```
EN #100
├── AR #101
└── FR #102
```

Duplicating EN #100 should create:

```
EN #100
├── AR #101
└── FR #102

EN #103
```

The duplicate should not be linked to the original Arabic and French posts.

## How it works

The plugin detects Duplicate Page's `dt_duplicate_post_as_draft` admin action and identifies the newly created post.

After Duplicate Page finishes copying the post data, the plugin:

1. Keeps the duplicated post's language.
2. Creates a new Polylang `post_translations` term for the duplicate.
3. Assigns only the duplicate to that new translation group.
4. Does not call `pll_save_post_translations()`.
5. Does not modify the original translation group.

## Requirements

- WordPress 6.2+
- PHP 7.4+
- Polylang
- Duplicate Page by mndpsingh287

## Installation

1. Download or clone this repository into `wp-content/plugins/`.
2. Activate **Polylang Duplicate Page Fix**.
3. Keep using Duplicate Page normally.

Test on a staging/local site before using it on production.

## Important

If an earlier version of this plugin already changed an existing translation group, this plugin does not automatically restore that group. Existing translation relationships should be repaired separately.
