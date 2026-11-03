# SMTP and application email

## Supported workflows

- Store onboarding and replacement-owner invitations, including explicit resends. A resend revokes earlier pending links; retrying delivery keeps the same token.
- Self-service password reset, administrator-assisted owner reset by email, and a private-link fallback. Public recovery responses do not disclose whether an account exists. Disabled accounts receive no new recovery emails.
- Email verification and resends, limited to one per account/address per minute, including the Livewire profile action. An email-address change sends verification to the new address and a security notice to the old address.
- Security notices for changed/reset passwords and confirmed/disabled two-factor authentication. Notices contain no passwords, recovery codes, or financial data.
- Optional announcement email: select **Also email active store owners when published**. Existing announcements remain portal-only. The first scheduler pass after publication snapshots current active owners of active stores in the selected audience. Newly added stores see an all-store announcement in the portal but do not receive an old email. Republish does not resend the original snapshot. Withdraw stops unsent mail; already accepted mail cannot be recalled.
- Branded HTML and plain-text messages, administrator delivery history, failure retries, and a test button restricted to the signed-in administrator's address.

## Delivery and privacy

`email_deliveries` is the durable transactional outbox. Invitation and relevant account changes write delivery work inside their database transaction. SMTP runs separately, so connection failures do not recreate stores or undo completed business operations. No historical invitations or announcements are backfilled.

The outbox stores recipient, kind, source/store/user identifiers, status, attempts, expiry, timestamps, and a safe error category. Link-bearing payloads are encrypted using the application key and hidden from model serialization and administrator screens. Payloads are removed after acceptance, cancellation, or expiry, including expired failed messages. The application key remains necessary to process pending messages and restore backups. Recipient/status metadata remains available for operational history.

Before sending, the application rechecks current account status, recipient email, invitation/token validity, store status, announcement publication/audience, and active ownership as appropriate. Password-reset tokens are checked against Laravel's broker. A security alert may intentionally target the former email address. Each delivery clears tenant context before and after work.

Delivery is at least once: if a server accepts a message but the process dies before recording success, a retry may duplicate the email. Retries reuse the same Message-ID and one-use account link; recipient servers are not required to deduplicate. “Sent” records SMTP acceptance, not inbox placement or a read receipt. SMTP alone does not provide bounce/complaint webhooks or mailbox access.

## Scheduler and retries

The existing private Hostinger scheduler runs `app:mail-drain --limit=10 --max-time=35` each minute. It uses a shared database-cache lock, a five-minute claim lease, and a 10-second SMTP socket timeout. The 35-second budget is checked between messages, so a message in progress can extend it. A terminated worker's claim becomes eligible after lease expiry. The separate generic queue drain remains available for other jobs.

Delivery failures retry after 1, 5, 15, and 30 minutes, then become Failed after the fifth attempt. An administrator can grant one more attempt while the original link remains eligible; the lifetime attempt count is retained and the retry is audited. Expired invitations need a new invitation, and expired recovery requests need a new recovery request. Log/array/failover-to-log transports cannot be marked as SMTP delivery.

Use **Admin Portal → Email delivery** to filter statuses, inspect failures, retry eligible failed emails, or send a test to your own address. **Manage store → View store email deliveries** filters the same history. SMTP credentials and message bodies are never exposed in these screens. **System status** distinguishes configuration from a recorded SMTP acceptance and highlights exhausted deliveries. Check scheduler heartbeat freshness separately.

## Private production configuration

Keep credentials in the private production `.env`, never Git, public files, audit metadata, or command output. For the provided Hostinger mailbox use:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=<mailbox address>
MAIL_PASSWORD=<private password>
MAIL_FROM_ADDRESS=<authorized sender address>
MAIL_FROM_NAME="Pyesio"
MAIL_TIMEOUT=10
MAIL_REQUIRE_TLS=true
```

Remove an overriding `MAIL_URL` when configuring individual fields. Port 465 uses implicit TLS; port 587 uses `MAIL_SCHEME=smtp` with required STARTTLS. TLS certificate validation remains enabled. Refresh configuration with `php artisan config:cache` after an authorized change. Keep the private `.env`, cached configuration, backup copies, and any temporary configuration payload readable only by the hosting account.

Provider reference: [Hostinger email configuration](https://www.hostinger.com/support/4305847-set-up-hostinger-email-on-your-applications-and-devices/). Framework reference: [Laravel mail](https://laravel.com/framework/docs/13.x/mail). Provider sending limits and spam filtering still apply; check the mailbox plan and sender-domain authentication in hPanel if mail is accepted but does not arrive.

## Release verification

The local suite covers transaction rollback, encrypted payloads, retry/redaction, revocation, suspended stores, valid recovery/verification links, changed recipient addresses, announcement opt-in/audience/withdrawal, lease recovery, context cleanup, admin permissions, and branded HTML/plain text. The production delivery check uses only the mailbox explicitly approved by the operator and a plain test message without account links or store data.

Deployment and the live delivery result are recorded in the latest release notes after verification.
