/* tsqllint-disable */
-- Application names are unique: messages are grouped and deleted by name.
-- Fails if two applications already share a name: rename one first.
CREATE UNIQUE INDEX IF NOT EXISTS idx_applications_name ON applications (name);
