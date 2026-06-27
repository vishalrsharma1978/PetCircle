# PawCircle Pet Community Portal

PawCircle is a pet-focused copy of the original community portal. It is tailored for local pet communities and supports pet-type-specific feeds, local group discovery and creation, match-oriented networking, and community event coordination.

## What Changed

This copy keeps the existing HTML, JavaScript, and PHP architecture, but retargets the product around pet communities:

- Pet-type based onboarding using values like Dog, Cat, Bird, Rabbit, Fish, Reptile, and Small Pets
- Community-specific feeds scoped by pet type and community focus
- Local group creation for rescues, neighborhood circles, breed clubs, and meetup crews
- Match-oriented connections for playdates, foster/adoption discovery, and pet-parent networking
- Community event flows for walks, adoption drives, clinics, and meetup sessions
- Pet-specific branding and onboarding copy under the PawCircle name
- Completely purged legacy human-matrimony/astrology logic (gotra, native village, etc.)
- Database natively migrated to use `pet_type` and `breed` instead of legacy columns

## Project Structure

```text
pet_community_proj/
├── pawcircle_frontend.html Main single-page frontend for PawCircle
├── pawcircle_api.php       PHP API layer backed by Supabase REST
├── sql_scripts/            Directory containing all database schema and data dumps
├── Dockerfile              Container build file
├── pawcircle_media_transcoder/ Optional video processing tools
├── img/                    Existing image assets from the copied project
└── README.md               This file
```

## Core Functionality

### Per-community feeds

Posts are natively filtered by the `pet_type` and `breed` columns in the database. 

### Local groups

Members can create and join groups for use cases like:

- neighborhood walks
- rescue coordination
- breed circles
- foster support
- clinic and grooming referrals

### Match making

The original social connection model is reused as a lightweight pet match system for:

- playdate discovery
- foster and adoption introductions
- trusted local pet-parent connections

### Community events

The app supports event creation and listing for:

- adoption camps
- vaccination drives
- training sessions
- pet walks
- meetup events

## Backend Notes

The backend has been fully updated and cleaned up from its original eSamaj origins.
The underlying Supabase schema has been natively migrated:
- `religion` has been renamed to `pet_type`
- `community` has been renamed to `breed`

All legacy astrology and matchmaking filters (`gotra`, `rashi`, `mangalik`, `native_village`, `age_group`) have been dropped from both the database and the API logic to prevent 500 server errors and maintain strict pet-domain focus.

## Running Locally

### Prerequisites

- PHP with cURL enabled
- Supabase project and credentials in `.env`
- A static or PHP-capable local server

### Basic run

1. Copy `.env.example` to `.env` and fill in the Supabase values.
2. Serve the project folder with PHP.
3. Open `pawcircle_frontend.html` through the same origin as `pawcircle_api.php`.

Example:

```powershell
php -S localhost:8000
```

Then open:

```text
http://localhost:8000/pawcircle_frontend.html
```

## Suggested Next Steps

1. Replace the remaining inherited image assets with actual pet-community artwork.
2. Seed Supabase with pet-specific demo groups, events, and posts.
