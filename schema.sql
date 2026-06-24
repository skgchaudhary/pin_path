-- ==========================================================================
-- PinPath — PostgreSQL schema (run once in Supabase → SQL Editor)
-- ==========================================================================

-- One row per trip. id is the slug (e.g. "pune-food-trip").
create table if not exists itineraries (
    id          text primary key,
    name        text        not null,
    created_at  timestamptz not null default now(),
    updated_at  timestamptz not null default now()
);

-- One row per stop. Location ids ("loc_001") are unique *within* an itinerary.
create table if not exists locations (
    id            text             not null,
    itinerary_id  text             not null
                  references itineraries(id) on delete cascade,
    name          text             not null,
    lat           double precision not null,
    lng           double precision not null,
    visited       boolean          not null default false,
    notes         text             not null default '',
    added_at      timestamptz      not null default now(),
    updated_at    timestamptz      not null default now(),
    primary key (itinerary_id, id)
);

create index if not exists locations_itinerary_idx on locations (itinerary_id);
