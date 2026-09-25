-- Blog demo authors, posts and comments: team@webconsulting.at -> office@webconsulting.at
--
-- The blog seeder (desiderio >= 4.7.0, BlogPageTreeSeeder) writes
-- office@webconsulting.at, but Blog classico (root 15) is not reseeded and a
-- reseed of 69/390 also rewrites SEO fields that content payloads own, so the
-- existing rows are updated in place. Idempotent: a second run changes nothing.
--
--   ddev mysql < Build/Scripts/sql/webconsulting-contact-blog.sql
--   (production: run it against the TYPO3 database, then cache:flush)

UPDATE tx_blog_domain_model_author
   SET email = 'office@webconsulting.at', tstamp = UNIX_TIMESTAMP()
 WHERE email = 'team@webconsulting.at';

UPDATE pages
   SET author_email = 'office@webconsulting.at', tstamp = UNIX_TIMESTAMP()
 WHERE author_email = 'team@webconsulting.at';

-- Translations keep the source's old values in l10n_diffsource (JSON in v14).
UPDATE pages
   SET l10n_diffsource = REPLACE(l10n_diffsource, 'team@webconsulting.at', 'office@webconsulting.at')
 WHERE l10n_diffsource LIKE '%team@webconsulting.at%';

UPDATE tx_blog_domain_model_comment
   SET email = 'office@webconsulting.at', tstamp = UNIX_TIMESTAMP()
 WHERE email = 'team@webconsulting.at';
