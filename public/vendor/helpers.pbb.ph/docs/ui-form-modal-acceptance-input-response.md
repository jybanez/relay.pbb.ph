# UI Form Modal Acceptance Input Response

## Purpose

Provide the exact project-side inputs requested in:

- `docs/ui-form-modal-acceptance-input-request.md`

This response is based on the current implementation in `PBB - HQ`, not on screenshots or approximated flow assumptions.

## Scope

Acceptance targets:

1. `Hub Add/Edit`
2. `Add Uplink`

## Important Context

The current `Hub Add/Edit` UI no longer exposes a free-form deployment selector in the modal.

Instead, the modal is opened in one of two ways:

- geodata-driven flow
  - opened from a region/province/city/barangay tree row
  - deployment is already known from the clicked row
- other-deployment flow
  - opened from `Add Other Hub`
  - deployment is fixed to `other`

So the acceptance exercise should model the current real flow, not an older “pick deployment inside the modal” version.

---

## A. Hub Add/Edit

### 1. Current Field Inventory

There are effectively two current form variants.

### A1. Geodata-driven Hub Add/Edit

Current implementation reference:

- `resources/js/app.js`
  - `buildHubSeedFromTreeRow(...)`
  - `openHubEditorModal(...)`
  - `buildHubDefaultName(...)`
  - `buildHubLocationSummary(...)`

Visible fields / displays:

1. Context strip
- display-only
- shows:
  - deployment badge
  - location summary
- not submitted directly as a field

2. `domain`
- label: `Domain Name`
- input type: text input
- optional
- editable
- default value:
  - edit: existing `hub.domain`
  - add: empty

3. `status`
- label: `Status`
- input type: helper `ui.select`
- required
- editable
- default value:
  - edit: existing `hub.status`
  - add: first configured status, currently `planned`

4. `code`
- label: `Hub Code`
- input type: text input
- optional
- editable
- default value:
  - edit: existing `hub.code`
  - add: empty

5. `name`
- hidden field
- required in payload
- not user-editable
- default value behavior:
  - generated from row ancestry / geodata context
  - edit: seeded from current hub name or derived seed default

6. `deployment`
- hidden / fixed value
- not editable
- default value behavior:
  - taken from clicked tree row / seed

7. `country_code`
- hidden / fixed value
- not editable
- default value behavior:
  - current app setting, currently `state.settings.country`

8. `reg_code`
9. `prov_code`
10. `citymun_code`
11. `brgy_code`
- hidden / fixed location codes
- not editable
- default value behavior:
  - seeded from tree row ancestry or existing hub

### A2. Other-Deployment Hub Add/Edit

Current implementation reference:

- `resources/js/app.js`
  - `openHubEditorModal(...)`

Visible fields:

1. `name`
- label: `Name`
- input type: text input
- required
- editable
- default value:
  - edit: existing `hub.name`
  - add: empty

2. `status`
- label: `Status`
- input type: helper `ui.select`
- required
- editable
- default value:
  - edit: existing `hub.status`
  - add: first configured status, currently `planned`

3. `code`
- label: `Code`
- input type: text input
- optional
- editable
- default value:
  - edit: existing `hub.code`
  - add: empty

4. `domain`
- label: `Domain`
- input type: text input
- optional
- editable
- default value:
  - edit: existing `hub.domain`
  - add: empty

Hidden fixed fields:

5. `deployment`
- hidden / fixed
- value: `other`

6. `country_code`
- hidden / fixed
- value: current app country setting

7. `reg_code`
8. `prov_code`
9. `citymun_code`
10. `brgy_code`
- hidden
- currently empty for the `other` flow unless already present on the edited record

### 2. Mode Differences

#### Create vs Edit

Common differences:

- create uses:
  - `POST /api/admin/hubs`
- edit uses:
  - `PUT /api/admin/hubs/{hub}`
- title changes:
  - geodata create:
    - `Add Hub for {row label or location summary}`
  - other create:
    - `Add Other Hub`
  - edit:
    - `Edit Hub`
- success message changes:
  - create:
    - `Hub created.`
  - edit:
    - `Hub updated.`

