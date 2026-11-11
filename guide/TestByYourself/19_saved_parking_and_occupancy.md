# 19 Saved parking, the way back and simulated occupancy

**Covers:** EPIC-09 ISM-95 (ISM-96, ISM-97) and ISM-98 (ISM-100), ParkingFacilities, decision D-013
**Automated tests:** `tests/Feature/Modules/ParkingFacilities/ParkingServiceTest.php`, `tests/Feature/Modules/ParkingFacilities/FacilityFinderTest.php`, `tests/Feature/Modules/ParkingFacilities/ParkingZoneAdminTest.php`, `tests/Unit/ParkingFacilities/SimulatedOccupancyFeedTest.php`
**Needs:** nothing else; this file is self-contained
**Time:** about 10 minutes

## What you are proving

A signed-in customer saves where they parked (zone, bay, note), updates or clears it, and gets floor-by-floor directions back to the car from the floor they are on. The spot is private. Free spaces per parking zone are shown, always labelled as simulated. Mall administrators maintain the parking zones.

## Setup

1. Docker Desktop running; in the project folder `docker compose up -d`.
2. `php artisan migrate:fresh --seed` (clean demo database; **deletes local data**).
3. `npm run build`.
4. `php artisan serve` and keep it running (<http://127.0.0.1:8000>).

PHP too old (">= 8.4.1" error)? Use `C:\Users\Shomik\.php\8.5\php.exe artisan ...`.

Accounts (password `password`), sign in at <http://127.0.0.1:8000/login>: `customer@demo.test`, `staff@demo.test`, `mall.admin@demo.test`.

### Create the data this file needs

Nothing beyond the demo seed: floor B1 "Basement Parking" with zones **B1-A** (60 spaces, near Lift A) and **B1-B** (80 spaces), and a lift ("Lift A, Centre atrium") on every floor.

## Steps

### 1. Occupancy is labelled as simulated

1. Signed out, open <http://127.0.0.1:8000/malls/niagara-city-mall/facilities#parking>.

**Expect:** "Free spaces right now" with an amber badge **Simulated data, not live**, the sentence that there are no sensors, and one bar per zone with "N of 60 spaces free" / "N of 80 spaces free". Reload a few minutes later in the same hour: the numbers stay the same; in the evening (18:00-20:00 Dhaka time) the bars are fuller than in the morning.

2. Under "My parking spot": "Sign in to remember where you parked."

### 2. Save a spot

1. Sign in as `customer@demo.test`, open <http://127.0.0.1:8000/malls/niagara-city-mall/facilities#parking>.

**Expect:** "No spot saved for this mall yet." and a form with Zone, Bay, Note.

2. Zone **B1-A · Zone A (near Lift A)**, Bay `14`, Note `blue car next to the pillar`, press **Save my spot**.

**Expect:** "Your parking spot was saved." and "You parked in B1-A · Zone A (near Lift A) on Basement Parking, bay 14." with the time and the note.

### 3. Directions back

1. "I am on" **First Floor** → **Take me back to my car**.

**Expect:** a numbered list:
1. Go to Lift A (Centre atrium).
2. Go down 2 floors to Basement Parking.
3. Your car is in zone B1-A (Zone A (near Lift A)), bay 14.
4. Your note: blue car next to the pillar

2. Choose **Basement Parking** and ask again. **Expect:** "Your car is on this floor, in zone B1-A ...".

### 4. Update and clear

1. Change Zone to **B1-B**, Bay `3`, press **Update my spot**. **Expect:** the summary now says B1-B, bay 3 (only one spot is kept).
2. Press **Clear my spot**. **Expect:** "Your parking spot was cleared." and "No spot saved for this mall yet."

### 5. Privacy

1. Save a spot again as the customer. In another browser sign in as `staff@demo.test` and open the same parking URL.

**Expect:** staff sees "No spot saved for this mall yet." — never the customer's zone, bay or note.

### 6. Administer parking zones

1. Sign in as `mall.admin@demo.test`, open <http://127.0.0.1:8000/account> → **Facilities**, scroll to **Parking zones**.
2. **Add parking zone**: Code `b1-c`, Spaces `40`, Name `Zone C (motorbikes)`, Floor Basement Parking → **Add parking zone**.

**Expect:** "Zone C (motorbikes) was added." and the zone listed as **B1-C** (stored upper-case); it appears on the customer parking section with its own bar.

3. **Edit** B1-C, Status **Archived**, save. **Expect:** greyed out; gone from the customer page and from the customer's Zone list.

## Things that must be refused

| Account | Try | Expect |
|---|---|---|
| guest | press nothing; there is no save form | "Sign in to remember where you parked." |
| guest | POST to http://127.0.0.1:8000/malls/niagara-city-mall/parking (e.g. with a REST client) | redirected to sign in, nothing saved |
| `customer@demo.test` | F12, change the Zone option value to a random UUID, save | "Choose a parking zone of this mall.", nothing saved |
| `customer@demo.test` | Bay `<b>14</b>` | refused (letters, digits, spaces and hyphens only) |
| `mall.admin@demo.test` | add a zone with code `B1-A` again | "This mall already has a parking zone with code B1-A." |
| `customer@demo.test` | open the admin Facilities URL | **403 Forbidden** |

## If something looks wrong

- 429 Too Many Requests: more than 20 save/clear actions in a minute; wait one minute.
- Directions are floor-by-floor only; a drawn route on the map waits for EPIC-05 (MapsNavigation).

## Result

Date: ____  Tester: ____  Pass / Fail: ____  Notes: ____
