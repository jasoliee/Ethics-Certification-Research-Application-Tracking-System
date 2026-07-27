# User Management

This legacy topic now points to the detailed verified guides:

- [Account creation and user management](ACCOUNT_CREATION_AND_USER_MANAGEMENT.md)
- [Username generation](USERNAME_GENERATION.md)
- [Bulk account import](BULK_ACCOUNT_IMPORT.md)
- [Adviser user management](ADVISER_USER_MANAGEMENT.md)
- [Dropdown option management](DROPDOWN_OPTION_MANAGEMENT.md)
- [Audit log](AUDIT_LOG.md)
- [Email and password setup](EMAIL_AND_PASSWORD_SETUP.md)
- [UI and responsive design](UI_AND_RESPONSIVE_DESIGN.md)
- [Manual visual validation](MANUAL_VISUAL_VALIDATION.md)

The previous document described creator-entered passwords, CSV templates, and immediate import. Those behaviors are obsolete. Current account creation uses a server-generated username, random internal credential, pending setup state, one-time emailed password link, role-specific `.xlsx` templates, categorized preview, and explicit single-use confirmation of valid rows.

Import preview separates active existing accounts from soft-deleted archived accounts. Only the RES Lead can restore one or all preview-listed archived accounts. Restoration reactivates the original row after conflict and identity checks, preserving its ID and relationships; an Adviser receives guidance but no restore control or route. Restored rows cannot be created again during confirmation.

The Account Information header groups identity details on the left, application totals in the center, and Back to User Management on the right. Reset/setup resend uses the shared hollow green button. User, Adviser, option, audit, preview, Applicant application, Adviser application, and requirement tables retain their columns inside focusable internal horizontal-scroll regions instead of widening the complete page.