Field behavior differences:

- there is no current create-only or edit-only visible field
- mode mostly affects:
  - endpoint
  - title
  - initial values

#### Flow differences: geodata vs other

This is the more important distinction than create vs edit.

If flow = `other`:

- `name` is visible and editable
- no context strip is shown
- deployment is fixed to `other`

If flow = geodata (`region` / `province` / `city` / `barangay`):

- context strip is shown
- `name` is hidden
- generated default name must still be submitted
- deployment is fixed by the seed / clicked row

### 3. Conditional Rules

Current real conditional logic:

If modal flow = `other`:

- `name` is editable
- no context strip
- payload `deployment = "other"`

If modal flow = geodata:

- `name` is hidden
- generated name must still be submitted
- context strip is display-only
- payload location codes are seeded from current tree context

Generated-name behavior:

- `barangay`
  - `{barangay}, {city}, {province}`
- `city`
  - `{city}, {province}`
- `province`
  - `{province}`
- `region`
  - `{region}`
- `national`
  - country code

Location summary behavior:

- `barangay`
  - `{barangay}, {city}, {province}`
- `city`
  - `{city}, {province}`
- `province`
  - `{province}, {region}`
- `region`
  - `{region}`
- `national`
  - country code

### 4. Payload Contract

Current outgoing payload shape for both add and edit:

```json
{
  "name": "CEBU CITY, CEBU",
  "code": "optional-code-or-null",
  "domain": "hub.example.pbb.ph",
  "deployment": "city",
  "status": "planned",
  "country_code": "PH",
  "reg_code": "07",
  "prov_code": "0722",
  "citymun_code": "072217",
  "brgy_code": null
}
```

Notes:

- `name` must always be present
- `code` and `domain` are sent through `normalizeEmpty(...)`, so blank input becomes `null`
- geodata flows still submit hidden/generated values:
  - `name`
  - location codes
  - fixed deployment
- `country_code` is always included from current app settings

Current save endpoints:

- add:
  - `POST /api/admin/hubs`
- edit:
  - `PUT /api/admin/hubs/{hub}`

### 5. Validation / Error Response Examples

Backend validation is currently in:

- `app/Http/Controllers/Api/HubController.php`
  - `validateHub(...)`

The project currently uses Laravel request validation directly:

```php
$request->validate([...])
```

So validation failures currently use Laravel’s default JSON validation error shape for API requests.

Representative examples:

#### Missing name

```json
{
  "message": "The name field is required.",
  "errors": {
    "name": [
      "The name field is required."
    ]
  }
}
```

#### Duplicate domain

```json
{
  "message": "The domain has already been taken.",
  "errors": {
    "domain": [
      "The domain has already been taken."
    ]
  }
}
```

#### Duplicate code

```json
{
  "message": "The code has already been taken.",
  "errors": {
    "code": [
      "The code has already been taken."
    ]
  }
}
```

#### Invalid status

```json
{
  "message": "The selected status is invalid.",
  "errors": {
    "status": [
      "The selected status is invalid."
    ]
  }
}
```

### 6. Current Implementation Reference

Frontend:

- [app.js](/c:/wamp64/www/pbb/hub.ph/resources/js/app.js)
  - `buildHubSeedFromTreeRow(...)`
  - `buildHubDefaultName(...)`
  - `buildHubLocationSummary(...)`
  - `openHubEditorModal(...)`

Backend:

- [HubController.php](/c:/wamp64/www/pbb/hub.ph/app/Http/Controllers/Api/HubController.php)
  - `store(...)`
  - `update(...)`
  - `validateHub(...)`

---

## B. Add Uplink

### 1. Current Field Inventory

Current implementation reference:

- `resources/js/app.js`
  - `openAddUplinkModal(...)`
  - `buildHubSavePayload(...)`
  - `getManualUplinkIds(...)`

Visible field:

1. `uplink_hub_ids`
- visible label:
  - no explicit label element
  - current instructional copy:
    - `Select additional hubs that {hub.name} should uplink to.`
- input type:
  - helper `ui.select`
- required or optional:
  - optional
- default value behavior:
  - preselected from current manual uplinks only

