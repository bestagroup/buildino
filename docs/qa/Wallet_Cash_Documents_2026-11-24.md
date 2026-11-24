# Building/unit cash documents

Entry: Operations → Finance and Wallet → دریافت و پرداخت (`/management/operations/wallet-cash`).

A manager with `wallet-cash.manage` selects an authorized building (automatic for exactly one). Receipt increases the selected wallet; payment debits it. Omitting unit targets the building wallet. Choosing a unit targets only that unit in that building. Amount and document number are required; description and a PNG/JPEG image up to 5 MB are optional. This records an external receipt/payment already performed; it does not initiate a bank transfer, allocate expense charges, or settle invoices automatically.

Documents are append-only through this UI, linked to wallet transfers and balanced wallet entries. No editing/deletion endpoint is provided. The existing WalletService enforces active wallets and available funds; payout locks and consumes its own reservation in the same database transaction. Pre-existing locked funds are not consumed. Building lock serializes reference validation; UUID and building/direction/document-number uniqueness prevent duplicate registration. A reused reference with changed unit/amount/type is rejected. Private scanned attachments and document writes roll back together on failure. Download is building-permission scoped.

The records are classified as wallet adjustments. They do not auto-post to general-ledger income/expense categories or change a BuildingExpense's status, because this form does not collect a financial category or a specific expense reference. Do not also record the same cash movement through a separate wallet payout/top-up operation.

Deploy: `php artisan migrate --force` then `php artisan optimize:clear`. Migration adds wallet_cash_documents and narrowly grants wallet-cash.manage to existing superadmin/building_manager/finance_manager roles, preserving all other assignments. Standard role seeding retains the permission. No asset build required.

Validation: 15 targeted tests / 101 assertions passed across cash documents, wallet operation flows and accounting bridge. Covers both wallet targets, duplicate submissions/reference conflicts, insufficient/locked balance, foreign-building access, optional private image download, rejected upload rollback, and Operations page rendering. Production withdrawals and browser visual testing were not performed. Concurrent MySQL worker load was not executed; transaction lock order and replay behavior were reviewed/tested.
