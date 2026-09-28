-- Layer-1 design decisions (SPEC §5.4), one row per decision: seed, secondary,
-- typography, scale, spacing, radius, shadow, container, surface_contrast. Derived
-- values are never stored; they are recomputed and compiled into tokens.css on save.
CREATE TABLE design_tokens (
    id {{pk}},
    group_key VARCHAR(64) NOT NULL,
    value_json TEXT NOT NULL,
    CONSTRAINT design_tokens_group_key_unique UNIQUE (group_key)
);
