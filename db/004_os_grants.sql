-- Business OS installs only (bin/app_install.php runs db/*.sql in order, as postgres, into the database it creates
-- for this application, owned by reservations_rw). The tables are therefore postgres's: give the application's
-- role everything on them, and on whatever a later migration run the same way creates. A standalone install loads
-- docs/sql/ as the application's own user and does not use this file.
GRANT ALL ON ALL TABLES IN SCHEMA public TO reservations_rw;
GRANT ALL ON ALL SEQUENCES IN SCHEMA public TO reservations_rw;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public GRANT ALL ON TABLES TO reservations_rw;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public GRANT ALL ON SEQUENCES TO reservations_rw;
