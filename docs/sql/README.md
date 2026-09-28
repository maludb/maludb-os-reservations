# SQL files

**To install, load only these three, in order:**

1. `pg_schema.sql`: the complete PostgreSQL schema (51 tables)
2. `nav_permissions.sql`: sidebar visibility per role
3. `os_adoption.sql`: the changes made while adopting ZozoCal into the MaluDB Business OS (safe to re-run; also the upgrade for an existing install)

Every other file here is a historical migration from the MySQL era, kept for reference. They are
already folded into `pg_schema.sql`, and several are MySQL syntax that fails on PostgreSQL. Do not
load them on a new install.
