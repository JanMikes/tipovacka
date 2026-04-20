# Test Fixtures Reference

All fixture data is defined as class constants on `App\DataFixtures\AppFixtures`.
Import the class in tests: `use App\DataFixtures\AppFixtures;`

## Users

| Constant prefix     | Email                       | Nickname         | Password   | Verified | Active | Deleted |
|---------------------|-----------------------------|------------------|------------|----------|--------|---------|
| `ADMIN_*`           | admin@tipovacka.test        | admin            | `password` | yes      | yes    | no      |
| `VERIFIED_USER_*`   | user@tipovacka.test         | tipovac          | `password` | yes      | yes    | no      |
| `UNVERIFIED_USER_*` | unverified@tipovacka.test   | novy_uzivatel    | `password` | no       | yes    | no      |
| `DELETED_USER_*`    | deleted@tipovacka.test      | smazany          | `password` | yes      | yes    | yes     |

### UUIDs

- `AppFixtures::ADMIN_ID` = `01933333-0000-7000-8000-000000000001`
- `AppFixtures::VERIFIED_USER_ID` = `01933333-0000-7000-8000-000000000002`
- `AppFixtures::UNVERIFIED_USER_ID` = `01933333-0000-7000-8000-000000000003`
- `AppFixtures::DELETED_USER_ID` = `01933333-0000-7000-8000-000000000004`

These match indices 0–3 of `PredictableIdentityProvider::PREDEFINED_UUIDS`.

## Sports

Seeded by both the foundation migration (prod) and `AppFixtures` (dev/test). Tests use `doctrine:schema:create`, which skips migrations — fixtures guarantee the row is present.

| Code       | Name   | UUID                                       |
|------------|--------|--------------------------------------------|
| `football` | Fotbal | `01960000-0000-7000-8000-000000000001`     |

Reference: `App\Entity\Sport::FOOTBALL_ID`

## Notes

- MockClock fixed at `2025-06-15 12:00:00 UTC` in all tests.
- Fixture users created with `createdAt = 2025-06-15 12:00:00 UTC`.
- DAMA DoctrineTestBundle wraps each test in a transaction; fixture baseline is always present.
- Fixture UUIDs are hardcoded (not consumed via `ProvideIdentity::next()`), so tests start from index 0 of the predictable provider uncontested.
