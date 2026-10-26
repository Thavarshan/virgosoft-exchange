SELECT 'CREATE DATABASE virgosoft_testing'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'virgosoft_testing')\gexec

SELECT 'CREATE DATABASE virgosoft_e2e'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'virgosoft_e2e')\gexec
