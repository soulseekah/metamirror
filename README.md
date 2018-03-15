# metamirror

[![Build Status](https://travis-ci.org/soulseekah/metamirror.svg?branch=master)](https://travis-ci.org/soulseekah/metamirror)

An experiment that mirrors subsets of WordPress meta tables for cast-free indexing.

## Background

WordPress stores meta information in key-value structures. Post meta, user meta, term meta, comment meta are all tables with a LONGTEXT `meta_value` that doesn't work well with indexing.

Consider plugins that store numeric values like ratings, pageviews, votecounts. When retrieving objects by these meta values and doing all sorts of math operations on them these values are cast to numeric values (floats or integers). And casting annihilates any indexing there may have been.

## Proposed solution

Create shadow meta table(s) that house casted values in with an index on them. Add conditional triggers on the core meta tables that insert/delete/update the values into the mirrors with a cast. Add SQL rewriting for SELECT queries detecting meta keys and "routing" them to the correct mirror table.

# Installation

Drop in as a plugin.

# Usage

```php
$mirror = new metamirror\Mirror( $wpdb->postmeta, 'INTEGER' );
$mirror->add_meta_key( 'pageviews' );
$mirror->add_meta_key( 'vote%' );
metamirror\Core::add( $mirror );
metamirror\Core::commit();
```

**Do not call `Core::commit()` every single time!** Just like `flush_rewrite_rules()` it's only meant to be run after adding new mirrors. The mirrors do have to be added either way, but once they're committed, no need to commit again until changes are made. Every commit will **DROP** all the mirror tables and recreate them.

# Warranty

Absolutely none. **Don't use in production**. Backup before installing or using.
