-- Adds the extra columns the Inventory > Digital Library table shows.
-- Only needed if you already imported an older schema.sql.
ALTER TABLE digital_files
  ADD COLUMN call_no VARCHAR(50) NULL AFTER file_size_bytes,
  ADD COLUMN accession_number VARCHAR(50) NULL AFTER call_no,
  ADD COLUMN book_location VARCHAR(100) NOT NULL DEFAULT 'Digital Library' AFTER accession_number;
