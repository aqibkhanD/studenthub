-- ============================================================
--  PostgreSQL initialisation script
--  Runs once when the container is first created
-- ============================================================

-- Enable pgvector extension (needed from Phase 4 for AI embeddings)
CREATE EXTENSION IF NOT EXISTS vector;

-- Enable pgcrypto (for UUID generation helpers)
CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- Enable pg_trgm (for trigram-based LIKE search optimisation)
CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- Verify
SELECT extname, extversion FROM pg_extension
WHERE extname IN ('vector', 'pgcrypto', 'pg_trgm');
