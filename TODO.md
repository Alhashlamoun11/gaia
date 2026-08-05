# GAIA TOURS — Dynamic Detail Pages — Implementation TODO

## Objective
Implement the four missing dynamic detail pages (events, hotels, rooms, tours),
connect all existing cards/buttons, and ensure clean SEO URLs + routing.

> Status: The four detail pages (events.php, hotel.php, room.php, tour.php) and
> their DB schema (migration 8) already exist and are fully functional.
> This pass completes the remaining wiring, routing, and verification.

## Steps

- [x] 0. Explore repo, audit DB schema, verify detail-page columns & seed data
- [x] 1. Confirm detail pages exist & load from DB (events/hotel/room/tour)
- [x] 2. Confirm gaia_url()/gaia_switch_lang_url() map all four to clean SEO URLs
- [x] 3. Confirm router.php routes all four detail pages (EN/AR + non-prefixed)
- [ ] 4. Update `.htaccess` production Apache rules to match router.php:
       - tours/{slug}   -> tour.php
       - events/{slug}  -> events.php
       - hotels/{slug}  -> hotel.php
       - rooms/{id}     -> room.php
       (EN/AR + non-prefixed)
- [ ] 5. Link home event cards (index.php) -> events.php?slug={slug}
- [ ] 6. Link GAIA Night event cards (gaia-night.php) -> events.php?slug={slug}
- [ ] 7. Add detail routes to _route_test.php (event/hotel/room/tour, EN/AR)
- [ ] 8. Create _smoke_details.php smoke test (valid 200 + key markers, invalid 404)
- [ ] 9. Run verification: lints, router test, smoke test, migration verify
- [ ] 10. Update this TODO.md to mark all steps complete
