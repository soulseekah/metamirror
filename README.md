# metamirror

An experiment that mirrors subsets of WordPress meta tables for cast-free indexing.

## Background

WordPress stores meta information in key-value structures. Post meta, user meta, term meta, comment meta are all tables with a LONGTEXT `meta_value` that doesn't work well with indexing.

Consider plugins that store numeric values like ratings, pageviews, votecounts. When retrieving objects by these meta values and doing all sorts of math operations on them these values are cast to numeric values (floats or integers). And casting annihilates any indexing there may have been.

## Proposed solution

Create shadow meta table(s) that house casted values in with an index on them. Add conditional triggers on the core meta tables that insert/delete/update the values into the mirrors with a cast. Add SQL rewriting for SELECT queries detecting meta keys and "routing" them to the correct mirror table.
