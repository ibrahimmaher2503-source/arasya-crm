# CORE-01 reference scope slice

`OrganizationReference` is an immutable, in-memory reference identity (`kind`, `referenceId`, and owning `entityId`). `OrganizationReferenceGuard` fails closed for blank or unknown identity data, requires the exact kind/ID/entity tuple when matching a reference, and permits a link only when both references belong to the same entity. An `entity` reference must identify its own entity.

Supported kinds cover CORE-01: entity, department, branch, warehouse, site, currency, unit, status, expense category, document numbering, and approval policy. The guard proves entity-boundary and identity matching only; it does not assert department/branch/site hierarchy, currency or unit compatibility, numbering rules, approval semantics, persistence constraints, authorization, auditing, or activation policy. It is read-only and has no database, migration, API, or mutation side effects; callers must not treat it as enforcement beyond this in-memory contract.
