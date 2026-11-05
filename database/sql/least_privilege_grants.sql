-- Least-privilege account for the running application. Replace the password, then
-- set DB_USERNAME=fund_app in production. Run migrations with a separate admin account.
-- The database triggers already refuse changes to immutable tables; these grants
-- add a second, independent layer by never giving the app UPDATE or DELETE on them.

CREATE USER IF NOT EXISTS 'fund_app'@'%' IDENTIFIED BY 'CHANGE_ME';

GRANT SELECT ON fund.* TO 'fund_app'@'%';

-- Append-only financial and audit records.
GRANT INSERT ON fund.audit_logs TO 'fund_app'@'%';
GRANT INSERT ON fund.ledger_transactions TO 'fund_app'@'%';
GRANT INSERT ON fund.ledger_entries TO 'fund_app'@'%';
GRANT INSERT ON fund.documents TO 'fund_app'@'%';
GRANT INSERT ON fund.monthly_rates TO 'fund_app'@'%';
GRANT INSERT ON fund.dues_obligations TO 'fund_app'@'%';
GRANT INSERT ON fund.dues_allocations TO 'fund_app'@'%';
GRANT INSERT ON fund.dues_waivers TO 'fund_app'@'%';
GRANT INSERT ON fund.expenses TO 'fund_app'@'%';
GRANT INSERT ON fund.expense_documents TO 'fund_app'@'%';
GRANT INSERT ON fund.account_transfers TO 'fund_app'@'%';
GRANT INSERT ON fund.account_reconciliations TO 'fund_app'@'%';

-- Updated, never deleted.
GRANT INSERT, UPDATE ON fund.payment_submissions TO 'fund_app'@'%';
GRANT INSERT, UPDATE ON fund.users TO 'fund_app'@'%';
GRANT INSERT, UPDATE ON fund.member_invitations TO 'fund_app'@'%';
GRANT INSERT, UPDATE ON fund.funds TO 'fund_app'@'%';
GRANT INSERT, UPDATE ON fund.fund_purposes TO 'fund_app'@'%';
GRANT INSERT, UPDATE ON fund.financial_accounts TO 'fund_app'@'%';
GRANT INSERT, UPDATE ON fund.fund_account_balances TO 'fund_app'@'%';
GRANT UPDATE ON fund.audit_chain_heads TO 'fund_app'@'%';

-- Framework tables.
GRANT SELECT, INSERT, UPDATE, DELETE ON fund.sessions TO 'fund_app'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON fund.cache TO 'fund_app'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON fund.cache_locks TO 'fund_app'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON fund.jobs TO 'fund_app'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON fund.job_batches TO 'fund_app'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON fund.failed_jobs TO 'fund_app'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON fund.password_reset_tokens TO 'fund_app'@'%';

FLUSH PRIVILEGES;
