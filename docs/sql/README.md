# SQL files

**To install, load only these two, in order:**

1. `pg_schema.sql`: the complete PostgreSQL schema (51 tables)
2. `nav_permissions.sql`: sidebar visibility per role

Every other file here is a historical migration from the MySQL era, kept for reference. They are
already folded into `pg_schema.sql`, and several are MySQL syntax that fails on PostgreSQL. Do not
load them on a new install.
