-- TRUNCATE url_checks;
-- TRUNCATE urls CASCADE;

CREATE TABLE IF NOT EXISTS urls
(
    id         bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    name       varchar (255) UNIQUE NOT NULL,
    created_at timestamp
    );

CREATE TABLE IF NOT EXISTS url_checks
(
    id          bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    url_id      bigint NOT NULL,
    status_code int,
    h1          varchar (255),
    title       varchar (255),
    description text,
    created_at  timestamp
    );