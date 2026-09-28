# db/ — the Business OS installer's migrations

`bin/app_install.php` (the kernel's installer) applies `db/*.sql` in order, as postgres, into a new database.
001–003 are links to the schema a standalone install loads from `docs/sql/` (see `docs/sql/README.md`), so there is
one copy of each; 004 gives the application's role the tables. A standalone install ignores this folder.