There are no other visible form fields in the current `Add Uplink` modal.

### 2. Uplink Selection Behavior

This is the current real behavior:

- multi-select
- searchable
- clearable
- static options from already loaded hub state
- not async-loaded in the current implementation

Option source:

- `state.hubs.list`

Current option filtering:

- exclude the current hub itself

Current option label shape:

- `candidate.name`

Current option value shape:

- stringified hub id in the select UI
- converted back to integer ids for payload submission

Current selected value shape in memory:

- array of string ids

Submitted value shape:

- array of integer ids

There is no current server-side search/filter query for this flow.

### 3. Payload Contract

Important: `Add Uplink` does not currently send only `uplink_hub_ids`.

It sends a full hub update payload by merging the existing hub fields with the new uplink array.

Current payload shape:

```json
{
  "name": "Adlaon, CEBU CITY, CEBU",
  "code": null,
  "domain": null,
  "deployment": "barangay",
  "status": "planned",
  "country_code": "PH",
  "reg_code": "07",
  "prov_code": "0722",
  "citymun_code": "072217",
  "brgy_code": "072217001",
  "uplink_hub_ids": [12, 19]
}
```

Current endpoint:

- `PUT /api/admin/hubs/{hub}`

The payload is built through:

- `buildHubSavePayload(hub, { uplink_hub_ids: [...] })`

### 4. Validation / Error Response Examples

Backend validation is currently shared with normal hub save validation in:

- `app/Http/Controllers/Api/HubController.php`
  - `validateHub(...)`

Representative examples:

#### Invalid uplink hub id

```json
{
  "message": "The selected uplink hub ids.0 is invalid.",
  "errors": {
    "uplink_hub_ids.0": [
      "The selected uplink hub ids.0 is invalid."
    ]
  }
}
```

#### Duplicate uplink id in the same payload

```json
{
  "message": "The uplink hub ids.1 field has a duplicate value.",
  "errors": {
    "uplink_hub_ids.1": [
      "The uplink hub ids.1 field has a duplicate value."
    ]
  }
}
```

#### Self-uplink

```json
{
  "message": "The selected uplink hub ids.0 is invalid.",
  "errors": {
    "uplink_hub_ids.0": [
      "The selected uplink hub ids.0 is invalid."
    ]
  }
}
```

General note:

- there is no current dedicated add-uplink validator or special response shape
- this flow uses the same hub update validator as the main save flow

### 5. Current Implementation Reference

Frontend:

- [app.js](/c:/wamp64/www/pbb/hub.ph/resources/js/app.js)
  - `openAddUplinkModal(...)`
  - `buildHubSavePayload(...)`
  - `getManualUplinkIds(...)`

Backend:

- [HubController.php](/c:/wamp64/www/pbb/hub.ph/app/Http/Controllers/Api/HubController.php)
  - `update(...)`
  - `validateHub(...)`
  - `syncManualUplinks(...)`

---

## Acceptance Notes

### For `Hub Add/Edit`

The most important acceptance question is:

- can `ui.form.modal` represent two current real variants cleanly:
  - geodata-driven form with hidden/generated values and display context
  - other-deployment form with editable name

That is the actual proof point for:

- `mode`
- `hidden`
- `display`
- declarative conditional behavior

### For `Add Uplink`

The most important acceptance question is:

- can `ui.form.modal` host the existing helper `ui.select` cleanly for a multi-select, searchable relationship flow without fallback custom markup

That is the actual proof point for:

- hosted `ui.select`
- shared error mapping

## Files To Use During Acceptance Proof

- [app.js](/c:/wamp64/www/pbb/hub.ph/resources/js/app.js)
- [HubController.php](/c:/wamp64/www/pbb/hub.ph/app/Http/Controllers/Api/HubController.php)
- [ui-form-modal-improvement-proposal.md](/c:/wamp64/www/pbb/hub.ph/docs/ui-form-modal-improvement-proposal.md)
- [ui-form-modal-improvement-response-memo.md](/c:/wamp64/www/pbb/hub.ph/docs/ui-form-modal-improvement-response-memo.md)
