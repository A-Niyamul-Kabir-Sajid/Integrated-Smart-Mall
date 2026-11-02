-- Runs once when the PostgreSQL volume is first initialised.
-- Creates the database used by `composer test:pgsql` so tests never touch development data.
SELECT 'CREATE DATABASE ism_testing'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'ism_testing')\gexec
