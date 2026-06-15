# BillingResourcesNewServers

Let users create new servers using resources from their account pool. Requires **Billing Core**, **Billing Resources**, and deducts allocated resources when a server is created.


## Features

- **User-facing**
  - **Overview → Create Server** (`/server/create`)
  - Choose spell, resources, placement (location/node/realm), allocations
  - Server created from available (unallocated) account resources

- **Admin**
  - **Fremium Resources → New Server Settings**
  - Enable/configure self-service server creation
  - Per-field policies for create form — default, fixed, or hidden resource fields (memory, CPU, disk, swap, IO, DB/backup/allocation limits)
  - Placement policies — location, node, realm, spell (with auto-select strategies)
  - **New Server Permissions** — per-user permissions, permission groups, resource-level access (open/restricted per location, node, realm, spell)


## Authors

- NaysKutzu  
- MythicalSystems
